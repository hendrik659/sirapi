<?php

namespace Tests\Feature;

use App\Models\Division;
use App\Models\InternshipCertificate;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class InternshipCertificateViewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    public function test_index_has_final_toolbar_columns_actions_and_hides_internal_code(): void
    {
        $manager = $this->makeManager();
        $certificate = $this->makeCertificate($manager, [
            'archive_code' => 'SERT-2026-014',
            'participant_name' => 'Ahmad Fajar',
        ]);

        $this->actingAs($manager)
            ->get(route('dashboard.certificates.index'))
            ->assertOk()
            ->assertViewIs('certificates.index')
            ->assertSee('Arsip Sertifikat')
            ->assertSee('Daftar arsip sertifikat peserta magang/PKL.')
            ->assertSee('Cari nama peserta, institusi, atau program studi/jurusan...')
            ->assertSee('Semua Tahun')
            ->assertSee('Nama Peserta')
            ->assertSee('Institusi')
            ->assertSee('Program Studi / Jurusan')
            ->assertSee('Periode')
            ->assertSee('Ahmad Fajar')
            ->assertSee('01 Mei 2026')
            ->assertSee('31 Juli 2026')
            ->assertSee(route('dashboard.certificates.show', $certificate), false)
            ->assertSee('<i class="fa-solid fa-eye"', false)
            ->assertSee('<span>Detail</span>', false)
            ->assertDontSee('data-testid="certificate-edit-link"', false)
            ->assertDontSee('<span>Edit</span>', false)
            ->assertDontSee('Kode Sistem')
            ->assertDontSee('SERT-2026-014')
            ->assertDontSee($certificate->document_path)
            ->assertDontSee('Delete')
            ->assertDontSee('Archive')
            ->assertDontSee('Approve');
    }

    public function test_create_and_edit_forms_have_accessible_fields_and_client_preview_hooks(): void
    {
        $manager = $this->makeManager();
        $certificate = $this->makeCertificate($manager);

        $create = $this->actingAs($manager)
            ->get(route('dashboard.certificates.create'))
            ->assertOk()
            ->assertViewIs('certificates.form')
            ->assertSee('Tambah Sertifikat')
            ->assertSee('Simpan')
            ->assertSee('data-certificate-document', false)
            ->assertSee('data-certificate-document-preview-area', false)
            ->assertSee('class="rs-document-form-layout"', false)
            ->assertSee('class="col-12 col-lg-7"', false)
            ->assertSee('class="col-12 col-lg-5"', false)
            ->assertSee('class="rs-document-preview-sticky"', false)
            ->assertSee('Belum ada dokumen dipilih. Pilih file untuk melihat preview.')
            ->assertSee('PDF, JPG, JPEG, atau PNG. Maksimal 5 MB.')
            ->assertSee('action="'.route('dashboard.certificates.store').'"', false);

        foreach (['participant_name', 'institution_name', 'major_name', 'start_date', 'end_date', 'document'] as $field) {
            $create->assertSee('name="'.$field.'"', false);
        }

        $create->assertDontSee('name="archive_code"', false);

        $this->actingAs($manager)
            ->get(route('dashboard.certificates.edit', $certificate))
            ->assertOk()
            ->assertViewIs('certificates.form')
            ->assertSee('Edit Sertifikat')
            ->assertSee('Simpan Perubahan')
            ->assertSee('Preview Dokumen')
            ->assertSee($certificate->original_document_name)
            ->assertSee('Kosongkan dokumen jika tidak ingin mengganti file.')
            ->assertSee('data="'.route('dashboard.certificates.preview', $certificate).'"', false)
            ->assertSee('title="Preview '.$certificate->original_document_name.'"', false)
            ->assertDontSee($certificate->document_path);
    }

    public function test_global_document_form_layout_and_preview_scripts_keep_responsive_sticky_and_blob_lifecycle(): void
    {
        $css = File::get(resource_path('css/app.css'));

        $this->assertMatchesRegularExpression(
            '/\.rs-document-preview-sticky\s*\{[^}]*position:\s*static;/s',
            $css,
        );
        $this->assertMatchesRegularExpression(
            '/@media \(min-width:\s*992px\)\s*\{.*?\.rs-document-preview-sticky\s*\{[^}]*position:\s*sticky;[^}]*top:\s*1\.25rem;/s',
            $css,
        );
        $this->assertStringContainsString('height: clamp(32rem, 68vh, 42rem);', $css);

        foreach ([
            'incoming-letter-preview.js',
            'outgoing-letter-preview.js',
            'certificate-preview.js',
        ] as $script) {
            $javascript = File::get(resource_path('js/'.$script));

            $this->assertStringContainsString('URL.createObjectURL', $javascript);
            $this->assertStringContainsString('URL.revokeObjectURL', $javascript);
            $this->assertStringContainsString("'application/pdf', 'image/jpeg', 'image/png'", $javascript);
            $this->assertStringContainsString('initialState', $javascript);
        }
    }

    public function test_detail_displays_internal_code_metadata_and_private_preview_without_path_leak(): void
    {
        $manager = $this->makeManager();
        $certificate = $this->makeCertificate($manager, [
            'archive_code' => 'SERT-2026-014',
            'participant_name' => 'Ahmad Fajar',
        ]);

        $this->actingAs($manager)
            ->get(route('dashboard.certificates.show', $certificate))
            ->assertOk()
            ->assertViewIs('certificates.show')
            ->assertSee('Detail Sertifikat')
            ->assertSee('Kode Sistem')
            ->assertSee('SERT-2026-014')
            ->assertSee('Ahmad Fajar')
            ->assertSee('Universitas Brawijaya')
            ->assertSee('Ilmu Komunikasi')
            ->assertSee($manager->name)
            ->assertSee('data-testid="certificate-preview"', false)
            ->assertSee(route('dashboard.certificates.preview', $certificate), false)
            ->assertSee(route('dashboard.certificates.download', $certificate), false)
            ->assertSee('data-testid="certificate-edit-link"', false)
            ->assertDontSee($certificate->document_path)
            ->assertDontSee('/storage/', false);
    }

    public function test_read_only_roles_never_see_create_or_edit_actions(): void
    {
        $manager = $this->makeManager();
        $certificate = $this->makeCertificate($manager);

        foreach (['admin_surat', 'pimpinan'] as $roleSlug) {
            $reader = $this->makeUser($roleSlug);

            $this->actingAs($reader)
                ->get(route('dashboard.certificates.index'))
                ->assertOk()
                ->assertDontSee('data-testid="certificate-create-link"', false)
                ->assertDontSee('data-testid="certificate-edit-link"', false)
                ->assertSee('<span>Detail</span>', false);
            $this->actingAs($reader)
                ->get(route('dashboard.certificates.show', $certificate))
                ->assertOk()
                ->assertDontSee('data-testid="certificate-edit-link"', false)
                ->assertSee('Preview')
                ->assertSee('Download');
        }
    }

    public function test_empty_state_only_gives_create_cta_to_sdm_division_head(): void
    {
        $manager = $this->makeManager();
        $admin = $this->makeUser('admin_surat');

        $this->actingAs($manager)
            ->get(route('dashboard.certificates.index'))
            ->assertOk()
            ->assertSee('Belum ada Sertifikat')
            ->assertSee('Arsip sertifikat peserta akan tampil di sini.')
            ->assertSee('Tambah Sertifikat');
        $this->actingAs($admin)
            ->get(route('dashboard.certificates.index'))
            ->assertOk()
            ->assertSee('Belum ada Sertifikat')
            ->assertSee('Arsip sertifikat peserta akan tampil di sini.')
            ->assertDontSee('Tambah Sertifikat');
    }

    public function test_sidebar_visibility_and_active_state_follow_certificate_access_policy(): void
    {
        $sdm = $this->makeDivision('SDM & Umum', 'SDM');
        $otherDivision = $this->makeDivision('Redaksi', 'RED');
        $allowed = [
            $this->makeUser('admin_surat'),
            $this->makeUser('pimpinan'),
            $this->makeUser('ketua_divisi', $sdm),
        ];

        foreach ($allowed as $user) {
            $this->actingAs($user)
                ->get(route('dashboard.certificates.index'))
                ->assertOk()
                ->assertSee('data-testid="certificate-menu-desktop"', false)
                ->assertSee('data-testid="certificate-menu-mobile"', false)
                ->assertSee('nav-link rs-nav-link active', false);
        }

        foreach ([
            $this->makeUser('ketua_divisi', $otherDivision),
            $this->makeUser('anggota_divisi', $otherDivision),
            $this->makeUser('anggota_divisi', $sdm),
        ] as $user) {
            $this->actingAs($user)
                ->get(route('dashboard'))
                ->assertOk()
                ->assertDontSee('data-testid="certificate-menu-desktop"', false)
                ->assertDontSee('data-testid="certificate-menu-mobile"', false);
        }
    }

    private function makeManager(): User
    {
        return $this->makeUser('ketua_divisi', $this->makeDivision('SDM & Umum', 'SDM'));
    }

    private function makeDivision(string $name, string $code): Division
    {
        return Division::query()->firstOrCreate(
            ['code' => $code],
            ['name' => $name, 'is_active' => true],
        );
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

    private function makeCertificate(User $creator, array $overrides = []): InternshipCertificate
    {
        $path = 'internship-certificates/2026/'.Str::uuid().'.pdf';
        Storage::disk('local')->put($path, '%PDF-1.4 certificate');

        return InternshipCertificate::query()->create(array_merge([
            'archive_code' => 'SERT-2026-001',
            'participant_name' => 'Peserta Magang',
            'institution_name' => 'Universitas Brawijaya',
            'major_name' => 'Ilmu Komunikasi',
            'start_date' => '2026-05-01',
            'end_date' => '2026-07-31',
            'document_path' => $path,
            'original_document_name' => 'sertifikat-final.pdf',
            'document_mime_type' => 'application/pdf',
            'document_size' => Storage::disk('local')->size($path),
            'created_by' => $creator->id,
        ], $overrides));
    }
}
