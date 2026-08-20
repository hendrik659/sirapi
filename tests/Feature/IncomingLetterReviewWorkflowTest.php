<?php

namespace Tests\Feature;

use App\Models\Division;
use App\Models\IncomingLetter;
use App\Models\IncomingLetterStatusHistory;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class IncomingLetterReviewWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    public function test_guest_cannot_access_review_routes(): void
    {
        $letter = $this->makeLetter($this->makeUser('admin_surat'));

        $this->get(route('incoming-letters.review.create', $letter))
            ->assertRedirect(route('login'));
        $this->post(route('incoming-letters.review.store', $letter), [])
            ->assertRedirect(route('login'));
    }

    public function test_pimpinan_can_open_and_store_a_review(): void
    {
        $pimpinan = $this->makeUser('pimpinan');
        $activeDivision = $this->makeDivision('Redaksi', 'RED');
        $inactiveDivision = $this->makeDivision('Keuangan', 'KEU', false);
        $letter = $this->makeLetter($pimpinan);

        $this->actingAs($pimpinan)
            ->get(route('incoming-letters.review.create', $letter))
            ->assertOk()
            ->assertViewIs('incoming-letters.review')
            ->assertViewHas('incomingLetter', fn (IncomingLetter $viewLetter) => $viewLetter->is($letter))
            ->assertViewHas('divisions', fn ($divisions) => $divisions->contains('id', $activeDivision->id)
                && ! $divisions->contains('id', $inactiveDivision->id));

        $this->actingAs($pimpinan)
            ->post(route('incoming-letters.review.store', $letter), [
                'destination_division_id' => $activeDivision->id,
                'review_note' => 'Mohon segera ditindaklanjuti.',
            ])
            ->assertRedirect(route('incoming-letters.show', $letter))
            ->assertSessionHas('success');

        $review = $letter->review()->firstOrFail();

        $this->assertSame($pimpinan->id, $review->reviewed_by);
        $this->assertSame($activeDivision->id, $review->destination_division_id);
        $this->assertSame('Mohon segera ditindaklanjuti.', $review->review_note);
        $this->assertNotNull($review->reviewed_at);
        $this->assertTrue($review->reviewer->is($pimpinan));
        $this->assertTrue($review->destinationDivision->is($activeDivision));

        $letter->refresh();

        $this->assertSame(IncomingLetter::STATUS_SELESAI, $letter->status);
        $this->assertSame($activeDivision->id, $letter->destination_division_id);
        $this->assertDatabaseHas('incoming_letter_status_histories', [
            'incoming_letter_id' => $letter->id,
            'previous_status' => IncomingLetter::STATUS_MENUNGGU_PEMERIKSAAN,
            'new_status' => IncomingLetter::STATUS_SELESAI,
            'activity' => 'Surat diperiksa dan diteruskan ke Divisi Redaksi',
            'notes' => 'Mohon segera ditindaklanjuti.',
            'changed_by' => $pimpinan->id,
        ]);
    }

    public function test_sdm_division_head_can_review_with_an_empty_note(): void
    {
        $sdm = $this->makeDivision('SDM & Umum', 'SDM');
        $destinationDivision = $this->makeDivision('Pemasaran', 'PEM');
        $divisionHead = $this->makeUser('ketua_divisi', $sdm);
        $letter = $this->makeLetter($divisionHead);

        $this->actingAs($divisionHead)
            ->post(route('incoming-letters.review.store', $letter), [
                'destination_division_id' => $destinationDivision->id,
                'review_note' => '',
            ])
            ->assertRedirect(route('incoming-letters.show', $letter));

        $this->assertDatabaseHas('incoming_letter_reviews', [
            'incoming_letter_id' => $letter->id,
            'reviewed_by' => $divisionHead->id,
            'destination_division_id' => $destinationDivision->id,
            'review_note' => null,
        ]);
    }

    public function test_admin_surat_is_forbidden_from_reviewing(): void
    {
        $admin = $this->makeUser('admin_surat');
        $letter = $this->makeLetter($admin);

        $this->actingAs($admin)
            ->get(route('incoming-letters.review.create', $letter))
            ->assertForbidden();
        $this->actingAs($admin)
            ->post(route('incoming-letters.review.store', $letter), [
                'destination_division_id' => $this->makeDivision()->id,
            ])
            ->assertForbidden();
    }

    public function test_non_sdm_division_head_is_forbidden_from_reviewing(): void
    {
        $division = $this->makeDivision('Redaksi', 'RED');
        $divisionHead = $this->makeUser('ketua_divisi', $division);
        $letter = $this->makeLetter($divisionHead);

        $this->actingAs($divisionHead)
            ->get(route('incoming-letters.review.create', $letter))
            ->assertForbidden();
    }

    public function test_division_member_is_forbidden_from_reviewing(): void
    {
        $member = $this->makeUser('anggota_divisi', $this->makeDivision('SDM & Umum', 'SDM'));
        $letter = $this->makeLetter($member);

        $this->actingAs($member)
            ->get(route('incoming-letters.review.create', $letter))
            ->assertForbidden();
    }

    public function test_a_newly_received_letter_cannot_be_reviewed(): void
    {
        $pimpinan = $this->makeUser('pimpinan');
        $destinationDivision = $this->makeDivision();
        $letter = $this->makeLetter($pimpinan, IncomingLetter::STATUS_BARU_DITERIMA);

        $this->actingAs($pimpinan)
            ->get(route('incoming-letters.review.create', $letter))
            ->assertForbidden();
        $this->actingAs($pimpinan)
            ->post(route('incoming-letters.review.store', $letter), [
                'destination_division_id' => $destinationDivision->id,
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('incoming_letter_reviews', [
            'incoming_letter_id' => $letter->id,
        ]);
        $this->assertSame(IncomingLetter::STATUS_BARU_DITERIMA, $letter->fresh()->status);
    }

    public function test_destination_division_is_required(): void
    {
        $pimpinan = $this->makeUser('pimpinan');
        $letter = $this->makeLetter($pimpinan);

        $this->actingAs($pimpinan)
            ->post(route('incoming-letters.review.store', $letter), [
                'review_note' => 'Catatan pemeriksaan.',
            ])
            ->assertSessionHasErrors('destination_division_id');

        $this->assertReviewDidNotChangeLetter($letter);
    }

    public function test_an_inactive_destination_division_is_rejected_without_partial_data(): void
    {
        $pimpinan = $this->makeUser('pimpinan');
        $inactiveDivision = $this->makeDivision('Keuangan', 'KEU', false);
        $letter = $this->makeLetter($pimpinan);

        $this->actingAs($pimpinan)
            ->post(route('incoming-letters.review.store', $letter), [
                'destination_division_id' => $inactiveDivision->id,
            ])
            ->assertSessionHasErrors('destination_division_id');

        $this->assertReviewDidNotChangeLetter($letter);
        $this->assertDatabaseCount('incoming_letter_status_histories', 0);
    }

    public function test_an_incoming_letter_cannot_be_reviewed_twice(): void
    {
        $pimpinan = $this->makeUser('pimpinan');
        $destinationDivision = $this->makeDivision();
        $letter = $this->makeLetter($pimpinan);
        $payload = ['destination_division_id' => $destinationDivision->id];

        $this->actingAs($pimpinan)
            ->post(route('incoming-letters.review.store', $letter), $payload)
            ->assertRedirect(route('incoming-letters.show', $letter));

        $this->actingAs($pimpinan)
            ->post(route('incoming-letters.review.store', $letter), $payload)
            ->assertForbidden();

        $this->assertDatabaseCount('incoming_letter_reviews', 1);
        $this->assertDatabaseCount('incoming_letter_status_histories', 1);
    }

    public function test_review_transaction_rolls_back_if_history_cannot_be_created(): void
    {
        $pimpinan = $this->makeUser('pimpinan');
        $destinationDivision = $this->makeDivision();
        $letter = $this->makeLetter($pimpinan);
        IncomingLetterStatusHistory::creating(function () {
            throw new RuntimeException('Simulasi kegagalan pencatatan history.');
        });
        $exception = null;

        $this->withoutExceptionHandling();

        try {
            $this->actingAs($pimpinan)
                ->post(route('incoming-letters.review.store', $letter), [
                    'destination_division_id' => $destinationDivision->id,
                ]);
        } catch (RuntimeException $caughtException) {
            $exception = $caughtException;
        } finally {
            IncomingLetterStatusHistory::flushEventListeners();
        }

        $this->assertNotNull($exception);
        $this->assertSame('Simulasi kegagalan pencatatan history.', $exception->getMessage());
        $this->assertReviewDidNotChangeLetter($letter);
        $this->assertDatabaseCount('incoming_letter_status_histories', 0);
    }

    public function test_creating_and_submitting_a_letter_records_both_status_histories(): void
    {
        $admin = $this->makeUser('admin_surat');

        $this->actingAs($admin)
            ->withHeader('Accept', 'application/json')
            ->post(route('incoming-letters.store'), $this->letterPayload())
            ->assertCreated();

        $letter = IncomingLetter::query()->firstOrFail();

        $this->assertDatabaseHas('incoming_letter_status_histories', [
            'incoming_letter_id' => $letter->id,
            'previous_status' => null,
            'new_status' => IncomingLetter::STATUS_BARU_DITERIMA,
            'activity' => 'Surat dicatat',
            'changed_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->patchJson(route('incoming-letters.submit-for-review', $letter))
            ->assertOk()
            ->assertJsonPath('status', IncomingLetter::STATUS_MENUNGGU_PEMERIKSAAN);

        $this->assertDatabaseHas('incoming_letter_status_histories', [
            'incoming_letter_id' => $letter->id,
            'previous_status' => IncomingLetter::STATUS_BARU_DITERIMA,
            'new_status' => IncomingLetter::STATUS_MENUNGGU_PEMERIKSAAN,
            'activity' => 'Surat dikirim untuk pemeriksaan',
            'changed_by' => $admin->id,
        ]);
        $this->assertSame(2, $letter->statusHistories()->count());
    }

    public function test_complete_admin_to_reviewer_workflow_is_consistent_across_backend_and_views(): void
    {
        $admin = $this->makeUser('admin_surat');
        $pimpinan = $this->makeUser('pimpinan');
        $destinationDivision = $this->makeDivision('Pemasaran', 'PEM');

        $this->actingAs($admin)
            ->post(route('incoming-letters.store'), $this->letterPayload())
            ->assertRedirect();

        $letter = IncomingLetter::query()->where('agenda_number', 'AGD-HISTORY-001')->firstOrFail();

        $this->assertSame(IncomingLetter::STATUS_BARU_DITERIMA, $letter->status);
        $this->assertSame(1, $letter->statusHistories()->count());

        $this->actingAs($admin)
            ->patch(route('incoming-letters.submit-for-review', $letter))
            ->assertRedirect(route('incoming-letters.show', $letter));

        $letter->refresh();

        $this->assertSame(IncomingLetter::STATUS_MENUNGGU_PEMERIKSAAN, $letter->status);
        $this->assertSame(2, $letter->statusHistories()->count());

        $this->actingAs($admin)
            ->get(route('incoming-letters.edit', $letter))
            ->assertUnprocessable();

        $updatePayload = $this->letterPayload();
        $updatePayload['subject'] = 'Perubahan yang harus ditolak';
        unset($updatePayload['document']);

        $this->actingAs($admin)
            ->put(route('incoming-letters.update', $letter), $updatePayload)
            ->assertUnprocessable();

        $this->assertSame('Pencatatan history surat', $letter->fresh()->subject);

        $this->actingAs($pimpinan)
            ->get(route('incoming-letters.show', $letter))
            ->assertOk()
            ->assertSee('data-testid="incoming-letter-review-link"', false);

        $this->actingAs($pimpinan)
            ->get(route('incoming-letters.review.create', $letter))
            ->assertOk()
            ->assertViewIs('incoming-letters.review')
            ->assertSee('data-testid="incoming-letter-review-form"', false);

        $this->actingAs($pimpinan)
            ->post(route('incoming-letters.review.store', $letter), [
                'destination_division_id' => $destinationDivision->id,
                'review_note' => '',
            ])
            ->assertRedirect(route('incoming-letters.show', $letter))
            ->assertSessionHas('success');

        $review = $letter->review()->firstOrFail();
        $letter->refresh();

        $this->assertSame($pimpinan->id, $review->reviewed_by);
        $this->assertNotNull($review->reviewed_at);
        $this->assertNull($review->review_note);
        $this->assertSame($destinationDivision->id, $letter->destination_division_id);
        $this->assertSame(IncomingLetter::STATUS_SELESAI, $letter->status);
        $this->assertSame(3, $letter->statusHistories()->count());

        $this->actingAs($pimpinan)
            ->post(route('incoming-letters.review.store', $letter), [
                'destination_division_id' => $destinationDivision->id,
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('incoming_letter_reviews', 1);
        $this->assertSame(3, $letter->statusHistories()->count());

        $this->actingAs($pimpinan)
            ->get(route('incoming-letters.show', $letter))
            ->assertOk()
            ->assertSee('Hasil Pemeriksaan')
            ->assertSee('Tidak ada catatan.')
            ->assertSee('Surat diperiksa dan diteruskan ke Divisi Pemasaran')
            ->assertSee('Selesai')
            ->assertSee($destinationDivision->name);

        $this->actingAs($pimpinan)
            ->get(route('incoming-letters.index', [
                'status' => IncomingLetter::STATUS_SELESAI,
                'destination_division_id' => $destinationDivision->id,
            ]))
            ->assertOk()
            ->assertSee($letter->agenda_number)
            ->assertSee('Selesai')
            ->assertSee($destinationDivision->name);
    }

    public function test_inactive_reviewer_is_rejected_by_active_middleware(): void
    {
        $inactivePimpinan = $this->makeUser('pimpinan', null, false);
        $letter = $this->makeLetter($inactivePimpinan);

        $this->actingAs($inactivePimpinan)
            ->get(route('incoming-letters.review.create', $letter))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    private function assertReviewDidNotChangeLetter(IncomingLetter $letter): void
    {
        $this->assertDatabaseMissing('incoming_letter_reviews', [
            'incoming_letter_id' => $letter->id,
        ]);

        $letter->refresh();

        $this->assertSame(IncomingLetter::STATUS_MENUNGGU_PEMERIKSAAN, $letter->status);
        $this->assertNull($letter->destination_division_id);
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

    private function makeUser(
        string $roleSlug,
        ?Division $division = null,
        bool $isActive = true,
    ): User {
        $role = Role::query()->firstOrCreate(
            ['slug' => $roleSlug],
            ['name' => str($roleSlug)->replace('_', ' ')->title()->toString()],
        );

        return User::query()->create([
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'role_id' => $role->id,
            'division_id' => $division?->id,
            'is_active' => $isActive,
        ]);
    }

    private function makeLetter(
        User $creator,
        string $status = IncomingLetter::STATUS_MENUNGGU_PEMERIKSAAN,
    ): IncomingLetter {
        return IncomingLetter::query()->create([
            'agenda_number' => 'AGD-'.fake()->unique()->numerify('####'),
            'letter_number' => '001/RS/VIII/2026',
            'sender_name' => 'Instansi Pengirim',
            'addressed_to' => 'Radar Surat',
            'letter_date' => '2026-08-01',
            'received_date' => '2026-08-03',
            'received_via' => 'fisik',
            'subject' => 'Permohonan pemeriksaan surat',
            'priority' => 'biasa',
            'document_path' => 'incoming-letters/2026/surat.pdf',
            'original_document_name' => 'surat.pdf',
            'document_mime_type' => 'application/pdf',
            'document_size' => 1024,
            'status' => $status,
            'created_by' => $creator->id,
            'submitted_for_review_at' => $status === IncomingLetter::STATUS_MENUNGGU_PEMERIKSAAN
                ? now()
                : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function letterPayload(): array
    {
        return [
            'agenda_number' => 'AGD-HISTORY-001',
            'letter_number' => '001/RS/VIII/2026',
            'sender_name' => 'Instansi Pengirim',
            'addressed_to' => 'Radar Surat',
            'letter_date' => '2026-08-01',
            'received_date' => '2026-08-03',
            'received_via' => 'fisik',
            'subject' => 'Pencatatan history surat',
            'priority' => 'biasa',
            'destination_division_id' => null,
            'document' => UploadedFile::fake()->create('surat.pdf', 20, 'application/pdf'),
        ];
    }
}
