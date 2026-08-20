<?php

namespace Tests\Feature;

use App\Models\Division;
use App\Models\InternshipCertificate;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class InternshipCertificateWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    public function test_table_model_relation_casts_and_hidden_path_match_the_final_scope(): void
    {
        $this->assertTrue(Schema::hasColumns('internship_certificates', [
            'id',
            'archive_code',
            'participant_name',
            'institution_name',
            'major_name',
            'start_date',
            'end_date',
            'document_path',
            'original_document_name',
            'document_mime_type',
            'document_size',
            'created_by',
            'created_at',
            'updated_at',
        ]));

        foreach (['status', 'archived_at', 'archived_by', 'approved_by', 'approved_at', 'deleted_by'] as $column) {
            $this->assertFalse(Schema::hasColumn('internship_certificates', $column));
        }

        $creator = $this->makeManager();
        $certificate = $this->makeCertificate($creator, [
            'start_date' => '2026-05-01',
            'end_date' => '2026-07-31',
            'document_size' => 4096,
        ]);

        $this->assertTrue($certificate->creator->is($creator));
        $this->assertSame('2026-05-01', $certificate->start_date->format('Y-m-d'));
        $this->assertSame('2026-07-31', $certificate->end_date->format('Y-m-d'));
        $this->assertIsInt($certificate->document_size);
        $this->assertIsInt($certificate->created_by);
        $this->assertContains('document_path', $certificate->getHidden());
        $this->assertArrayNotHasKey('document_path', $certificate->toArray());
    }

    public function test_store_creates_private_archive_with_creator_metadata_and_concurrency_safe_code(): void
    {
        $manager = $this->makeManager();

        $this->actingAs($manager)
            ->post(route('dashboard.certificates.store'), $this->validPayload([
                'end_date' => '2027-07-31',
                'document' => UploadedFile::fake()->create('Sertifikat Ahmad.pdf', 120, 'application/pdf'),
            ]))
            ->assertRedirect()
            ->assertSessionHas('success', 'Sertifikat berhasil ditambahkan.');

        $certificate = InternshipCertificate::query()->firstOrFail();

        $this->assertSame('SERT-2027-001', $certificate->archive_code);
        $this->assertSame($manager->id, $certificate->created_by);
        $this->assertStringStartsWith('internship-certificates/2027/', $certificate->document_path);
        $this->assertMatchesRegularExpression('#/[0-9a-f-]{36}\.pdf\z#', $certificate->document_path);
        $this->assertStringNotContainsString('Ahmad', $certificate->document_path);
        $this->assertSame('Sertifikat Ahmad.pdf', $certificate->original_document_name);
        $this->assertSame('application/pdf', $certificate->document_mime_type);
        Storage::disk('local')->assertExists($certificate->document_path);
    }

    public function test_archive_code_uses_database_id_beyond_three_digits_and_remains_unique(): void
    {
        $manager = $this->makeManager();
        InternshipCertificate::query()->forceCreate($this->certificateAttributes($manager, [
            'id' => 999,
            'archive_code' => 'SERT-2026-999',
        ]));

        $this->actingAs($manager)
            ->post(route('dashboard.certificates.store'), $this->validPayload([
                'end_date' => '2028-01-31',
            ]))
            ->assertRedirect();

        $certificate = InternshipCertificate::query()->findOrFail(1000);
        $this->assertSame('SERT-2028-1000', $certificate->archive_code);

        $this->expectException(QueryException::class);
        InternshipCertificate::query()->create($this->certificateAttributes($manager, [
            'archive_code' => 'SERT-2028-1000',
        ]));
    }

    public function test_required_metadata_dates_and_document_are_validated(): void
    {
        $manager = $this->makeManager();

        foreach (['participant_name', 'institution_name', 'major_name', 'start_date', 'end_date', 'document'] as $field) {
            $payload = $this->validPayload();
            unset($payload[$field]);

            $this->actingAs($manager)
                ->post(route('dashboard.certificates.store'), $payload)
                ->assertSessionHasErrors($field);
        }

        $this->actingAs($manager)
            ->post(route('dashboard.certificates.store'), $this->validPayload([
                'start_date' => '2026-08-01',
                'end_date' => '2026-07-31',
            ]))
            ->assertSessionHasErrors('end_date');

        $this->assertDatabaseCount('internship_certificates', 0);
    }

    public function test_supported_document_formats_are_accepted(): void
    {
        $manager = $this->makeManager();
        $documents = [
            UploadedFile::fake()->create('sertifikat.pdf', 50, 'application/pdf'),
            UploadedFile::fake()->create('sertifikat.jpg', 50, 'image/jpeg'),
            UploadedFile::fake()->create('sertifikat.jpeg', 50, 'image/jpeg'),
            UploadedFile::fake()->create('sertifikat.png', 50, 'image/png'),
        ];

        foreach ($documents as $index => $document) {
            $this->actingAs($manager)
                ->post(route('dashboard.certificates.store'), $this->validPayload([
                    'participant_name' => 'Peserta '.$index,
                    'document' => $document,
                ]))
                ->assertSessionDoesntHaveErrors();
        }

        $this->assertDatabaseCount('internship_certificates', 4);
    }

    public function test_invalid_or_oversized_document_is_rejected(): void
    {
        $manager = $this->makeManager();

        foreach ([
            UploadedFile::fake()->create('sertifikat.docx', 50, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
            UploadedFile::fake()->create('sertifikat.pdf', 5121, 'application/pdf'),
        ] as $document) {
            $this->actingAs($manager)
                ->post(route('dashboard.certificates.store'), $this->validPayload(['document' => $document]))
                ->assertSessionHasErrors('document');
        }

        $this->assertDatabaseCount('internship_certificates', 0);
    }

    public function test_failed_store_removes_new_file_and_rolls_back_record(): void
    {
        $manager = $this->makeManager();
        InternshipCertificate::updating(function (): void {
            throw new RuntimeException('Simulasi kegagalan archive code.');
        });

        try {
            $response = $this->actingAs($manager)
                ->post(route('dashboard.certificates.store'), $this->validPayload());
        } finally {
            InternshipCertificate::flushEventListeners();
        }

        $response->assertServerError();
        $this->assertDatabaseCount('internship_certificates', 0);
        $this->assertSame([], Storage::disk('local')->allFiles('internship-certificates'));
    }

    public function test_update_without_document_keeps_old_file_and_recalculates_code_year(): void
    {
        $manager = $this->makeManager();
        $certificate = $this->makeCertificate($manager);
        $oldPath = $certificate->document_path;

        $this->actingAs($manager)
            ->put(route('dashboard.certificates.update', $certificate), $this->validMetadata([
                'participant_name' => 'Nama Baru',
                'end_date' => '2029-07-31',
            ]))
            ->assertRedirect(route('dashboard.certificates.show', $certificate))
            ->assertSessionHas('success', 'Sertifikat berhasil diperbarui.');

        $certificate->refresh();
        $this->assertSame('Nama Baru', $certificate->participant_name);
        $this->assertSame("SERT-2029-{$this->minimumThreeDigits($certificate->id)}", $certificate->archive_code);
        $this->assertSame($oldPath, $certificate->document_path);
        Storage::disk('local')->assertExists($oldPath);
    }

    public function test_update_with_document_replaces_file_only_after_success(): void
    {
        $manager = $this->makeManager();
        $certificate = $this->makeCertificate($manager);
        $oldPath = $certificate->document_path;

        $this->actingAs($manager)
            ->put(route('dashboard.certificates.update', $certificate), $this->validMetadata([
                'document' => UploadedFile::fake()->create('pengganti.png', 75, 'image/png'),
            ]))
            ->assertRedirect(route('dashboard.certificates.show', $certificate));

        $certificate->refresh();
        $this->assertNotSame($oldPath, $certificate->document_path);
        $this->assertSame('pengganti.png', $certificate->original_document_name);
        $this->assertSame('image/png', $certificate->document_mime_type);
        Storage::disk('local')->assertExists($certificate->document_path);
        Storage::disk('local')->assertMissing($oldPath);
    }

    public function test_failed_update_keeps_old_file_and_removes_unused_new_file(): void
    {
        $manager = $this->makeManager();
        $certificate = $this->makeCertificate($manager);
        $oldPath = $certificate->document_path;
        InternshipCertificate::updating(function (): void {
            throw new RuntimeException('Simulasi kegagalan update.');
        });

        try {
            $response = $this->actingAs($manager)
                ->put(route('dashboard.certificates.update', $certificate), $this->validMetadata([
                    'participant_name' => 'Tidak boleh tersimpan',
                    'document' => UploadedFile::fake()->create('gagal.pdf', 50, 'application/pdf'),
                ]));
        } finally {
            InternshipCertificate::flushEventListeners();
        }

        $response->assertServerError();
        $this->assertSame('Peserta Magang', $certificate->fresh()->participant_name);
        $this->assertSame($oldPath, $certificate->fresh()->document_path);
        Storage::disk('local')->assertExists($oldPath);
        $this->assertCount(1, Storage::disk('local')->allFiles('internship-certificates'));
    }

    public function test_private_preview_download_missing_file_and_unsupported_mime_are_handled(): void
    {
        $manager = $this->makeManager();
        $reader = $this->makeUser('pimpinan');
        $certificate = $this->makeCertificate($manager);

        $this->actingAs($reader)
            ->get(route('dashboard.certificates.preview', $certificate))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertHeaderContains('content-disposition', 'inline');
        $this->actingAs($reader)
            ->get(route('dashboard.certificates.download', $certificate))
            ->assertOk()
            ->assertDownload('sertifikat-final.pdf');

        Storage::disk('local')->delete($certificate->document_path);
        $this->actingAs($reader)->get(route('dashboard.certificates.preview', $certificate))->assertNotFound();
        $this->actingAs($reader)->get(route('dashboard.certificates.download', $certificate))->assertNotFound();

        Storage::disk('local')->put($certificate->document_path, 'unsupported');
        $certificate->update(['document_mime_type' => 'text/plain']);
        $this->actingAs($reader)->get(route('dashboard.certificates.preview', $certificate))->assertStatus(415);
        $this->actingAs($reader)->get(route('dashboard.certificates.download', $certificate))->assertStatus(415);
    }

    public function test_index_search_year_combination_ordering_and_pagination_work(): void
    {
        $manager = $this->makeManager();
        $reader = $this->makeUser('admin_surat');
        $target = $this->makeCertificate($manager, [
            'participant_name' => 'Ahmad Fajar',
            'institution_name' => 'Universitas Brawijaya',
            'major_name' => 'Ilmu Komunikasi',
            'end_date' => '2026-07-31',
        ]);
        $this->makeCertificate($manager, [
            'participant_name' => 'Peserta Tahun Lain',
            'institution_name' => 'Universitas Brawijaya',
            'end_date' => '2025-07-31',
        ]);

        foreach (['Ahmad Fajar', 'Universitas Brawijaya', 'Ilmu Komunikasi'] as $search) {
            $this->actingAs($reader)
                ->get(route('dashboard.certificates.index', ['search' => $search, 'year' => 2026]))
                ->assertOk()
                ->assertViewHas('certificates', fn ($items) => $items->count() === 1 && $items->first()->is($target));
        }

        foreach (range(1, 16) as $index) {
            $this->makeCertificate($manager, [
                'participant_name' => 'Pagination '.$index,
                'end_date' => '2027-'.($index > 9 ? '12' : '11').'-'.str_pad((string) min($index, 28), 2, '0', STR_PAD_LEFT),
            ]);
        }

        $this->actingAs($reader)
            ->get(route('dashboard.certificates.index', ['search' => 'Pagination', 'year' => 2027]))
            ->assertOk()
            ->assertViewHas('certificates', function ($items): bool {
                return $items->count() === 15
                    && $items->total() === 16
                    && $items->first()->end_date->greaterThanOrEqualTo($items->last()->end_date)
                    && str_contains($items->url(2), 'search=Pagination')
                    && str_contains($items->url(2), 'year=2027');
            })
            ->assertViewHas('years', fn ($years) => $years->all() === [2027, 2026, 2025]);
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
        $attributes = $this->certificateAttributes($creator, $overrides);
        Storage::disk('local')->put($attributes['document_path'], '%PDF-1.4 certificate');

        return InternshipCertificate::query()->create($attributes);
    }

    private function certificateAttributes(User $creator, array $overrides = []): array
    {
        return array_merge([
            'archive_code' => 'SERT-'.Str::uuid(),
            'participant_name' => 'Peserta Magang',
            'institution_name' => 'Universitas Brawijaya',
            'major_name' => 'Ilmu Komunikasi',
            'start_date' => '2026-05-01',
            'end_date' => '2026-07-31',
            'document_path' => 'internship-certificates/2026/'.Str::uuid().'.pdf',
            'original_document_name' => 'sertifikat-final.pdf',
            'document_mime_type' => 'application/pdf',
            'document_size' => 100,
            'created_by' => $creator->id,
        ], $overrides);
    }

    private function validMetadata(array $overrides = []): array
    {
        return array_merge([
            'participant_name' => 'Peserta Magang',
            'institution_name' => 'Universitas Brawijaya',
            'major_name' => 'Ilmu Komunikasi',
            'start_date' => '2026-05-01',
            'end_date' => '2026-07-31',
        ], $overrides);
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge($this->validMetadata(), [
            'document' => UploadedFile::fake()->create('sertifikat.pdf', 100, 'application/pdf'),
        ], $overrides);
    }

    private function minimumThreeDigits(int $id): string
    {
        return str_pad((string) $id, 3, '0', STR_PAD_LEFT);
    }
}
