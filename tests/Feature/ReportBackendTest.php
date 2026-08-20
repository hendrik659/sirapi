<?php

namespace Tests\Feature;

use App\Models\Division;
use App\Models\IncomingLetter;
use App\Models\OutgoingLetter;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class ReportBackendTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_and_inactive_user_cannot_access_report_pages_or_exports(): void
    {
        $routes = $this->reportRoutes();

        foreach ($routes as $route) {
            $this->get(route($route))->assertRedirect(route('login'));
        }

        $inactive = $this->makeUser('admin_surat', null, false);

        foreach ($routes as $route) {
            $this->actingAs($inactive);
            $this->get(route($route))->assertRedirect(route('login'));
            $this->assertGuest();
        }
    }

    public function test_all_allowed_roles_can_access_pages_and_exports_with_required_division(): void
    {
        $division = $this->makeDivision();

        foreach (['admin_surat', 'pimpinan', 'ketua_divisi', 'anggota_divisi'] as $role) {
            $user = $this->makeUser($role, $division);

            $this->actingAs($user)->get(route('reports.incoming-letters.index'))->assertOk();
            $this->actingAs($user)->get(route('reports.outgoing-letters.index'))->assertOk();
            $this->assertSuccessfulExport(
                $this->actingAs($user)->get(route('reports.incoming-letters.export')),
            );
            $this->assertSuccessfulExport(
                $this->actingAs($user)->get(route('reports.outgoing-letters.export')),
            );
        }
    }

    public function test_division_roles_without_division_and_unlisted_roles_are_forbidden(): void
    {
        foreach (['ketua_divisi', 'anggota_divisi'] as $role) {
            $user = $this->makeUser($role);

            foreach ($this->reportRoutes() as $route) {
                $this->actingAs($user)->get(route($route))->assertForbidden();
            }
        }

        $otherRole = $this->makeUser('staf_lain', $this->makeDivision());
        $this->actingAs($otherRole)->get(route('reports.incoming-letters.index'))->assertForbidden();
        $this->actingAs($otherRole)->get(route('reports.outgoing-letters.index'))->assertForbidden();
    }

    public function test_division_scope_and_forged_division_filter_are_enforced_for_heads_and_members(): void
    {
        $redaction = $this->makeDivision('Redaksi', 'RED');
        $finance = $this->makeDivision('Keuangan', 'KEU');
        $creator = $this->makeUser('admin_surat');
        $redactionIncoming = $this->makeIncoming($creator, $redaction, ['subject' => 'Incoming Redaksi']);
        $this->makeIncoming($creator, $finance, ['subject' => 'Incoming Keuangan']);
        $redactionOutgoing = $this->makeOutgoing($creator, $redaction, ['subject' => 'Outgoing Redaksi']);
        $this->makeOutgoing($creator, $finance, ['subject' => 'Outgoing Keuangan']);

        foreach (['ketua_divisi', 'anggota_divisi'] as $role) {
            $user = $this->makeUser($role, $redaction);

            $this->actingAs($user)
                ->get(route('reports.incoming-letters.index', ['division_id' => $finance->id]))
                ->assertOk()
                ->assertViewHas('incomingLetters', fn (LengthAwarePaginator $letters): bool => $letters->pluck('id')->all() === [$redactionIncoming->id]);

            $this->actingAs($user)
                ->get(route('reports.outgoing-letters.index', ['division_id' => $finance->id]))
                ->assertOk()
                ->assertViewHas('outgoingLetters', fn (LengthAwarePaginator $letters): bool => $letters->pluck('id')->all() === [$redactionOutgoing->id]);

            $this->actingAs($user)
                ->get(route('reports.incoming-letters.index', ['division_id' => 999999]))
                ->assertOk()
                ->assertViewHas('incomingLetters', fn (LengthAwarePaginator $letters): bool => $letters->pluck('id')->all() === [$redactionIncoming->id]);

            $this->actingAs($user)
                ->get(route('reports.outgoing-letters.index', ['division_id' => 999999]))
                ->assertOk()
                ->assertViewHas('outgoingLetters', fn (LengthAwarePaginator $letters): bool => $letters->pluck('id')->all() === [$redactionOutgoing->id]);
        }
    }

    public function test_admin_and_leader_can_filter_another_division(): void
    {
        $redaction = $this->makeDivision('Redaksi', 'RED');
        $finance = $this->makeDivision('Keuangan', 'KEU');
        $creator = $this->makeUser('admin_surat');
        $this->makeIncoming($creator, $redaction);
        $financeIncoming = $this->makeIncoming($creator, $finance);
        $this->makeOutgoing($creator, $redaction);
        $financeOutgoing = $this->makeOutgoing($creator, $finance);

        foreach (['admin_surat', 'pimpinan'] as $role) {
            $user = $this->makeUser($role);

            $this->actingAs($user)
                ->get(route('reports.incoming-letters.index', ['division_id' => $finance->id]))
                ->assertViewHas('incomingLetters', fn (LengthAwarePaginator $letters): bool => $letters->pluck('id')->all() === [$financeIncoming->id]);
            $this->actingAs($user)
                ->get(route('reports.outgoing-letters.index', ['division_id' => $finance->id]))
                ->assertViewHas('outgoingLetters', fn (LengthAwarePaginator $letters): bool => $letters->pluck('id')->all() === [$financeOutgoing->id]);
        }
    }

    public function test_incoming_search_uses_all_required_columns(): void
    {
        $division = $this->makeDivision();
        $admin = $this->makeUser('admin_surat');
        $letters = [
            $this->makeIncoming($admin, $division, ['agenda_number' => 'AGENDA-UNIK']),
            $this->makeIncoming($admin, $division, ['letter_number' => 'NOMOR-UNIK']),
            $this->makeIncoming($admin, $division, ['sender_name' => 'PENGIRIM-UNIK']),
            $this->makeIncoming($admin, $division, ['subject' => 'PERIHAL-UNIK']),
        ];

        foreach ([
            'AGENDA-UNIK' => $letters[0]->id,
            'NOMOR-UNIK' => $letters[1]->id,
            'PENGIRIM-UNIK' => $letters[2]->id,
            'PERIHAL-UNIK' => $letters[3]->id,
        ] as $search => $expectedId) {
            $this->actingAs($admin)
                ->get(route('reports.incoming-letters.index', ['search' => $search]))
                ->assertOk()
                ->assertViewHas('incomingLetters', fn (LengthAwarePaginator $result): bool => $result->total() === 1 && $result->first()->id === $expectedId);
        }
    }

    public function test_incoming_period_status_priority_and_combined_filters_work(): void
    {
        $division = $this->makeDivision();
        $admin = $this->makeUser('admin_surat');
        $early = $this->makeIncoming($admin, $division, [
            'received_date' => '2026-07-31',
            'status' => IncomingLetter::STATUS_BARU_DITERIMA,
            'priority' => 'biasa',
            'subject' => 'Data Awal',
        ]);
        $target = $this->makeIncoming($admin, $division, [
            'received_date' => '2026-08-07',
            'status' => IncomingLetter::STATUS_SELESAI,
            'priority' => 'segera',
            'subject' => 'Target Gabungan',
        ]);
        $late = $this->makeIncoming($admin, $division, [
            'received_date' => '2026-08-20',
            'status' => IncomingLetter::STATUS_MENUNGGU_PEMERIKSAAN,
            'priority' => 'segera',
            'subject' => 'Data Akhir',
        ]);

        $checks = [
            [['start_date' => '2026-08-01'], [$target->id, $late->id]],
            [['end_date' => '2026-08-07'], [$early->id, $target->id]],
            [['start_date' => '2026-08-01', 'end_date' => '2026-08-10'], [$target->id]],
            [['status' => IncomingLetter::STATUS_SELESAI], [$target->id]],
            [['priority' => 'biasa'], [$early->id]],
            [[
                'search' => 'Target',
                'start_date' => '2026-08-01',
                'end_date' => '2026-08-10',
                'status' => IncomingLetter::STATUS_SELESAI,
                'priority' => 'segera',
                'division_id' => $division->id,
            ], [$target->id]],
        ];

        foreach ($checks as [$filters, $expectedIds]) {
            sort($expectedIds);
            $this->actingAs($admin)
                ->get(route('reports.incoming-letters.index', $filters))
                ->assertOk()
                ->assertViewHas('incomingLetters', function (LengthAwarePaginator $result) use ($expectedIds): bool {
                    $ids = $result->pluck('id')->sort()->values()->all();

                    return $ids === $expectedIds;
                });
        }
    }

    public function test_incoming_validation_uses_indonesian_messages_and_only_final_statuses(): void
    {
        $admin = $this->makeUser('admin_surat');

        $this->actingAs($admin)
            ->from(route('reports.incoming-letters.index'))
            ->get(route('reports.incoming-letters.index', [
                'start_date' => '2026-08-10',
                'end_date' => '2026-08-01',
                'status' => 'status_lain',
                'priority' => 'darurat',
            ]))
            ->assertRedirect(route('reports.incoming-letters.index'))
            ->assertSessionHasErrors([
                'end_date' => 'Tanggal akhir harus sama dengan atau setelah tanggal awal.',
                'status' => 'Status surat masuk tidak valid.',
                'priority' => 'Prioritas surat masuk tidak valid.',
            ]);
    }

    public function test_report_filters_reject_invalid_ranges_and_inactive_divisions_for_global_roles(): void
    {
        $inactiveDivision = Division::query()->create([
            'name' => 'Divisi Nonaktif',
            'code' => 'NON',
            'is_active' => false,
        ]);
        $admin = $this->makeUser('admin_surat');

        $this->actingAs($admin)
            ->from(route('reports.incoming-letters.index'))
            ->get(route('reports.incoming-letters.index', ['division_id' => $inactiveDivision->id]))
            ->assertRedirect(route('reports.incoming-letters.index'))
            ->assertSessionHasErrors([
                'division_id' => 'Divisi tidak tersedia atau sudah tidak aktif.',
            ]);

        $this->actingAs($admin)
            ->from(route('reports.outgoing-letters.index'))
            ->get(route('reports.outgoing-letters.index', [
                'start_date' => '2026-08-10',
                'end_date' => '2026-08-01',
                'division_id' => $inactiveDivision->id,
            ]))
            ->assertRedirect(route('reports.outgoing-letters.index'))
            ->assertSessionHasErrors([
                'end_date' => 'Tanggal akhir harus sama dengan atau setelah tanggal awal.',
                'division_id' => 'Divisi tidak tersedia atau sudah tidak aktif.',
            ]);
    }

    public function test_incoming_summary_and_recap_follow_the_filtered_query(): void
    {
        $redaction = $this->makeDivision('Redaksi', 'RED');
        $finance = $this->makeDivision('Keuangan', 'KEU');
        $admin = $this->makeUser('admin_surat');
        $this->makeIncoming($admin, $redaction, ['subject' => 'Terpilih', 'status' => IncomingLetter::STATUS_BARU_DITERIMA]);
        $this->makeIncoming($admin, $redaction, ['subject' => 'Terpilih', 'status' => IncomingLetter::STATUS_SELESAI]);
        $this->makeIncoming($admin, $finance, ['subject' => 'Terpilih', 'status' => IncomingLetter::STATUS_MENUNGGU_PEMERIKSAAN]);
        $this->makeIncoming($admin, $finance, ['subject' => 'Tidak Masuk', 'status' => IncomingLetter::STATUS_SELESAI]);
        $this->makeIncoming($admin, $finance, ['subject' => 'Terpilih', 'status' => 'status_nonaktif']);

        $response = $this->actingAs($admin)
            ->get(route('reports.incoming-letters.index', ['search' => 'Terpilih']))
            ->assertOk()
            ->assertViewHas('summary', [
                'total' => 3,
                'baru_diterima' => 1,
                'menunggu_pemeriksaan' => 1,
                'selesai' => 1,
            ]);

        $response->assertViewHas('recap', function (Collection $recap) use ($redaction, $finance): bool {
            return (int) $recap->firstWhere('division_id', $redaction->id)->total === 2
                && (int) $recap->firstWhere('division_id', $finance->id)->total === 1;
        });
    }

    public function test_incoming_pagination_preserves_all_query_parameters(): void
    {
        $division = $this->makeDivision();
        $admin = $this->makeUser('admin_surat');

        foreach (range(1, 26) as $number) {
            $this->makeIncoming($admin, $division, [
                'subject' => 'Data Pagination',
                'received_date' => '2026-08-07',
            ]);
        }

        $filters = [
            'search' => 'Data Pagination',
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-31',
            'status' => IncomingLetter::STATUS_BARU_DITERIMA,
            'priority' => 'biasa',
            'division_id' => $division->id,
        ];
        $this->actingAs($admin)
            ->get(route('reports.incoming-letters.index', $filters))
            ->assertOk()
            ->assertViewHas('incomingLetters', function (LengthAwarePaginator $letters) use ($filters): bool {
                $next = $letters->nextPageUrl();
                parse_str((string) parse_url((string) $next, PHP_URL_QUERY), $nextQuery);

                return $letters->count() === 25
                    && collect($filters)->every(fn ($value, $key): bool => ($nextQuery[$key] ?? null) == $value);
            });
    }

    public function test_outgoing_search_period_division_and_combined_filters_work(): void
    {
        $redaction = $this->makeDivision('Redaksi', 'RED');
        $finance = $this->makeDivision('Keuangan', 'KEU');
        $admin = $this->makeUser('admin_surat');
        $letters = [
            $this->makeOutgoing($admin, $redaction, ['reference_code' => 'KODE-UNIK', 'letter_date' => '2026-07-31']),
            $this->makeOutgoing($admin, $redaction, ['letter_number' => 'NOMOR-UNIK', 'letter_date' => '2026-08-07']),
            $this->makeOutgoing($admin, $finance, ['recipient_name' => 'PENERIMA-UNIK', 'letter_date' => '2026-08-10']),
            $this->makeOutgoing($admin, $finance, ['subject' => 'PERIHAL-UNIK', 'letter_date' => '2026-08-20']),
        ];

        foreach ([
            'KODE-UNIK' => $letters[0]->id,
            'NOMOR-UNIK' => $letters[1]->id,
            'PENERIMA-UNIK' => $letters[2]->id,
            'PERIHAL-UNIK' => $letters[3]->id,
        ] as $search => $expectedId) {
            $this->actingAs($admin)
                ->get(route('reports.outgoing-letters.index', ['search' => $search]))
                ->assertViewHas('outgoingLetters', fn (LengthAwarePaginator $result): bool => $result->total() === 1 && $result->first()->id === $expectedId);
        }

        $checks = [
            [['start_date' => '2026-08-10'], [$letters[2]->id, $letters[3]->id]],
            [['end_date' => '2026-08-07'], [$letters[0]->id, $letters[1]->id]],
            [['start_date' => '2026-08-01', 'end_date' => '2026-08-15'], [$letters[1]->id, $letters[2]->id]],
            [['division_id' => $finance->id], [$letters[2]->id, $letters[3]->id]],
            [[
                'search' => 'PENERIMA-UNIK',
                'start_date' => '2026-08-01',
                'end_date' => '2026-08-15',
                'division_id' => $finance->id,
            ], [$letters[2]->id]],
        ];

        foreach ($checks as [$filters, $expectedIds]) {
            sort($expectedIds);
            $this->actingAs($admin)
                ->get(route('reports.outgoing-letters.index', $filters))
                ->assertOk()
                ->assertViewHas('outgoingLetters', function (LengthAwarePaginator $result) use ($expectedIds): bool {
                    return $result->pluck('id')->sort()->values()->all() === $expectedIds;
                });
        }
    }

    public function test_outgoing_summary_recap_and_pagination_follow_filters(): void
    {
        $redaction = $this->makeDivision('Redaksi', 'RED');
        $finance = $this->makeDivision('Keuangan', 'KEU');
        $admin = $this->makeUser('admin_surat');
        $this->makeOutgoing($admin, $redaction, ['subject' => 'Terpilih']);
        $this->makeOutgoing($admin, $finance, ['subject' => 'Terpilih']);
        $this->makeOutgoing($admin, $finance, ['subject' => 'Tidak Masuk']);

        foreach (range(1, 24) as $number) {
            $this->makeOutgoing($admin, $redaction, ['subject' => 'Terpilih']);
        }

        $response = $this->actingAs($admin)
            ->get(route('reports.outgoing-letters.index', ['search' => 'Terpilih']))
            ->assertOk()
            ->assertViewHas('summary', ['total' => 26, 'division_count' => 2])
            ->assertViewHas('outgoingLetters', function (LengthAwarePaginator $letters): bool {
                return $letters->count() === 25
                    && str_contains((string) $letters->nextPageUrl(), 'search=Terpilih');
            });

        $response->assertViewHas('recap', function (Collection $recap) use ($redaction, $finance): bool {
            return (int) $recap->firstWhere('division_id', $redaction->id)->total === 25
                && (int) $recap->firstWhere('division_id', $finance->id)->total === 1;
        });
    }

    public function test_feature_registers_exactly_six_read_only_get_routes(): void
    {
        $routes = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route): bool => str_starts_with($route->getName() ?? '', 'reports.'));

        $this->assertEqualsCanonicalizing([
            ...$this->reportRoutes(),
            'reports.certificates.index',
            'reports.certificates.export',
        ], $routes->pluck('action.as')->all());
        $this->assertCount(6, $routes);
        $this->assertTrue($routes->every(fn ($route): bool => $route->methods() === ['GET', 'HEAD']));
    }

    /** @return array<int, string> */
    private function reportRoutes(): array
    {
        return [
            'reports.incoming-letters.index',
            'reports.incoming-letters.export',
            'reports.outgoing-letters.index',
            'reports.outgoing-letters.export',
        ];
    }

    private function assertSuccessfulExport(TestResponse $response): void
    {
        $response->assertOk();
        $this->assertSame(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            $response->headers->get('content-type'),
        );

        $path = $response->baseResponse->getFile()->getPathname();
        $this->assertFileExists($path);
        unlink($path);
    }

    private function makeDivision(string $name = 'Redaksi', string $code = 'RED'): Division
    {
        return Division::query()->create(['name' => $name, 'code' => $code, 'is_active' => true]);
    }

    private function makeUser(string $roleSlug, ?Division $division = null, bool $active = true): User
    {
        $role = Role::query()->firstOrCreate(['slug' => $roleSlug], ['name' => Str::headline($roleSlug)]);

        return User::query()->create([
            'name' => Str::headline($roleSlug).' '.Str::random(5),
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'role_id' => $role->id,
            'division_id' => $division?->id,
            'is_active' => $active,
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
