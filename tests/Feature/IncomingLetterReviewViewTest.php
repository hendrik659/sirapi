<?php

namespace Tests\Feature;

use App\Models\Division;
use App\Models\IncomingLetter;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class IncomingLetterReviewViewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    public function test_pimpinan_sees_the_review_button_for_a_waiting_letter(): void
    {
        $pimpinan = $this->makeUser('pimpinan', 'Pimpinan');
        $letter = $this->makeLetter($pimpinan, [
            'status' => IncomingLetter::STATUS_MENUNGGU_PEMERIKSAAN,
            'submitted_for_review_at' => now(),
        ]);

        $this->actingAs($pimpinan)
            ->get(route('incoming-letters.show', $letter))
            ->assertOk()
            ->assertSee('data-testid="incoming-letter-review-link"', false)
            ->assertSee('href="'.route('incoming-letters.review.create', $letter).'"', false)
            ->assertSee('Periksa dan Teruskan');
    }

    public function test_sdm_division_head_sees_the_review_button(): void
    {
        $sdm = $this->makeDivision('SDM & Umum', 'SDM');
        $divisionHead = $this->makeUser('ketua_divisi', 'Ketua Divisi', $sdm);
        $letter = $this->makeLetter($divisionHead, [
            'status' => IncomingLetter::STATUS_MENUNGGU_PEMERIKSAAN,
            'submitted_for_review_at' => now(),
        ]);

        $this->actingAs($divisionHead)
            ->get(route('incoming-letters.show', $letter))
            ->assertOk()
            ->assertSee('data-testid="incoming-letter-review-link"', false);
    }

    public function test_unauthorized_roles_do_not_see_the_review_button(): void
    {
        $nonSdmDivision = $this->makeDivision('Redaksi', 'RED');
        $users = [
            $this->makeUser('admin_surat', 'Admin Surat'),
            $this->makeUser('anggota_divisi', 'Anggota Divisi'),
            $this->makeUser('ketua_divisi', 'Ketua Divisi', $nonSdmDivision),
        ];
        $letter = $this->makeLetter($users[0], [
            'status' => IncomingLetter::STATUS_MENUNGGU_PEMERIKSAAN,
            'submitted_for_review_at' => now(),
        ]);

        foreach ($users as $user) {
            $this->actingAs($user)
                ->get(route('incoming-letters.show', $letter))
                ->assertOk()
                ->assertDontSee('data-testid="incoming-letter-review-link"', false);
        }
    }

    public function test_review_button_only_appears_while_waiting_and_before_a_review_exists(): void
    {
        $pimpinan = $this->makeUser('pimpinan', 'Pimpinan');
        $division = $this->makeDivision();
        $newLetter = $this->makeLetter($pimpinan);
        $completedLetter = $this->makeLetter($pimpinan, [
            'status' => IncomingLetter::STATUS_SELESAI,
            'destination_division_id' => $division->id,
        ]);
        $reviewedWaitingLetter = $this->makeLetter($pimpinan, [
            'status' => IncomingLetter::STATUS_MENUNGGU_PEMERIKSAAN,
            'submitted_for_review_at' => now(),
        ]);
        $reviewedWaitingLetter->review()->create([
            'reviewed_by' => $pimpinan->id,
            'destination_division_id' => $division->id,
            'review_note' => null,
            'reviewed_at' => now(),
        ]);

        foreach ([$newLetter, $completedLetter, $reviewedWaitingLetter] as $letter) {
            $this->actingAs($pimpinan)
                ->get(route('incoming-letters.show', $letter))
                ->assertOk()
                ->assertDontSee('data-testid="incoming-letter-review-link"', false);
        }
    }

    public function test_authorized_user_can_open_the_responsive_review_form_with_active_divisions_only(): void
    {
        $pimpinan = $this->makeUser('pimpinan', 'Pimpinan');
        $activeDivision = $this->makeDivision('Redaksi', 'RED');
        $inactiveDivision = $this->makeDivision('Keuangan', 'KEU', false);
        $letter = $this->makeLetter($pimpinan, [
            'agenda_number' => 'AGD-REVIEW-VIEW',
            'status' => IncomingLetter::STATUS_MENUNGGU_PEMERIKSAAN,
            'submitted_for_review_at' => now(),
        ]);

        $this->actingAs($pimpinan)
            ->get(route('incoming-letters.review.create', $letter))
            ->assertOk()
            ->assertViewIs('incoming-letters.review')
            ->assertSee('Periksa Surat Masuk')
            ->assertSee('AGD-REVIEW-VIEW')
            ->assertSee('action="'.route('incoming-letters.review.store', $letter).'"', false)
            ->assertSee('data="'.route('incoming-letters.preview', $letter).'"', false)
            ->assertSee('data-testid="incoming-letter-review-form"', false)
            ->assertSee('name="action"', false)
            ->assertSee('value="forward"', false)
            ->assertSee('value="archive_directly"', false)
            ->assertSee('Teruskan ke Divisi')
            ->assertSee('Arsipkan Langsung')
            ->assertSee('name="destination_division_id"', false)
            ->assertSee('<option value="'.$activeDivision->id.'"', false)
            ->assertDontSee($inactiveDivision->name)
            ->assertSee('name="review_note"', false)
            ->assertSee('maxlength="2000"', false)
            ->assertSee('data-confirmation-title="Teruskan Surat"', false)
            ->assertSee('data-confirmation-action-label="Teruskan ke Divisi"', false)
            ->assertSee('Surat akan diteruskan ke divisi yang dipilih dan pemeriksaan tidak dapat diulang.')
            ->assertSee('Arsipkan Surat Langsung')
            ->assertSee('Surat ini akan diselesaikan tanpa diteruskan ke divisi.')
            ->assertSee('data-review-action-form', false)
            ->assertDontSee('summary')
            ->assertDontSee($letter->document_path)
            ->assertDontSee('/storage/');
    }

    public function test_review_result_and_status_history_are_displayed_on_the_detail_page(): void
    {
        $division = $this->makeDivision('Redaksi', 'RED');
        $viewer = $this->makeUser('ketua_divisi', 'Ketua Divisi Tujuan', $division);
        $reviewer = $this->makeUser('pimpinan', 'Pimpinan');
        $letter = $this->makeLetter($viewer, [
            'status' => IncomingLetter::STATUS_SELESAI,
            'destination_division_id' => $division->id,
        ]);
        $letter->review()->create([
            'reviewed_by' => $reviewer->id,
            'destination_division_id' => $division->id,
            'review_note' => 'Segera koordinasikan dengan redaksi.',
            'reviewed_at' => '2026-08-03 15:30:00',
        ]);
        $letter->statusHistories()->create([
            'previous_status' => IncomingLetter::STATUS_MENUNGGU_PEMERIKSAAN,
            'new_status' => IncomingLetter::STATUS_SELESAI,
            'activity' => 'Surat diperiksa dan diteruskan ke Divisi Redaksi',
            'notes' => 'Segera koordinasikan dengan redaksi.',
            'changed_by' => $reviewer->id,
        ]);

        $this->actingAs($viewer)
            ->get(route('incoming-letters.show', $letter))
            ->assertOk()
            ->assertSee('Hasil Pemeriksaan')
            ->assertSee($reviewer->name)
            ->assertSee('3 Agustus 2026, 15:30 WIB')
            ->assertSee($division->name)
            ->assertSee('Segera koordinasikan dengan redaksi.')
            ->assertSee('Riwayat Status')
            ->assertSee('Surat diperiksa dan diteruskan ke Divisi Redaksi')
            ->assertSee('Menunggu Pemeriksaan')
            ->assertSee('Selesai')
            ->assertSee('Diubah oleh '.$reviewer->name);
    }

    public function test_an_empty_review_note_is_displayed_as_no_note(): void
    {
        $viewer = $this->makeUser('anggota_divisi', 'Anggota Divisi');
        $reviewer = $this->makeUser('pimpinan', 'Pimpinan');
        $division = $this->makeDivision();
        $letter = $this->makeLetter($viewer, [
            'status' => IncomingLetter::STATUS_SELESAI,
            'destination_division_id' => $division->id,
        ]);
        $letter->review()->create([
            'reviewed_by' => $reviewer->id,
            'destination_division_id' => $division->id,
            'review_note' => null,
            'reviewed_at' => now(),
        ]);

        $this->actingAs($viewer)
            ->get(route('incoming-letters.show', $letter))
            ->assertOk()
            ->assertSee('Tidak ada catatan.');
    }

    public function test_index_displays_the_completed_status_filter_badge_and_destination_division(): void
    {
        $viewer = $this->makeUser('anggota_divisi', 'Anggota Divisi');
        $division = $this->makeDivision('Pemasaran', 'PEM');
        $letter = $this->makeLetter($viewer, [
            'agenda_number' => 'AGD-FORWARDED-001',
            'status' => IncomingLetter::STATUS_SELESAI,
            'destination_division_id' => $division->id,
        ]);

        $this->actingAs($viewer)
            ->get(route('incoming-letters.index', [
                'status' => IncomingLetter::STATUS_SELESAI,
            ]))
            ->assertOk()
            ->assertSee('AGD-FORWARDED-001')
            ->assertSee('value="selesai"', false)
            ->assertDontSee('value="diteruskan_ke_divisi"', false)
            ->assertDontSee('value="ditugaskan_ke_anggota"', false)
            ->assertSee('class="badge rs-status-badge rs-status-done"', false)
            ->assertSee('Selesai')
            ->assertSee($division->name)
            ->assertDontSee('Penanggung Jawab')
            ->assertDontSee('Tugas Saya')
            ->assertDontSee('Tugaskan Anggota');
    }

    public function test_assignment_routes_and_actions_are_removed(): void
    {
        $this->assertFalse(Route::has('incoming-letters.assignment.create'));
        $this->assertFalse(Route::has('incoming-letters.assignment.store'));

        $viewer = $this->makeUser('anggota_divisi', 'Anggota Divisi');
        $letter = $this->makeLetter($viewer);

        $this->actingAs($viewer)
            ->get(route('incoming-letters.show', $letter))
            ->assertOk()
            ->assertDontSee('Tugaskan Anggota')
            ->assertDontSee('Hasil Penugasan')
            ->assertDontSee('Mulai Tindak Lanjut')
            ->assertDontSee('Arsipkan');
    }

    public function test_destination_and_completed_status_filters_persist_across_pagination(): void
    {
        $viewer = $this->makeUser('anggota_divisi', 'Anggota Divisi');
        $targetDivision = $this->makeDivision('Pemasaran', 'PEM');
        $otherDivision = $this->makeDivision('Keuangan', 'KEU');

        foreach (range(1, 11) as $number) {
            $this->makeLetter($viewer, [
                'agenda_number' => 'AGD-FILTER-'.str_pad((string) $number, 2, '0', STR_PAD_LEFT),
                'status' => IncomingLetter::STATUS_SELESAI,
                'destination_division_id' => $targetDivision->id,
            ]);
        }

        $this->makeLetter($viewer, [
            'agenda_number' => 'AGD-OTHER-DIVISION',
            'status' => IncomingLetter::STATUS_SELESAI,
            'destination_division_id' => $otherDivision->id,
        ]);

        $this->actingAs($viewer)
            ->get(route('incoming-letters.index', [
                'status' => IncomingLetter::STATUS_SELESAI,
                'destination_division_id' => $targetDivision->id,
            ]))
            ->assertOk()
            ->assertSee('AGD-FILTER-11')
            ->assertDontSee('AGD-OTHER-DIVISION')
            ->assertSee('status=selesai', false)
            ->assertSee('destination_division_id='.$targetDivision->id, false)
            ->assertSee('page=2', false);
    }

    private function makeDivision(
        string $name = 'Redaksi',
        string $code = 'RED',
        bool $isActive = true,
    ): Division {
        return Division::query()->create([
            'name' => $name,
            'code' => $code,
            'is_active' => $isActive,
        ]);
    }

    private function makeUser(string $roleSlug, string $roleName, ?Division $division = null): User
    {
        $role = Role::query()->firstOrCreate(
            ['slug' => $roleSlug],
            ['name' => $roleName],
        );

        return User::query()->create([
            'name' => $roleName.' '.fake()->unique()->numerify('###'),
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'role_id' => $role->id,
            'division_id' => $division?->id,
            'is_active' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeLetter(User $creator, array $overrides = []): IncomingLetter
    {
        $path = 'incoming-letters/2026/'.fake()->uuid().'.pdf';
        Storage::disk('local')->put($path, '%PDF-1.4 test document');

        return IncomingLetter::query()->create(array_merge([
            'agenda_number' => 'AGD-'.fake()->unique()->numerify('####'),
            'letter_number' => '001/RS/VIII/2026',
            'sender_name' => 'Instansi Pengirim',
            'addressed_to' => 'Radar Surat',
            'letter_date' => '2026-08-01',
            'received_date' => '2026-08-03',
            'received_via' => 'fisik',
            'subject' => 'Permohonan pemeriksaan surat',
            'priority' => 'biasa',
            'destination_division_id' => null,
            'document_path' => $path,
            'original_document_name' => 'surat.pdf',
            'document_mime_type' => 'application/pdf',
            'document_size' => Storage::disk('local')->size($path),
            'status' => IncomingLetter::STATUS_BARU_DITERIMA,
            'created_by' => $creator->id,
            'submitted_for_review_at' => null,
        ], $overrides));
    }
}
