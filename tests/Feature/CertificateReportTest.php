<?php

namespace Tests\Feature;

use App\Models\Division;
use App\Models\InternshipCertificate;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use OpenSpout\Reader\XLSX\Reader;
use Tests\TestCase;

class CertificateReportTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_guest_inactive_and_unauthorized_roles_cannot_access_report_or_export(): void
    {
        foreach ($this->reportRoutes() as $route) {
            $this->get(route($route))->assertRedirect(route('login'));
        }

        $inactive = $this->makeUser('admin_surat', null, false);
        $this->actingAs($inactive)
            ->get(route('reports.certificates.index'))
            ->assertRedirect(route('login'));
        $this->assertGuest();

        $sdm = $this->makeDivision('SDM & Umum', 'SDM');
        $otherDivision = $this->makeDivision('Redaksi', 'RED');
        $forbiddenUsers = [
            $this->makeUser('ketua_divisi', $otherDivision),
            $this->makeUser('anggota_divisi', $otherDivision),
            $this->makeUser('anggota_divisi', $sdm),
        ];

        foreach ($forbiddenUsers as $user) {
            foreach ($this->reportRoutes() as $route) {
                $this->actingAs($user)->get(route($route))->assertForbidden();
            }
        }
    }

    public function test_admin_pimpinan_and_sdm_division_head_can_access_report_and_export(): void
    {
        $sdm = $this->makeDivision('SDM & Umum', 'SDM');

        foreach ([
            $this->makeUser('admin_surat'),
            $this->makeUser('pimpinan'),
            $this->makeUser('ketua_divisi', $sdm),
        ] as $user) {
            $this->actingAs($user)
                ->get(route('reports.certificates.index'))
                ->assertOk();

            $response = $this->actingAs($user)
                ->get(route('reports.certificates.export'))
                ->assertOk()
                ->assertDownload('laporan-sertifikat-semua-tahun.xlsx')
                ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

            $this->removeDownloadedFile($response);
        }
    }

    public function test_search_year_combination_summary_year_options_and_default_ordering_are_consistent(): void
    {
        $admin = $this->makeUser('admin_surat');
        $nameMatch = $this->makeCertificate($admin, [
            'participant_name' => 'Ahmad Fajar',
            'institution_name' => 'Institut Lain',
            'major_name' => 'Teknik Informatika',
            'end_date' => '2026-07-31',
        ]);
        $institutionMatch = $this->makeCertificate($admin, [
            'participant_name' => 'Andini Putri',
            'institution_name' => 'Universitas Brawijaya',
            'major_name' => 'Akuntansi',
            'end_date' => '2026-08-31',
        ]);
        $majorMatch = $this->makeCertificate($admin, [
            'participant_name' => 'Budi Santoso',
            'institution_name' => 'Universitas Negeri',
            'major_name' => 'Ilmu Komunikasi',
            'end_date' => '2025-07-31',
        ]);

        foreach ([
            'ahmad' => $nameMatch->id,
            'Brawijaya' => $institutionMatch->id,
            'Komunikasi' => $majorMatch->id,
        ] as $search => $expectedId) {
            $this->actingAs($admin)
                ->get(route('reports.certificates.index', ['search' => $search]))
                ->assertOk()
                ->assertViewHas('certificates', fn (LengthAwarePaginator $items): bool => $items->total() === 1 && $items->first()->id === $expectedId)
                ->assertViewHas('summary', ['total' => 1]);
        }

        $this->actingAs($admin)
            ->get(route('reports.certificates.index', ['search' => 'Brawijaya', 'year' => 2026]))
            ->assertOk()
            ->assertViewHas('certificates', fn (LengthAwarePaginator $items): bool => $items->pluck('id')->all() === [$institutionMatch->id])
            ->assertViewHas('summary', ['total' => 1])
            ->assertViewHas('years', fn ($years): bool => $years->all() === [2026, 2025])
            ->assertSee('value="Brawijaya"', false)
            ->assertSee('value="2026" selected', false);

        $this->actingAs($admin)
            ->get(route('reports.certificates.index'))
            ->assertViewHas('certificates', fn (LengthAwarePaginator $items): bool => $items->pluck('id')->all() === [
                $institutionMatch->id,
                $nameMatch->id,
                $majorMatch->id,
            ])
            ->assertViewHas('summary', ['total' => 3]);
    }

    public function test_validation_pagination_business_columns_and_empty_states_are_correct(): void
    {
        $admin = $this->makeUser('admin_surat');

        $this->actingAs($admin)
            ->from(route('reports.certificates.index'))
            ->get(route('reports.certificates.index', ['year' => 'abc']))
            ->assertRedirect(route('reports.certificates.index'))
            ->assertSessionHasErrors('year');

        $this->actingAs($admin)
            ->get(route('reports.certificates.index'))
            ->assertOk()
            ->assertSee('Belum ada Sertifikat')
            ->assertSee('Data laporan sertifikat akan tampil di sini.');

        foreach (range(1, 16) as $number) {
            $this->makeCertificate($admin, [
                'participant_name' => 'Peserta Pagination '.sprintf('%02d', $number),
                'institution_name' => 'Universitas Brawijaya',
                'end_date' => '2026-07-31',
            ]);
        }

        $response = $this->actingAs($admin)
            ->get(route('reports.certificates.index', [
                'search' => 'Brawijaya',
                'year' => 2026,
            ]))
            ->assertOk()
            ->assertSeeInOrder([
                'No', 'Nama Peserta', 'Institusi', 'Program Studi / Jurusan', 'Periode',
            ])
            ->assertDontSee('Kode Sistem')
            ->assertDontSee('document_path')
            ->assertViewHas('certificates', function (LengthAwarePaginator $items): bool {
                $query = [];
                parse_str((string) parse_url((string) $items->nextPageUrl(), PHP_URL_QUERY), $query);

                return $items->count() === 15
                    && $items->total() === 16
                    && ($query['search'] ?? null) === 'Brawijaya'
                    && ($query['year'] ?? null) === '2026';
            });

        $response->assertSee('Halaman 1 dari 2');

        $this->actingAs($admin)
            ->get(route('reports.certificates.index', ['search' => 'Tidak Ada']))
            ->assertOk()
            ->assertSee('Data tidak ditemukan')
            ->assertSee('Tidak ada data yang sesuai dengan pencarian atau filter.')
            ->assertSee('Reset');
    }

    public function test_sidebar_visibility_and_active_state_follow_certificate_report_authorization(): void
    {
        $sdm = $this->makeDivision('SDM & Umum', 'SDM');
        $otherDivision = $this->makeDivision('Redaksi', 'RED');

        foreach ([
            $this->makeUser('admin_surat'),
            $this->makeUser('pimpinan'),
            $this->makeUser('ketua_divisi', $sdm),
        ] as $user) {
            $this->actingAs($user)
                ->get(route('reports.certificates.index'))
                ->assertOk()
                ->assertSee('data-testid="certificate-report-menu-desktop"', false)
                ->assertSee('data-testid="certificate-report-menu-mobile"', false)
                ->assertSee('rs-nav-collapse-button active', false)
                ->assertSee('collapse show', false)
                ->assertSee('rs-nav-sublink active', false)
                ->assertSee('data-testid="incoming-report-menu-desktop"', false)
                ->assertSee('data-testid="outgoing-report-menu-desktop"', false);
        }

        foreach ([
            $this->makeUser('ketua_divisi', $otherDivision),
            $this->makeUser('anggota_divisi', $otherDivision),
            $this->makeUser('anggota_divisi', $sdm),
        ] as $user) {
            $this->actingAs($user)
                ->get(route('dashboard'))
                ->assertOk()
                ->assertDontSee('data-testid="certificate-report-menu-desktop"', false)
                ->assertDontSee('data-testid="certificate-report-menu-mobile"', false);
        }
    }

    public function test_excel_contains_metadata_business_columns_full_filtered_dataset_and_no_internal_fields(): void
    {
        Carbon::setTestNow('2026-08-14 20:47:00');
        $admin = $this->makeUser('admin_surat');

        foreach (range(1, 30) as $number) {
            $this->makeCertificate($admin, [
                'participant_name' => 'Peserta Terpilih '.sprintf('%02d', $number),
                'institution_name' => 'Universitas Brawijaya',
                'end_date' => '2026-07-31',
            ]);
        }
        $excluded = $this->makeCertificate($admin, [
            'participant_name' => 'Peserta Tidak Terpilih',
            'institution_name' => 'Universitas Brawijaya',
            'end_date' => '2025-07-31',
            'document_path' => 'internship-certificates/secret/internal.pdf',
        ]);

        $response = $this->actingAs($admin)
            ->get(route('reports.certificates.export', [
                'search' => 'Brawijaya',
                'year' => 2026,
            ]))
            ->assertOk()
            ->assertDownload('laporan-sertifikat-2026.xlsx')
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $rows = $this->readWorkbookRows($response);
        $text = collect($rows)->flatten()->filter(fn ($value): bool => $value !== null)->implode('|');

        foreach ([
            'SIRAPI',
            'Sistem Arsip Jawa Pos Radar Kediri',
            'LAPORAN ARSIP SERTIFIKAT',
            'Tahun',
            '2026',
            'Pencarian',
            'Brawijaya',
            'Total Data',
            'Diekspor Oleh',
            $admin->name,
            'Tanggal Export',
            '14 Agustus 2026, 20:47 WIB',
        ] as $expected) {
            $this->assertStringContainsString($expected, $text);
        }

        $this->assertSame([
            'No',
            'Nama Peserta',
            'Asal Institusi',
            'Program Studi / Jurusan',
            'Tanggal Mulai',
            'Tanggal Selesai',
        ], $this->tableHeader($rows));
        $this->assertCount(30, $this->tableDataRows($rows));
        $this->assertSame(30, $this->metadataValue($rows, 'Total Data'));
        $this->assertStringContainsString('01/05/2026', $text);
        $this->assertStringContainsString('31/07/2026', $text);
        $this->assertStringNotContainsString($excluded->archive_code, $text);
        $this->assertStringNotContainsString($excluded->document_path, $text);
        $this->assertStringNotContainsString('document_path', $text);
        $this->assertStringNotContainsString('document_mime_type', $text);
    }

    /** @return array<int, string> */
    private function reportRoutes(): array
    {
        return ['reports.certificates.index', 'reports.certificates.export'];
    }

    /** @return array<int, array<int, bool|float|int|string|null>> */
    private function readWorkbookRows(TestResponse $response): array
    {
        $path = $response->baseResponse->getFile()->getPathname();
        $reader = new Reader;
        $reader->open($path);
        $rows = [];

        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $rows[] = $row->toArray();
            }

            break;
        }

        $reader->close();
        unlink($path);

        return $rows;
    }

    /** @param array<int, array<int, mixed>> $rows */
    private function tableHeader(array $rows): array
    {
        return collect($rows)->first(fn (array $row): bool => ($row[0] ?? null) === 'No');
    }

    /** @param array<int, array<int, mixed>> $rows */
    private function tableDataRows(array $rows): array
    {
        $headerIndex = collect($rows)->search(fn (array $row): bool => ($row[0] ?? null) === 'No');

        return array_slice($rows, $headerIndex + 1);
    }

    /** @param array<int, array<int, mixed>> $rows */
    private function metadataValue(array $rows, string $label): int
    {
        $row = collect($rows)->first(fn (array $row): bool => ($row[0] ?? null) === $label);

        return (int) ($row[1] ?? 0);
    }

    private function removeDownloadedFile(TestResponse $response): void
    {
        $path = $response->baseResponse->getFile()->getPathname();

        if (is_file($path)) {
            unlink($path);
        }
    }

    private function makeDivision(string $name, string $code): Division
    {
        return Division::query()->create([
            'name' => $name,
            'code' => $code,
            'is_active' => true,
        ]);
    }

    private function makeUser(string $roleSlug, ?Division $division = null, bool $active = true): User
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
            'is_active' => $active,
        ]);
    }

    private function makeCertificate(User $creator, array $overrides = []): InternshipCertificate
    {
        return InternshipCertificate::query()->create(array_merge([
            'archive_code' => 'SERT-'.Str::uuid(),
            'participant_name' => 'Peserta Magang',
            'institution_name' => 'Universitas Kediri',
            'major_name' => 'Ilmu Komunikasi',
            'start_date' => '2026-05-01',
            'end_date' => '2026-07-31',
            'document_path' => 'internship-certificates/2026/'.Str::uuid().'.pdf',
            'original_document_name' => 'sertifikat.pdf',
            'document_mime_type' => 'application/pdf',
            'document_size' => 100,
            'created_by' => $creator->id,
        ], $overrides));
    }
}
