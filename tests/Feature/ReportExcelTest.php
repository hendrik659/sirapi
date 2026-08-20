<?php

namespace Tests\Feature;

use App\Models\Division;
use App\Models\IncomingLetter;
use App\Models\OutgoingLetter;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use OpenSpout\Reader\XLSX\Reader;
use Tests\TestCase;

class ReportExcelTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_incoming_xlsx_contains_professional_header_summary_filtered_columns_and_all_rows(): void
    {
        Carbon::setTestNow('2026-08-07 10:30:00');
        $division = $this->makeDivision('Redaksi', 'RED');
        $admin = $this->makeUser('admin_surat');

        foreach (range(1, 30) as $number) {
            $this->makeIncoming($admin, $division, [
                'agenda_number' => sprintf('AGD-SELECTED-%03d', $number),
                'subject' => 'Data Export Terpilih',
                'received_date' => '2026-08-07',
                'status' => match ($number % 3) {
                    0 => IncomingLetter::STATUS_BARU_DITERIMA,
                    1 => IncomingLetter::STATUS_MENUNGGU_PEMERIKSAAN,
                    default => IncomingLetter::STATUS_SELESAI,
                },
            ]);
        }
        $excluded = $this->makeIncoming($admin, $division, [
            'agenda_number' => 'AGD-NOT-EXPORTED',
            'subject' => 'Data Di Luar Filter',
            'document_path' => 'incoming-letters/secret/internal-document.pdf',
        ]);

        $response = $this->actingAs($admin)->get(route('reports.incoming-letters.export', [
            'search' => 'Data Export Terpilih',
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-31',
            'division_id' => $division->id,
        ]));

        $response->assertOk()
            ->assertDownload('laporan-surat-masuk-2026-08.xlsx')
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $rows = $this->readWorkbookRows($response);
        $text = $this->workbookText($rows);

        $this->assertStringContainsString('RADAR KEDIRI', $text);
        $this->assertStringContainsString('LAPORAN SURAT MASUK', $text);
        $this->assertStringContainsString('2026-08-01 s.d. 2026-08-31', $text);
        $this->assertStringContainsString('Redaksi', $text);
        $this->assertStringContainsString($admin->name, $text);
        $this->assertStringContainsString('Tanggal Export', $text);
        $this->assertStringContainsString('7 Agustus 2026, 10:30 WIB', $text);
        $this->assertStringContainsString('RINGKASAN', $text);
        $this->assertStringContainsString('Total Surat', $text);
        $this->assertStringNotContainsString($excluded->agenda_number, $text);
        $this->assertStringNotContainsString($excluded->document_path, $text);
        $this->assertStringNotContainsString('document_path', $text);
        $this->assertSame([
            'No', 'Agenda', 'Nomor Surat', 'Tanggal Diterima', 'Pengirim', 'Perihal',
            'Divisi Tujuan', 'Prioritas', 'Status',
        ], $this->tableHeader($rows));
        $this->assertCount(30, $this->tableDataRows($rows));
        $this->assertSame(30, $this->summaryValue($rows, 'Total Surat'));
        $this->assertSame(10, $this->summaryValue($rows, 'Baru Diterima'));
        $this->assertSame(10, $this->summaryValue($rows, 'Menunggu Pemeriksaan'));
        $this->assertSame(10, $this->summaryValue($rows, 'Selesai'));
    }

    public function test_outgoing_xlsx_contains_filtered_rows_and_is_not_limited_by_ui_pagination(): void
    {
        Carbon::setTestNow('2026-08-07 10:30:00');
        $division = $this->makeDivision('Keuangan', 'KEU');
        $admin = $this->makeUser('pimpinan');

        foreach (range(1, 31) as $number) {
            $this->makeOutgoing($admin, $division, [
                'reference_code' => sprintf('SK-2026-%03d', $number),
                'letter_number' => sprintf('OUT-SELECTED-%03d', $number),
                'subject' => 'Data Export Keluar',
                'letter_date' => '2026-08-07',
            ]);
        }
        $excluded = $this->makeOutgoing($admin, $division, [
            'reference_code' => 'SK-2026-999',
            'subject' => 'Tidak Dipilih',
            'document_path' => 'outgoing-letters/secret/internal-document.pdf',
        ]);

        $response = $this->actingAs($admin)->get(route('reports.outgoing-letters.export', [
            'search' => 'Data Export Keluar',
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-31',
        ]));

        $response->assertOk()
            ->assertDownload('laporan-surat-keluar-2026-08.xlsx')
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $rows = $this->readWorkbookRows($response);
        $text = $this->workbookText($rows);

        $this->assertStringContainsString('RADAR KEDIRI', $text);
        $this->assertStringContainsString('LAPORAN SURAT KELUAR', $text);
        $this->assertStringContainsString('Semua Divisi', $text);
        $this->assertStringContainsString('7 Agustus 2026, 10:30 WIB', $text);
        $this->assertStringContainsString('RINGKASAN', $text);
        $this->assertStringNotContainsString($excluded->reference_code, $text);
        $this->assertStringNotContainsString($excluded->document_path, $text);
        $this->assertSame([
            'No', 'Kode Sistem', 'Nomor Surat', 'Tanggal Surat', 'Tujuan', 'Perihal',
            'Divisi', 'Dicatat Oleh',
        ], $this->tableHeader($rows));
        $this->assertCount(31, $this->tableDataRows($rows));
        $this->assertSame(31, $this->summaryValue($rows, 'Total Surat Keluar'));
    }

    public function test_forged_division_filter_cannot_leak_incoming_or_outgoing_rows_in_member_exports(): void
    {
        Carbon::setTestNow('2026-08-07 10:30:00');
        $redaction = $this->makeDivision('Redaksi', 'RED');
        $finance = $this->makeDivision('Keuangan', 'KEU');
        $creator = $this->makeUser('admin_surat');
        $this->makeIncoming($creator, $redaction, ['agenda_number' => 'AGD-REDAKSI']);
        $this->makeIncoming($creator, $finance, ['agenda_number' => 'AGD-KEUANGAN']);
        $this->makeOutgoing($creator, $redaction, ['reference_code' => 'SK-REDAKSI']);
        $this->makeOutgoing($creator, $finance, ['reference_code' => 'SK-KEUANGAN']);

        foreach (['ketua_divisi', 'anggota_divisi'] as $role) {
            $user = $this->makeUser($role, $redaction);
            $incomingResponse = $this->actingAs($user)->get(route('reports.incoming-letters.export', [
                'division_id' => $finance->id,
            ]));
            $incomingResponse->assertDownload('laporan-surat-masuk-redaksi-2026-08.xlsx');
            $incomingText = $this->workbookText($this->readWorkbookRows($incomingResponse));
            $this->assertStringContainsString('AGD-REDAKSI', $incomingText);
            $this->assertStringNotContainsString('AGD-KEUANGAN', $incomingText);

            $outgoingResponse = $this->actingAs($user)->get(route('reports.outgoing-letters.export', [
                'division_id' => $finance->id,
            ]));
            $outgoingResponse->assertDownload('laporan-surat-keluar-redaksi-2026-08.xlsx');
            $outgoingText = $this->workbookText($this->readWorkbookRows($outgoingResponse));
            $this->assertStringContainsString('SK-REDAKSI', $outgoingText);
            $this->assertStringNotContainsString('SK-KEUANGAN', $outgoingText);
        }
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
    private function workbookText(array $rows): string
    {
        return collect($rows)->flatten()->filter(fn ($value): bool => $value !== null)->implode('|');
    }

    /**
     * @param  array<int, array<int, mixed>>  $rows
     * @return array<int, mixed>
     */
    private function tableHeader(array $rows): array
    {
        return collect($rows)->first(fn (array $row): bool => ($row[0] ?? null) === 'No');
    }

    /**
     * @param  array<int, array<int, mixed>>  $rows
     * @return array<int, array<int, mixed>>
     */
    private function tableDataRows(array $rows): array
    {
        $headerIndex = collect($rows)->search(fn (array $row): bool => ($row[0] ?? null) === 'No');

        return array_slice($rows, $headerIndex + 1);
    }

    /** @param array<int, array<int, mixed>> $rows */
    private function summaryValue(array $rows, string $label): int
    {
        $row = collect($rows)->first(fn (array $row): bool => ($row[0] ?? null) === $label);

        return (int) ($row[1] ?? 0);
    }

    private function makeDivision(string $name, string $code): Division
    {
        return Division::query()->create(['name' => $name, 'code' => $code, 'is_active' => true]);
    }

    private function makeUser(string $roleSlug, ?Division $division = null): User
    {
        $role = Role::query()->firstOrCreate(['slug' => $roleSlug], ['name' => Str::headline($roleSlug)]);

        return User::query()->create([
            'name' => Str::headline($roleSlug),
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'role_id' => $role->id,
            'division_id' => $division?->id,
            'is_active' => true,
        ]);
    }

    /** @param array<string, mixed> $overrides */
    private function makeIncoming(User $creator, ?Division $division, array $overrides = []): IncomingLetter
    {
        return IncomingLetter::query()->create(array_merge([
            'agenda_number' => 'AGD-'.Str::uuid(),
            'letter_number' => 'SM-'.Str::uuid(),
            'sender_name' => 'Pengirim Surat',
            'addressed_to' => 'Radar Kediri',
            'letter_date' => '2026-08-01',
            'received_date' => '2026-08-07',
            'received_via' => 'fisik',
            'subject' => 'Perihal Surat Masuk',
            'priority' => 'biasa',
            'destination_division_id' => $division?->id,
            'document_path' => 'incoming-letters/2026/'.Str::uuid().'.pdf',
            'original_document_name' => 'surat.pdf',
            'document_mime_type' => 'application/pdf',
            'document_size' => 100,
            'status' => IncomingLetter::STATUS_BARU_DITERIMA,
            'created_by' => $creator->id,
        ], $overrides));
    }

    /** @param array<string, mixed> $overrides */
    private function makeOutgoing(User $creator, Division $division, array $overrides = []): OutgoingLetter
    {
        return OutgoingLetter::query()->create(array_merge([
            'reference_code' => 'SK-'.Str::uuid(),
            'letter_number' => 'SK-NO-'.Str::uuid(),
            'letter_date' => '2026-08-07',
            'recipient_name' => 'Penerima Surat',
            'subject' => 'Perihal Surat Keluar',
            'division_id' => $division->id,
            'created_by' => $creator->id,
            'document_path' => 'outgoing-letters/2026/'.Str::uuid().'.pdf',
            'original_document_name' => 'surat.pdf',
            'document_mime_type' => 'application/pdf',
            'document_size' => 100,
        ], $overrides));
    }
}
