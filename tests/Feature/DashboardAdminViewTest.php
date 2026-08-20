<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class DashboardAdminViewTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_admin_dashboard_renders_final_sections_in_the_required_order(): void
    {
        Carbon::setTestNow('2026-08-08 10:00:00');
        $admin = $this->makeAdmin('Ulyatul Ula Kilmi');

        $response = $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('data-testid="dashboard-admin-banner"', false)
            ->assertSee('Sabtu, 8 Agustus 2026')
            ->assertSeeInOrder([
                'Dashboard Admin SIRAPI',
                'Ringkasan administrasi surat internal.',
                'Total Surat Masuk',
                'Baru Diterima',
                'Menunggu Pemeriksaan',
                'Total Surat Keluar',
                'Akses Cepat',
                'Tren Surat (6 Bulan Terakhir)',
                'Surat Masuk Terbaru',
                'Surat Keluar Terbaru',
                'Aktivitas Terbaru',
                'Data Master',
            ]);

        $this->assertSame(4, substr_count($response->getContent(), 'data-testid="dashboard-kpi"'));
        $this->assertSame(9, substr_count($response->getContent(), 'data-testid="dashboard-quick-access"'));

        $css = File::get(resource_path('css/app.css'));

        $this->assertStringContainsString('min-height: 8.5rem;', $css);
        $this->assertStringContainsString('background-position: center, right 22%;', $css);
    }

    public function test_all_quick_access_cards_use_existing_routes(): void
    {
        $admin = $this->makeAdmin();
        $response = $this->actingAs($admin)->get(route('dashboard'))->assertOk();

        foreach ([
            'incoming-letters.create',
            'incoming-letters.index',
            'outgoing-letters.index',
            'dashboard.certificates.index',
            'reports.incoming-letters.index',
            'reports.outgoing-letters.index',
            'reports.certificates.index',
            'users.index',
            'divisions.index',
        ] as $routeName) {
            $response->assertSee('href="'.route($routeName).'"', false);
        }

        $response->assertDontSee('Catat surat masuk baru')
            ->assertDontSee('Lihat semua surat masuk')
            ->assertDontSee('Lihat semua surat keluar')
            ->assertDontSee('Lihat laporan surat masuk')
            ->assertDontSee('Lihat laporan surat keluar')
            ->assertDontSee('Kelola data pengguna')
            ->assertDontSee('Kelola data divisi');
    }

    public function test_quick_access_uses_a_container_responsive_grid_without_forcing_seven_narrow_columns(): void
    {
        $admin = $this->makeAdmin();
        $response = $this->actingAs($admin)->get(route('dashboard'))->assertOk();
        $content = $response->getContent();
        $css = File::get(resource_path('css/app.css'));

        $response
            ->assertSee('class="rs-quick-access-grid"', false)
            ->assertSee('data-testid="dashboard-quick-access"', false)
            ->assertSee('<strong>Divisi</strong>', false);

        $this->assertSame(9, substr_count($content, 'data-testid="dashboard-quick-access"'));
        $this->assertStringContainsString(
            'grid-template-columns: repeat(2, minmax(0, 1fr));',
            $css,
        );
        $this->assertStringContainsString('grid-template-columns: repeat(4, minmax(0, 1fr));', $css);
        $this->assertStringContainsString('grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));', $css);
        $this->assertStringNotContainsString('grid-template-columns: repeat(7,', $css);
    }

    public function test_chart_recent_panels_activity_master_and_empty_states_render_accessibly(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('data-testid="dashboard-trend-section"', false)
            ->assertSee('data-dashboard-trend-chart', false)
            ->assertSee('role="img"', false)
            ->assertSee('aria-label="Diagram garis tren Surat Masuk dan Surat Keluar selama enam bulan terakhir"', false)
            ->assertSee('Belum ada Surat Masuk')
            ->assertSee('Belum ada Surat Keluar')
            ->assertSee('Belum ada Aktivitas')
            ->assertSee('Pengguna Aktif')
            ->assertSee('Pengguna Nonaktif')
            ->assertSee('Divisi Aktif')
            ->assertSee('Total Pengguna')
            ->assertSee('<th scope="col">Perihal</th>', false)
            ->assertDontSee('<th scope="col">Kode Sistem</th>', false);
    }

    public function test_recent_tables_do_not_force_overflow_and_master_actions_stay_secondary(): void
    {
        $admin = $this->makeAdmin();
        $response = $this->actingAs($admin)->get(route('dashboard'))->assertOk();
        $content = $response->getContent();
        $css = File::get(resource_path('css/app.css'));

        $this->assertSame(2, substr_count($content, 'data-testid="dashboard-recent-table"'));
        $this->assertSame(2, substr_count($content, 'class="small fw-semibold text-decoration-none"'));
        $this->assertSame(2, substr_count($content, 'rs-dashboard-master-action'));
        $this->assertMatchesRegularExpression('/\.rs-dashboard-table\s*\{[^}]*min-width:\s*0;/s', $css);
        $this->assertStringNotContainsString('min-width: 620px;', $css);

        $response
            ->assertSee('btn btn-sm btn-outline-secondary rs-dashboard-master-action', false)
            ->assertDontSee('btn btn-primary rs-dashboard-master-action', false);
    }

    public function test_sidebar_has_only_report_collapses_and_dynamic_profile_logout_on_desktop_and_mobile(): void
    {
        $admin = $this->makeAdmin('Ulyatul Ula Kilmi');
        $response = $this->actingAs($admin)->get(route('dashboard'))->assertOk();
        $content = $response->getContent();

        $response->assertSee('data-testid="reports-menu-desktop"', false)
            ->assertSee('data-testid="reports-menu-mobile"', false)
            ->assertSee('data-testid="sidebar-profile-desktop"', false)
            ->assertSee('data-testid="sidebar-profile-mobile"', false)
            ->assertSee('Ulyatul Ula Kilmi')
            ->assertSee('Admin')
            ->assertDontSee('Admin Surat')
            ->assertSee('Keluar')
            ->assertSee('Sertifikat')
            ->assertSee('Pengguna')
            ->assertSee('Divisi');

        $this->assertSame(2, substr_count($content, 'data-bs-toggle="collapse"'));
        $this->assertSame(2, substr_count($content, '>Keluar<'));
        $this->assertSame(2, substr_count($content, 'action="'.route('logout').'"'));

        $desktopMenuPositions = collect([
            'Dashboard',
            'Surat Masuk',
            'Surat Keluar',
            'Sertifikat',
            'Pengguna',
            'Divisi',
            'Laporan',
        ])->map(fn (string $label): int|false => strpos($content, 'data-sidebar-tooltip="'.$label.'"'));

        $this->assertNotContains(false, $desktopMenuPositions->all());
        $this->assertSame($desktopMenuPositions->sort()->values()->all(), $desktopMenuPositions->values()->all());
    }

    public function test_dashboard_omits_prohibited_sections_and_exposes_the_real_notification_bell(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Perlu Perhatian')
            ->assertDontSee('Terkirim')
            ->assertDontSee('Activity Logs')
            ->assertDontSee('Pengaturan')
            ->assertDontSee('Pusat Bantuan')
            ->assertDontSee('Arsipkan')
            ->assertDontSee('Tugas Saya')
            ->assertDontSee('Sembunyikan')
            ->assertSee('data-testid="notification-bell"', false)
            ->assertDontSee('Versi 1.0.0');
    }

    public function test_footer_uses_current_year_without_a_fake_version(): void
    {
        Carbon::setTestNow('2026-08-08 10:00:00');
        $admin = $this->makeAdmin();

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('© 2026 SIRAPI - Jawa Pos Radar Kediri.')
            ->assertDontSee('Versi');
    }

    private function makeAdmin(string $name = 'Admin Dashboard'): User
    {
        $role = Role::query()->create(['name' => 'Admin Surat', 'slug' => 'admin_surat']);

        return User::query()->create([
            'name' => $name,
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'role_id' => $role->id,
            'is_active' => true,
        ]);
    }
}
