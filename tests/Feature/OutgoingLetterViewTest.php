<?php

namespace Tests\Feature;

use App\Models\Division;
use App\Models\OutgoingLetter;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class OutgoingLetterViewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    public function test_all_active_roles_can_open_index_and_detail_with_sidebar_links(): void
    {
        $division = $this->makeDivision();
        $creator = $this->makeUser('anggota_divisi', $division);
        $letter = $this->makeLetter($creator, $division);

        foreach (['admin_surat', 'pimpinan', 'ketua_divisi', 'anggota_divisi'] as $roleSlug) {
            $user = $this->makeUser($roleSlug, $division);

            $this->actingAs($user)
                ->get(route('outgoing-letters.index'))
                ->assertOk()
                ->assertViewIs('outgoing-letters.index')
                ->assertSee('data-testid="outgoing-letter-menu-mobile"', false)
                ->assertSee('data-testid="outgoing-letter-menu-desktop"', false);
            $this->actingAs($user)
                ->get(route('outgoing-letters.show', $letter))
                ->assertOk()
                ->assertViewIs('outgoing-letters.show');
        }
    }

    public function test_create_button_and_form_only_appear_for_authorized_division_roles(): void
    {
        $division = $this->makeDivision();

        foreach (['ketua_divisi', 'anggota_divisi'] as $roleSlug) {
            $user = $this->makeUser($roleSlug, $division);

            $this->actingAs($user)
                ->get(route('outgoing-letters.index'))
                ->assertOk()
                ->assertSee('data-testid="outgoing-letter-create-link"', false);
            $this->actingAs($user)
                ->get(route('outgoing-letters.create'))
                ->assertOk()
                ->assertViewIs('outgoing-letters.form');
        }

        foreach ([$this->makeUser('admin_surat'), $this->makeUser('pimpinan'), $this->makeUser('anggota_divisi')] as $user) {
            $this->actingAs($user)
                ->get(route('outgoing-letters.index'))
                ->assertOk()
                ->assertDontSee('data-testid="outgoing-letter-create-link"', false);
            $this->actingAs($user)
                ->get(route('outgoing-letters.create'))
                ->assertForbidden();
        }
    }

    public function test_form_is_create_only_and_exposes_no_internal_or_edit_fields(): void
    {
        $division = $this->makeDivision();
        $creator = $this->makeUser('ketua_divisi', $division);
        $response = $this->actingAs($creator)
            ->get(route('outgoing-letters.create'))
            ->assertOk()
            ->assertSee('data-outgoing-letter-document', false)
            ->assertSee('class="rs-document-form-layout"', false)
            ->assertSee('class="col-12 col-lg-7"', false)
            ->assertSee('class="col-12 col-lg-5"', false)
            ->assertSee('class="rs-document-preview-sticky"', false)
            ->assertSee('data-outgoing-document-preview-area', false)
            ->assertSee('Belum ada dokumen dipilih. Pilih file untuk melihat preview.')
            ->assertSee('Simpan')
            ->assertSee('langsung menjadi data hanya-baca');

        foreach (['letter_number', 'letter_date', 'recipient_name', 'recipient_address', 'subject', 'document'] as $field) {
            $response->assertSee('name="'.$field.'"', false);
        }

        foreach (['reference_code', 'division_id', 'created_by', 'archived_at', 'archived_by', 'status'] as $field) {
            $response->assertDontSee('name="'.$field.'"', false);
        }

        $response
            ->assertDontSee('Simpan Perubahan')
            ->assertDontSee('dokumen lama')
            ->assertDontSee('_method', false);
    }

    public function test_index_has_final_columns_filters_and_read_only_actions_only(): void
    {
        $division = $this->makeDivision();
        $creator = $this->makeUser('anggota_divisi', $division);
        $letter = $this->makeLetter($creator, $division, [
            'reference_code' => 'SK-2026-001',
            'letter_number' => '001/RED/VIII/2026',
            'recipient_name' => 'Penerima Final',
            'subject' => 'Perihal Final',
        ]);

        $response = $this->actingAs($creator)
            ->get(route('outgoing-letters.index', [
                'search' => 'Final',
                'division_id' => $division->id,
                'letter_date' => '2026-08-07',
            ]))
            ->assertOk();

        foreach (['Kode Sistem', 'Nomor Surat', 'Tanggal Surat', 'Tujuan', 'Perihal', 'Divisi', 'Aksi'] as $heading) {
            $response->assertSee($heading);
        }

        $response
            ->assertSee($letter->reference_code)
            ->assertSee(route('outgoing-letters.show', $letter), false)
            ->assertSee('data-testid="outgoing-letter-detail-link"', false)
            ->assertSee('<span>Detail</span>', false)
            ->assertDontSee(route('outgoing-letters.preview', $letter), false)
            ->assertDontSee(route('outgoing-letters.download', $letter), false)
            ->assertDontSee('data-testid="outgoing-letter-utility-menu"', false)
            ->assertDontSee('data-rs-table-dropdown', false)
            ->assertDontSee('fa-ellipsis-vertical', false)
            ->assertSee('name="division_id"', false)
            ->assertSee('name="letter_date"', false)
            ->assertDontSee('name="archive_state"', false)
            ->assertDontSee('Status Arsip')
            ->assertDontSee('Belum Diarsipkan')
            ->assertDontSee('Sudah Diarsipkan')
            ->assertDontSee('data-testid="outgoing-letter-edit-link"', false)
            ->assertDontSee('Arsipkan')
            ->assertDontSee('Delete');
    }

    public function test_detail_is_final_read_only_private_and_displays_creation_timeline(): void
    {
        $division = $this->makeDivision();
        $creator = $this->makeUser('anggota_divisi', $division);
        $letter = $this->makeLetter($creator, $division, ['reference_code' => 'SK-2026-001']);
        $letter->histories()->create([
            'activity' => 'Surat Keluar dicatat',
            'notes' => null,
            'changed_by' => $creator->id,
        ]);

        $this->actingAs($creator)
            ->get(route('outgoing-letters.show', $letter))
            ->assertOk()
            ->assertSee('Kode Sistem SK-2026-001')
            ->assertSee('data-testid="outgoing-letter-preview"', false)
            ->assertSee(route('outgoing-letters.preview', $letter), false)
            ->assertSee(route('outgoing-letters.download', $letter), false)
            ->assertSee('data-testid="outgoing-letter-preview-link"', false)
            ->assertSee('data-testid="outgoing-letter-download-link"', false)
            ->assertSee('<span>Kembali</span>', false)
            ->assertSee('<span>Preview</span>', false)
            ->assertSee('<span>Download</span>', false)
            ->assertSee('Surat Keluar dicatat')
            ->assertSee($creator->name)
            ->assertDontSee($letter->document_path)
            ->assertDontSee('/storage/', false)
            ->assertDontSee('Edit')
            ->assertDontSee('Arsipkan')
            ->assertDontSee('Diarsipkan Oleh')
            ->assertDontSee('Tanggal Arsip');
    }

    public function test_browser_store_redirects_to_final_detail(): void
    {
        $division = $this->makeDivision();
        $creator = $this->makeUser('anggota_divisi', $division);

        $this->actingAs($creator)
            ->post(route('outgoing-letters.store'), [
                'letter_number' => '001/FINAL/2026',
                'letter_date' => '2026-08-07',
                'recipient_name' => 'Penerima Final',
                'recipient_address' => null,
                'subject' => 'Dokumen langsung final',
                'document' => UploadedFile::fake()->create('final.pdf', 100, 'application/pdf'),
            ])
            ->assertRedirect(route('outgoing-letters.show', OutgoingLetter::query()->firstOrFail()))
            ->assertSessionHas('success', 'Surat Keluar berhasil disimpan.');

        $letter = OutgoingLetter::query()->firstOrFail();
        $this->actingAs($creator)
            ->get(route('outgoing-letters.show', $letter))
            ->assertOk()
            ->assertSee('Surat Keluar dicatat')
            ->assertDontSee('Edit')
            ->assertDontSee('Arsipkan');
    }

    public function test_index_displays_initial_and_filtered_empty_states(): void
    {
        $user = $this->makeUser('pimpinan');

        $this->actingAs($user)
            ->get(route('outgoing-letters.index'))
            ->assertOk()
            ->assertSee('Belum ada Surat Keluar')
            ->assertSee('Surat keluar yang dicatat akan tampil di sini.');
        $this->actingAs($user)
            ->get(route('outgoing-letters.index', ['search' => 'tidak-ada']))
            ->assertOk()
            ->assertSee('Data tidak ditemukan')
            ->assertSee('Tidak ada data yang sesuai dengan pencarian atau filter.')
            ->assertSee('Reset');
    }

    private function makeDivision(string $name = 'Redaksi', string $code = 'RED'): Division
    {
        return Division::query()->create([
            'name' => $name,
            'code' => $code,
            'is_active' => true,
        ]);
    }

    private function makeUser(string $roleSlug, ?Division $division = null): User
    {
        $role = Role::query()->firstOrCreate(
            ['slug' => $roleSlug],
            ['name' => Str::headline($roleSlug)],
        );

        return User::query()->create([
            'name' => Str::headline($roleSlug).' '.Str::random(5),
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
    private function makeLetter(User $creator, Division $division, array $overrides = []): OutgoingLetter
    {
        $path = 'outgoing-letters/2026/'.Str::uuid().'.pdf';
        Storage::disk('local')->put($path, '%PDF-1.4 outgoing letter');

        return OutgoingLetter::query()->create(array_merge([
            'reference_code' => 'REF-'.Str::uuid(),
            'letter_number' => 'NO-'.Str::uuid(),
            'letter_date' => '2026-08-07',
            'recipient_name' => 'Penerima Surat',
            'recipient_address' => 'Jalan Penerima Nomor 1',
            'subject' => 'Surat Keluar Final',
            'division_id' => $division->id,
            'created_by' => $creator->id,
            'document_path' => $path,
            'original_document_name' => 'surat-final.pdf',
            'document_mime_type' => 'application/pdf',
            'document_size' => Storage::disk('local')->size($path),
        ], $overrides));
    }
}
