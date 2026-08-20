<?php

namespace Tests\Feature;

use App\Models\Division;
use App\Models\IncomingLetter;
use App\Models\IncomingLetterStatusHistory;
use App\Models\OutgoingLetter;
use App\Models\OutgoingLetterHistory;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

class DashboardAdminBackendTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_guest_is_redirected_inactive_user_is_blocked_and_admin_can_access(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));

        $inactive = $this->makeUser('admin_surat', false);
        $this->actingAs($inactive)
            ->get(route('dashboard'))
            ->assertRedirect(route('login'));
        $this->assertGuest();

        $admin = $this->makeUser('admin_surat');
        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertViewIs('dashboard.index')
            ->assertViewHas('isAdminDashboard', true);
    }

    public function test_non_admin_role_keeps_a_separate_placeholder_without_admin_data(): void
    {
        $member = $this->makeUser('anggota_divisi');

        $this->actingAs($member)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertViewHas('isAdminDashboard', false)
            ->assertViewMissing('totalIncomingLetters');
    }

    public function test_letter_kpis_and_master_statistics_use_database_counts(): void
    {
        $admin = $this->makeUser('admin_surat');
        $activeUser = $this->makeUser('anggota_divisi');
        $this->makeUser('pimpinan', false);
        $softDeletedUser = $this->makeUser('ketua_divisi');
        $softDeletedUser->delete();
        $activeDivision = $this->makeDivision('Redaksi', 'RED');
        $this->makeDivision('Divisi Nonaktif', 'NON', false);

        $this->makeIncoming($admin, $activeDivision, ['status' => IncomingLetter::STATUS_BARU_DITERIMA]);
        $this->makeIncoming($admin, $activeDivision, ['status' => IncomingLetter::STATUS_BARU_DITERIMA]);
        $this->makeIncoming($admin, $activeDivision, ['status' => IncomingLetter::STATUS_MENUNGGU_PEMERIKSAAN]);
        $this->makeIncoming($admin, $activeDivision, ['status' => IncomingLetter::STATUS_SELESAI]);
        $this->makeOutgoing($activeUser, $activeDivision);
        $this->makeOutgoing($activeUser, $activeDivision);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertViewHas('totalIncomingLetters', 4)
            ->assertViewHas('newIncomingLetters', 2)
            ->assertViewHas('waitingReviewIncomingLetters', 1)
            ->assertViewHas('totalOutgoingLetters', 2)
            ->assertViewHas('activeUsers', 2)
            ->assertViewHas('inactiveUsers', 1)
            ->assertViewHas('totalUsers', 3)
            ->assertViewHas('activeDivisions', 1);
    }

    public function test_recent_incoming_and_outgoing_are_limited_to_five_and_deterministically_ordered(): void
    {
        $admin = $this->makeUser('admin_surat');
        $division = $this->makeDivision();
        $incoming = [];
        $outgoing = [];

        foreach ([
            '2026-07-01',
            '2026-07-02',
            '2026-08-01',
            '2026-08-02',
            '2026-08-03',
            '2026-08-04',
            '2026-08-04',
        ] as $index => $date) {
            $incoming[] = $this->makeIncoming($admin, $division, [
                'agenda_number' => 'AGD-RECENT-'.$index,
                'received_date' => $date,
            ]);
            $outgoing[] = $this->makeOutgoing($admin, $division, [
                'reference_code' => 'SK-RECENT-'.$index,
                'letter_number' => 'NO-RECENT-'.$index,
                'letter_date' => $date,
            ]);
        }

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertViewHas('recentIncomingLetters', function (Collection $letters) use ($incoming): bool {
                return $letters->pluck('id')->all() === [
                    $incoming[6]->id,
                    $incoming[5]->id,
                    $incoming[4]->id,
                    $incoming[3]->id,
                    $incoming[2]->id,
                ];
            })
            ->assertViewHas('recentOutgoingLetters', function (Collection $letters) use ($outgoing): bool {
                return $letters->pluck('id')->all() === [
                    $outgoing[6]->id,
                    $outgoing[5]->id,
                    $outgoing[4]->id,
                    $outgoing[3]->id,
                    $outgoing[2]->id,
                ];
            });
    }

    public function test_six_month_trend_uses_business_dates_and_zero_fills_missing_months(): void
    {
        Carbon::setTestNow('2026-08-08 10:00:00');
        $admin = $this->makeUser('admin_surat');
        $division = $this->makeDivision();

        foreach (['2026-03-01', '2026-03-25', '2026-05-12', '2026-08-01', '2026-08-02', '2026-08-08'] as $date) {
            $this->makeIncoming($admin, $division, ['received_date' => $date]);
        }
        $this->makeIncoming($admin, $division, ['received_date' => '2026-02-28']);
        $this->makeIncoming($admin, $division, ['received_date' => '2026-09-01']);

        foreach (['2026-04-10', '2026-08-02', '2026-08-07'] as $date) {
            $this->makeOutgoing($admin, $division, ['letter_date' => $date]);
        }
        $this->makeOutgoing($admin, $division, ['letter_date' => '2026-02-01']);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertViewHas('sixMonthLabels', ["Mar '26", "Apr '26", "Mei '26", "Jun '26", "Jul '26", "Agu '26"])
            ->assertViewHas('sixMonthIncomingTrend', [2, 0, 1, 0, 0, 3])
            ->assertViewHas('sixMonthOutgoingTrend', [0, 1, 0, 0, 0, 2]);
    }

    public function test_recent_activities_merge_existing_histories_order_by_time_and_include_actor(): void
    {
        Carbon::setTestNow('2026-08-08 12:00:00');
        $admin = $this->makeUser('admin_surat');
        $division = $this->makeDivision();
        $incoming = $this->makeIncoming($admin, $division, ['agenda_number' => 'AGD-ACTIVITY']);
        $outgoing = $this->makeOutgoing($admin, $division, ['reference_code' => 'SK-ACTIVITY']);

        foreach (range(1, 4) as $number) {
            IncomingLetterStatusHistory::query()->forceCreate([
                'incoming_letter_id' => $incoming->id,
                'previous_status' => null,
                'new_status' => IncomingLetter::STATUS_BARU_DITERIMA,
                'activity' => 'Aktivitas Masuk '.$number,
                'changed_by' => $admin->id,
                'created_at' => now()->subMinutes(20 - $number),
            ]);
        }

        foreach (range(1, 3) as $number) {
            OutgoingLetterHistory::query()->forceCreate([
                'outgoing_letter_id' => $outgoing->id,
                'activity' => 'Aktivitas Keluar '.$number,
                'changed_by' => $admin->id,
                'created_at' => now()->subMinutes(10 - $number),
            ]);
        }

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertViewHas('recentActivities', function (Collection $activities) use ($admin): bool {
                return $activities->count() === 5
                    && $activities->pluck('activity')->all() === [
                        'Aktivitas Keluar 3',
                        'Aktivitas Keluar 2',
                        'Aktivitas Keluar 1',
                        'Aktivitas Masuk 4',
                        'Aktivitas Masuk 3',
                    ]
                    && $activities->every(fn (array $activity): bool => $activity['actor'] === $admin->name)
                    && $activities->first()['reference'] === 'SK-ACTIVITY';
            });
    }

    public function test_dashboard_route_remains_read_only_and_workflow_routes_are_not_added(): void
    {
        $dashboard = Route::getRoutes()->getByName('dashboard');

        $this->assertSame(['GET', 'HEAD'], $dashboard->methods());
        $this->assertFalse(Route::has('dashboard.store'));
        $this->assertFalse(Route::has('dashboard.update'));
        $this->assertFalse(Route::has('dashboard.destroy'));
        $this->assertFalse(Route::has('outgoing-letters.update'));
        $this->assertFalse(Route::has('outgoing-letters.archive'));
    }

    private function makeDivision(string $name = 'Redaksi', string $code = 'RED', bool $active = true): Division
    {
        return Division::query()->create(['name' => $name, 'code' => $code, 'is_active' => $active]);
    }

    private function makeUser(string $roleSlug, bool $active = true): User
    {
        $role = Role::query()->firstOrCreate(['slug' => $roleSlug], ['name' => Str::headline($roleSlug)]);

        return User::query()->create([
            'name' => Str::headline($roleSlug).' '.Str::random(4),
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'role_id' => $role->id,
            'is_active' => $active,
        ]);
    }

    /** @param array<string, mixed> $overrides */
    private function makeIncoming(User $creator, Division $division, array $overrides = []): IncomingLetter
    {
        return IncomingLetter::query()->create(array_merge([
            'agenda_number' => 'AGD-'.Str::uuid(),
            'letter_number' => 'SM-'.Str::uuid(),
            'sender_name' => 'Instansi Pengirim',
            'addressed_to' => 'Radar Kediri',
            'letter_date' => '2026-08-01',
            'received_date' => '2026-08-08',
            'received_via' => 'fisik',
            'subject' => 'Perihal Surat Masuk',
            'priority' => 'biasa',
            'destination_division_id' => $division->id,
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
            'letter_date' => '2026-08-08',
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
