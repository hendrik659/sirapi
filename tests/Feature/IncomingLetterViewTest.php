<?php

namespace Tests\Feature;

use App\Models\Division;
use App\Models\IncomingLetter;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class IncomingLetterViewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('incoming-letters.index'))
            ->assertRedirect(route('login'));
    }

    public function test_active_user_can_open_index_and_detail(): void
    {
        $user = $this->makeUser('anggota_divisi', 'Anggota Divisi');
        $letter = $this->makeLetter($user);

        $this->actingAs($user)
            ->get(route('incoming-letters.index'))
            ->assertOk()
            ->assertViewIs('incoming-letters.index');

        $this->actingAs($user)
            ->get(route('incoming-letters.show', $letter))
            ->assertOk()
            ->assertViewIs('incoming-letters.show');
    }

    public function test_admin_can_open_create_and_edit_form_for_a_new_letter(): void
    {
        $admin = $this->makeUser('admin_surat', 'Admin Surat');
        $letter = $this->makeLetter($admin);

        $createResponse = $this->actingAs($admin)
            ->get(route('incoming-letters.create'));

        $createResponse
            ->assertOk()
            ->assertViewIs('incoming-letters.form')
            ->assertSee('Tambah Surat Masuk')
            ->assertSee('enctype="multipart/form-data"', false)
            ->assertSee('class="rs-document-form-layout"', false)
            ->assertSee('class="col-12 col-lg-7"', false)
            ->assertSee('class="col-12 col-lg-5"', false)
            ->assertSee('class="rs-document-preview-sticky"', false)
            ->assertSee('data-document-preview-area', false)
            ->assertSee('Tujuan pada Surat')
            ->assertSee('(Opsional)')
            ->assertSee('Belum ada dokumen dipilih. Pilih file untuk melihat preview.');

        preg_match('/<input\b[^>]*\bid="addressed_to"[^>]*>/s', $createResponse->getContent(), $addressedToInput);
        $this->assertNotEmpty($addressedToInput);
        $this->assertStringNotContainsString('required', $addressedToInput[0]);

        $this->actingAs($admin)
            ->get(route('incoming-letters.edit', $letter))
            ->assertOk()
            ->assertViewIs('incoming-letters.form')
            ->assertSee('Edit Surat Masuk')
            ->assertSee($letter->agenda_number)
            ->assertSee($letter->original_document_name)
            ->assertSee('data="'.route('incoming-letters.preview', $letter).'"', false)
            ->assertSee('data-incoming-letter-document', false)
            ->assertSee('data-document-preview-area', false)
            ->assertSee('title="Preview '.$letter->original_document_name.'"', false)
            ->assertDontSee($letter->document_path);
    }

    public function test_non_admin_is_forbidden_from_create_and_edit(): void
    {
        $user = $this->makeUser('anggota_divisi', 'Anggota Divisi');
        $letter = $this->makeLetter($user);

        $this->actingAs($user)
            ->get(route('incoming-letters.create'))
            ->assertForbidden();

        $this->actingAs($user)
            ->get(route('incoming-letters.edit', $letter))
            ->assertForbidden();
    }

    public function test_index_displays_incoming_letter_data_and_public_actions(): void
    {
        $user = $this->makeUser('anggota_divisi', 'Anggota Divisi');
        $division = $this->makeDivision();
        $letter = $this->makeLetter($user, [
            'agenda_number' => 'AGD-VIEW-001',
            'letter_number' => '001/SM/VIII/2026',
            'sender_name' => 'Kantor Pajak Kediri',
            'subject' => 'Undangan Rapat Koordinasi',
            'priority' => 'segera',
            'destination_division_id' => $division->id,
        ]);

        $this->actingAs($user)
            ->get(route('incoming-letters.index'))
            ->assertOk()
            ->assertSee('AGD-VIEW-001')
            ->assertSee('001/SM/VIII/2026')
            ->assertSee('Kantor Pajak Kediri')
            ->assertSee('Undangan Rapat Koordinasi')
            ->assertSee($division->name)
            ->assertSee('Segera')
            ->assertSee('Baru Diterima')
            ->assertSee('href="'.route('incoming-letters.show', $letter).'"', false)
            ->assertSee('data-testid="incoming-letter-detail-link"', false)
            ->assertDontSee('href="'.route('incoming-letters.preview', $letter).'"', false)
            ->assertDontSee('href="'.route('incoming-letters.download', $letter).'"', false)
            ->assertDontSee('data-testid="incoming-letter-utility-menu"', false)
            ->assertDontSee('fa-ellipsis-vertical', false)
            ->assertDontSee('Hapus');
    }

    public function test_status_badges_use_the_same_final_mapping_on_index_dashboard_and_report(): void
    {
        $admin = $this->makeUser('admin_surat', 'Admin Surat');

        foreach ([
            IncomingLetter::STATUS_BARU_DITERIMA => ['Baru Diterima', 'new', '2026-08-01'],
            IncomingLetter::STATUS_MENUNGGU_PEMERIKSAAN => ['Menunggu Pemeriksaan', 'waiting', '2026-08-02'],
            IncomingLetter::STATUS_SELESAI => ['Selesai', 'done', '2026-08-03'],
        ] as $status => [$label, $variant, $receivedDate]) {
            $this->makeLetter($admin, [
                'agenda_number' => 'AGD-STATUS-'.strtoupper($variant),
                'status' => $status,
                'received_date' => $receivedDate,
            ]);
        }

        foreach ([
            route('incoming-letters.index'),
            route('dashboard'),
            route('reports.incoming-letters.index'),
        ] as $url) {
            $response = $this->actingAs($admin)->get($url)->assertOk();

            foreach ([
                IncomingLetter::STATUS_BARU_DITERIMA => ['Baru Diterima', 'new'],
                IncomingLetter::STATUS_MENUNGGU_PEMERIKSAAN => ['Menunggu Pemeriksaan', 'waiting'],
                IncomingLetter::STATUS_SELESAI => ['Selesai', 'done'],
            ] as $status => [$label, $variant]) {
                $response
                    ->assertSee('class="badge rs-status-badge rs-status-'.$variant.'"', false)
                    ->assertSee('data-incoming-letter-status="'.$status.'"', false)
                    ->assertSee($label);
            }
        }
    }

    public function test_detail_displays_letter_data_preview_and_download_routes(): void
    {
        $user = $this->makeUser('anggota_divisi', 'Anggota Divisi');
        $division = $this->makeDivision();
        $letter = $this->makeLetter($user, [
            'agenda_number' => 'AGD-DETAIL-001',
            'letter_number' => '010/DETAIL/VIII/2026',
            'sender_name' => 'Pemerintah Kota Kediri',
            'addressed_to' => 'Pimpinan Radar Kediri',
            'subject' => 'Detail Surat Masuk',
            'destination_division_id' => $division->id,
        ]);

        $this->actingAs($user)
            ->get(route('incoming-letters.show', $letter))
            ->assertOk()
            ->assertSee('AGD-DETAIL-001')
            ->assertSee('010/DETAIL/VIII/2026')
            ->assertSee('Pemerintah Kota Kediri')
            ->assertSee('Pimpinan Radar Kediri')
            ->assertSee('Detail Surat Masuk')
            ->assertSee($division->name)
            ->assertSee($user->name)
            ->assertSee('data="'.route('incoming-letters.preview', $letter).'"', false)
            ->assertSee('href="'.route('incoming-letters.download', $letter).'"', false)
            ->assertSee('data-testid="incoming-letter-preview"', false)
            ->assertSee('data-testid="incoming-letter-preview-link"', false)
            ->assertSee('data-testid="incoming-letter-download-link"', false);
    }

    public function test_create_button_is_only_visible_to_admin_surat(): void
    {
        $admin = $this->makeUser('admin_surat', 'Admin Surat');
        $reader = $this->makeUser('anggota_divisi', 'Anggota Divisi');

        $this->actingAs($admin)
            ->get(route('incoming-letters.index'))
            ->assertSee('data-testid="incoming-letter-create-link"', false);

        $this->actingAs($reader)
            ->get(route('incoming-letters.index'))
            ->assertDontSee('data-testid="incoming-letter-create-link"', false);
    }

    public function test_admin_sees_edit_and_submit_actions_for_new_letter(): void
    {
        $admin = $this->makeUser('admin_surat', 'Admin Surat');
        $letter = $this->makeLetter($admin);

        $this->actingAs($admin)
            ->get(route('incoming-letters.index'))
            ->assertOk()
            ->assertSeeInOrder([
                'data-testid="incoming-letter-detail-link"',
                'data-testid="incoming-letter-edit-link"',
                'data-testid="incoming-letter-submit-form"',
            ], false)
            ->assertSee('data-testid="incoming-letter-submit-button"', false)
            ->assertSee('class="btn btn-sm btn-primary d-inline-flex align-items-center gap-1"', false)
            ->assertSee('<span>Detail</span>', false)
            ->assertSee('<span>Edit</span>', false)
            ->assertSee('<span>Kirim Pemeriksaan</span>', false)
            ->assertDontSee('href="'.route('incoming-letters.preview', $letter).'"', false)
            ->assertDontSee('href="'.route('incoming-letters.download', $letter).'"', false)
            ->assertDontSee('data-testid="incoming-letter-utility-menu"', false)
            ->assertDontSee('fa-ellipsis-vertical', false);

        $this->actingAs($admin)
            ->get(route('incoming-letters.show', $letter))
            ->assertOk()
            ->assertSee('data-testid="incoming-letter-edit-link"', false)
            ->assertSee('data-testid="incoming-letter-submit-form"', false)
            ->assertSee('action="'.route('incoming-letters.submit-for-review', $letter).'"', false)
            ->assertSee('data-confirmation', false)
            ->assertSee('data-confirmation-title="Kirim untuk Pemeriksaan"', false)
            ->assertSee('Surat akan dikirim kepada pihak pemeriksa dan tidak dapat diedit kembali oleh Admin.');
    }

    public function test_non_admin_does_not_see_edit_or_submit_actions(): void
    {
        $reader = $this->makeUser('anggota_divisi', 'Anggota Divisi');
        $letter = $this->makeLetter($reader);

        $this->actingAs($reader)
            ->get(route('incoming-letters.show', $letter))
            ->assertOk()
            ->assertDontSee('data-testid="incoming-letter-edit-link"', false)
            ->assertDontSee('data-testid="incoming-letter-submit-form"', false);

        $this->actingAs($reader)
            ->get(route('incoming-letters.index'))
            ->assertOk()
            ->assertSee('data-testid="incoming-letter-detail-link"', false)
            ->assertDontSee('data-testid="incoming-letter-utility-menu"', false)
            ->assertDontSee('data-testid="incoming-letter-edit-link"', false)
            ->assertDontSee('data-testid="incoming-letter-submit-form"', false);
    }

    public function test_edit_and_submit_actions_disappear_after_review_submission(): void
    {
        $admin = $this->makeUser('admin_surat', 'Admin Surat');
        $letter = $this->makeLetter($admin, [
            'status' => IncomingLetter::STATUS_MENUNGGU_PEMERIKSAAN,
            'submitted_for_review_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('incoming-letters.show', $letter))
            ->assertOk()
            ->assertSee('Menunggu Pemeriksaan')
            ->assertDontSee('data-testid="incoming-letter-edit-link"', false)
            ->assertDontSee('data-testid="incoming-letter-submit-form"', false);

        $this->actingAs($admin)
            ->get(route('incoming-letters.index'))
            ->assertOk()
            ->assertSee('data-testid="incoming-letter-detail-link"', false)
            ->assertDontSee('data-testid="incoming-letter-edit-link"', false)
            ->assertDontSee('data-testid="incoming-letter-submit-form"', false)
            ->assertDontSee('href="'.route('incoming-letters.preview', $letter).'"', false)
            ->assertDontSee('href="'.route('incoming-letters.download', $letter).'"', false);
    }

    public function test_priority_options_only_contain_biasa_and_segera(): void
    {
        $admin = $this->makeUser('admin_surat', 'Admin Surat');

        $this->actingAs($admin)
            ->get(route('incoming-letters.create'))
            ->assertOk()
            ->assertSee('<option value="biasa"', false)
            ->assertSee('<option value="segera"', false)
            ->assertDontSee('Pilih Prioritas')
            ->assertDontSee('destination_division_id', false)
            ->assertDontSee('name="status"', false);
    }

    public function test_browser_form_workflow_redirects_back_to_blade_pages(): void
    {
        $admin = $this->makeUser('admin_surat', 'Admin Surat');

        $this->actingAs($admin)
            ->post(route('incoming-letters.store'), $this->letterPayload([
                'agenda_number' => 'AGD-FORM-001',
            ]))
            ->assertRedirect();

        $letter = IncomingLetter::query()->where('agenda_number', 'AGD-FORM-001')->firstOrFail();

        $this->actingAs($admin)
            ->put(route('incoming-letters.update', $letter), $this->letterPayload([
                'agenda_number' => 'AGD-FORM-002',
                'document' => UploadedFile::fake()->create('surat-revisi.pdf', 20, 'application/pdf'),
            ]))
            ->assertRedirect(route('incoming-letters.show', $letter))
            ->assertSessionHas('success', 'Surat Masuk berhasil diperbarui.');

        $this->actingAs($admin)
            ->patch(route('incoming-letters.submit-for-review', $letter))
            ->assertRedirect(route('incoming-letters.show', $letter))
            ->assertSessionHas('success', 'Surat Masuk berhasil dikirim untuk pemeriksaan.');

        $this->assertSame(
            IncomingLetter::STATUS_MENUNGGU_PEMERIKSAAN,
            $letter->fresh()->status,
        );
    }

    public function test_sidebar_displays_active_incoming_letter_menu_on_mobile_and_desktop(): void
    {
        $user = $this->makeUser('anggota_divisi', 'Anggota Divisi');

        $this->actingAs($user)
            ->get(route('incoming-letters.index'))
            ->assertOk()
            ->assertSee('data-testid="incoming-letter-menu-mobile"', false)
            ->assertSee('data-testid="incoming-letter-menu-desktop"', false)
            ->assertSee('href="'.route('incoming-letters.index').'"', false)
            ->assertSee('class="nav-link rs-nav-link active"', false)
            ->assertSee('fa-envelope-open-text', false);
    }

    public function test_index_displays_initial_and_filtered_empty_states(): void
    {
        $user = $this->makeUser('anggota_divisi', 'Anggota Divisi');

        $this->actingAs($user)
            ->get(route('incoming-letters.index'))
            ->assertOk()
            ->assertSee('Belum ada Surat Masuk')
            ->assertSee('Surat masuk yang dicatat akan tampil di sini.');

        $this->actingAs($user)
            ->get(route('incoming-letters.index', ['search' => 'tidak-ada']))
            ->assertOk()
            ->assertSee('Data tidak ditemukan')
            ->assertSee('Tidak ada data yang sesuai dengan pencarian atau filter.')
            ->assertSee('Reset');
    }

    private function makeDivision(): Division
    {
        return Division::query()->create([
            'name' => 'Redaksi',
            'code' => 'RED',
            'is_active' => true,
        ]);
    }

    private function makeUser(string $roleSlug, string $roleName): User
    {
        $role = Role::query()->firstOrCreate(
            ['slug' => $roleSlug],
            ['name' => $roleName],
        );

        return User::query()->create([
            'name' => $roleName,
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'role_id' => $role->id,
            'is_active' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function letterPayload(array $overrides = []): array
    {
        return array_merge([
            'agenda_number' => 'AGD-'.fake()->unique()->numerify('####'),
            'letter_number' => '001/RS/VIII/2026',
            'sender_name' => 'Instansi Pengirim',
            'addressed_to' => 'Radar Surat',
            'letter_date' => '2026-08-01',
            'received_date' => '2026-08-03',
            'received_via' => 'fisik',
            'subject' => 'Undangan Rapat',
            'priority' => 'biasa',
            'destination_division_id' => null,
            'document' => UploadedFile::fake()->create('surat-masuk.pdf', 20, 'application/pdf'),
        ], $overrides);
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
            'letter_number' => null,
            'sender_name' => 'Instansi Pengirim',
            'addressed_to' => 'Radar Surat',
            'letter_date' => '2026-08-01',
            'received_date' => '2026-08-03',
            'received_via' => 'fisik',
            'subject' => 'Undangan Rapat',
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
