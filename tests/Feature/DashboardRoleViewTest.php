<?php

namespace Tests\Feature;

use App\Models\Division;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardRoleViewTest extends TestCase
{
    use RefreshDatabase;

    public function test_each_supported_role_receives_the_correct_dashboard_banner_and_quick_access(): void
    {
        $sdm = $this->makeDivision('Sumber Daya Manusia', 'SDM');
        $editorial = $this->makeDivision('Editorial', 'EDT');
        $cases = [
            [$this->makeUser('admin_surat'), 'Dashboard Admin SIRAPI', 9, true, true],
            [$this->makeUser('pimpinan'), 'Dashboard Pimpinan', 6, true, false],
            [$this->makeUser('ketua_divisi', $sdm), 'Dashboard Ketua Divisi', 6, true, false],
            [$this->makeUser('ketua_divisi', $editorial), 'Dashboard Ketua Divisi', 4, false, false],
            [$this->makeUser('anggota_divisi', $editorial), 'Dashboard Anggota Divisi', 4, false, false],
        ];

        foreach ($cases as [$user, $bannerTitle, $quickAccessCount, $canViewCertificates, $canManageMasterData]) {
            $response = $this->actingAs($user)
                ->get(route('dashboard'))
                ->assertOk()
                ->assertSee($bannerTitle)
                ->assertSee('Akses Cepat')
                ->assertSee('href="'.route('incoming-letters.index').'"', false)
                ->assertSee('href="'.route('outgoing-letters.index').'"', false)
                ->assertSee('href="'.route('reports.incoming-letters.index').'"', false)
                ->assertSee('href="'.route('reports.outgoing-letters.index').'"', false);

            $this->assertSame($quickAccessCount, substr_count($response->getContent(), 'data-testid="dashboard-quick-access"'));

            if ($canViewCertificates) {
                $response
                    ->assertSee('href="'.route('dashboard.certificates.index').'"', false)
                    ->assertSee('href="'.route('reports.certificates.index').'"', false);
            } else {
                $response
                    ->assertDontSee('href="'.route('dashboard.certificates.index').'"', false)
                    ->assertDontSee('href="'.route('reports.certificates.index').'"', false);
            }

            if ($canManageMasterData) {
                $response
                    ->assertSee('href="'.route('users.index').'"', false)
                    ->assertSee('href="'.route('divisions.index').'"', false);
            } else {
                $response
                    ->assertDontSee('href="'.route('users.index').'"', false)
                    ->assertDontSee('href="'.route('divisions.index').'"', false);
            }
        }
    }

    public function test_division_roles_without_a_division_do_not_receive_report_navigation_or_quick_access(): void
    {
        foreach (['ketua_divisi', 'anggota_divisi'] as $roleSlug) {
            $response = $this->actingAs($this->makeUser($roleSlug))
                ->get(route('dashboard'))
                ->assertOk()
                ->assertDontSee('data-testid="reports-menu-desktop"', false)
                ->assertDontSee('data-testid="reports-menu-mobile"', false)
                ->assertDontSee('href="'.route('reports.incoming-letters.index').'"', false)
                ->assertDontSee('href="'.route('reports.outgoing-letters.index').'"', false);

            $this->assertSame(2, substr_count($response->getContent(), 'data-testid="dashboard-quick-access"'));
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

    private function makeUser(string $roleSlug, ?Division $division = null): User
    {
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
            'is_active' => true,
        ]);
    }
}
