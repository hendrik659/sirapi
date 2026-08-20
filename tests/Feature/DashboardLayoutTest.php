<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class DashboardLayoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_shared_layout_exposes_desktop_sidebar_and_mobile_offcanvas_controls_accessibly(): void
    {
        $admin = $this->makeAdmin();
        $response = $this->actingAs($admin)->get(route('dashboard'))->assertOk();
        $content = $response->getContent();

        $response
            ->assertSee('SIRAPI')
            ->assertSee('Sistem Arsip Jawa Pos Radar Kediri')
            ->assertSee(asset('images/auth/radar-kediri-logo-white.png'))
            ->assertSee('data-testid="desktop-sidebar"', false)
            ->assertSee('id="rsDesktopSidebar"', false)
            ->assertSee('data-testid="dashboard-main-wrapper"', false)
            ->assertSee('data-testid="dashboard-global-header"', false)
            ->assertSee('data-testid="desktop-sidebar-toggle"', false)
            ->assertSee('data-desktop-sidebar-toggle', false)
            ->assertSee('aria-controls="rsDesktopSidebar"', false)
            ->assertSee('aria-expanded="true"', false)
            ->assertSee('aria-label="Ciutkan sidebar"', false)
            ->assertSee('id="rsMobileSidebar"', false)
            ->assertSee('data-testid="mobile-sidebar-toggle"', false)
            ->assertSee('data-bs-toggle="offcanvas"', false)
            ->assertSee('data-bs-target="#rsMobileSidebar"', false)
            ->assertSee('aria-controls="rsMobileSidebar"', false)
            ->assertSee('aria-expanded="false"', false)
            ->assertSee('aria-label="Buka menu navigasi"', false)
            ->assertDontSee('sticky-top', false);

        $this->assertLessThan(
            strpos($content, 'data-testid="dashboard-main-wrapper"'),
            strpos($content, 'data-testid="desktop-sidebar"'),
        );
        $this->assertLessThan(
            strpos($content, 'data-testid="dashboard-global-header"'),
            strpos($content, 'data-testid="dashboard-main-wrapper"'),
        );
    }

    public function test_collapsed_desktop_controls_keep_accessible_names_and_semantic_logout(): void
    {
        $admin = $this->makeAdmin('Ulyatul Ula Kilmi');
        $response = $this->actingAs($admin)->get(route('dashboard'))->assertOk();
        $content = $response->getContent();

        foreach (['Dashboard', 'Surat Masuk', 'Surat Keluar', 'Laporan', 'Pengguna', 'Divisi', 'Keluar'] as $label) {
            $response
                ->assertSee('data-sidebar-tooltip="'.$label.'"', false)
                ->assertSee('aria-label="'.$label.'"', false);
        }

        $response
            ->assertSee('data-sidebar-tooltip="Ulyatul Ula Kilmi — Admin"', false)
            ->assertSee('aria-label="Ulyatul Ula Kilmi — Admin"', false)
            ->assertDontSee('Admin Surat')
            ->assertSee('tabindex="0"', false)
            ->assertSee('<form method="POST" action="'.route('logout').'">', false)
            ->assertSee('type="submit"', false)
            ->assertSee("localStorage.getItem('rs-sidebar-state')", false)
            ->assertSee("classList.add('rs-sidebar-collapsed')", false);

        $this->assertSame(2, substr_count($content, 'data-bs-toggle="collapse"'));
        $this->assertSame(2, substr_count($content, 'action="'.route('logout').'"'));
    }

    public function test_representative_internal_pages_all_render_the_global_dashboard_layout(): void
    {
        $admin = $this->makeAdmin();

        foreach ([
            'dashboard',
            'incoming-letters.index',
            'incoming-letters.create',
            'outgoing-letters.index',
            'reports.incoming-letters.index',
            'reports.outgoing-letters.index',
            'users.index',
            'users.create',
            'divisions.index',
            'divisions.create',
        ] as $routeName) {
            $this->actingAs($admin)
                ->get(route($routeName))
                ->assertOk()
                ->assertSee('data-testid="desktop-sidebar"', false)
                ->assertSee('data-testid="dashboard-main-wrapper"', false)
                ->assertSee('data-testid="dashboard-global-header"', false)
                ->assertSee('id="rsMobileSidebar"', false);
        }
    }

    public function test_layout_assets_keep_fixed_sidebar_and_document_scroll_contracts(): void
    {
        $css = File::get(resource_path('css/app.css'));
        $javascript = File::get(resource_path('js/dashboard-layout.js'));

        $this->assertStringContainsString('--rs-sidebar-width: 272px;', $css);
        $this->assertStringContainsString('--rs-sidebar-collapsed-width: 82px;', $css);
        $this->assertStringContainsString('--rs-content-max: 1720px;', $css);
        $this->assertMatchesRegularExpression('/\.rs-sidebar\s*\{[^}]*position:\s*fixed;[^}]*height:\s*100vh;/s', $css);
        $this->assertMatchesRegularExpression('/\.rs-main-wrapper\s*\{[^}]*margin-left:\s*var\(--rs-sidebar-width\);/s', $css);
        $this->assertMatchesRegularExpression('/\.rs-sidebar-collapsed \.rs-main-wrapper\s*\{[^}]*margin-left:\s*var\(--rs-sidebar-collapsed-width\);/s', $css);
        $this->assertMatchesRegularExpression('/@media \(max-width:\s*991\.98px\)\s*\{[^}]*\.rs-main-wrapper[^}]*margin-left:\s*0;/s', $css);

        preg_match('/\.rs-main\s*\{(?<rules>[^}]*)\}/s', $css, $mainRules);
        preg_match('/\.rs-navbar\s*\{(?<rules>[^}]*)\}/s', $css, $navbarRules);

        $this->assertStringNotContainsString('overflow-y', $mainRules['rules']);
        $this->assertStringNotContainsString('height: 100vh', $mainRules['rules']);
        $this->assertStringNotContainsString('position: fixed', $navbarRules['rules']);
        $this->assertStringNotContainsString('position: sticky', $navbarRules['rules']);
        $this->assertStringContainsString("localStorage.setItem(storageKey, collapsed ? 'collapsed' : 'expanded')", $javascript);
        $this->assertStringContainsString("trigger: 'hover focus'", $javascript);
        $this->assertStringContainsString('Collapse.getOrCreateInstance', $javascript);
    }

    private function makeAdmin(string $name = 'Admin Layout'): User
    {
        $role = Role::query()->create([
            'name' => 'Admin Surat',
            'slug' => 'admin_surat',
        ]);

        return User::query()->create([
            'name' => $name,
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'role_id' => $role->id,
            'is_active' => true,
        ]);
    }
}
