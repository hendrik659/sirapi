<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthenticationPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_login_page_is_available(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Login SIRAPI')
            ->assertSee('Sistem Arsip Jawa Pos Radar Kediri')
            ->assertSee('action="'.route('login.store').'"', false)
            ->assertSee('name="_token"', false)
            ->assertSee('name="email"', false)
            ->assertSee('name="password"', false)
            ->assertSee('name="remember"', false)
            ->assertSee('type="password"', false)
            ->assertSee('data-password-toggle', false)
            ->assertSee('aria-pressed="false"', false)
            ->assertSee(asset('images/auth/radar-kediri-building.png'))
            ->assertSee(asset('images/auth/radar-kediri-logo-white.png'))
            ->assertDontSee('Radarsurat');
    }

    public function test_login_brand_assets_exist_in_public_storage(): void
    {
        $this->assertFileExists(public_path('images/auth/radar-kediri-building.png'));
        $this->assertFileExists(public_path('images/auth/radar-kediri-logo-white.png'));
    }

    public function test_login_background_uses_one_shared_blue_canvas_without_a_panel_seam(): void
    {
        $css = File::get(resource_path('css/app.css'));

        $this->assertStringContainsString('--rs-auth-blue-deep: #0a47c8;', $css);
        $this->assertStringContainsString('--rs-auth-blue: #0b50d8;', $css);
        $this->assertStringContainsString('--rs-auth-blue-light: #155ddf;', $css);
        $this->assertMatchesRegularExpression(
            '/\.rs-auth-page\s*\{[^}]*display:\s*grid;[^}]*gap:\s*0;[^}]*background:\s*linear-gradient/s',
            $css,
        );
        $this->assertMatchesRegularExpression(
            '/\.rs-auth-hero::before\s*\{[^}]*background:\s*linear-gradient\([^}]*var\(--rs-auth-blue\)\s*100%/s',
            $css,
        );
        $this->assertMatchesRegularExpression(
            '/\.rs-auth-panel\s*\{[^}]*background:\s*transparent;/s',
            $css,
        );
        $this->assertMatchesRegularExpression(
            '/@media \(max-width:\s*1199\.98px\)\s*\{.*?\.rs-auth-page\s*\{[^}]*display:\s*block;[^}]*#f3f6fb\s*24rem,/s',
            $css,
        );
        $this->assertStringContainsString('min-height: clamp(20rem, 36vh, 23.75rem);', $css);
        $this->assertStringNotContainsString('@media (min-width: 992px) and (max-width: 1199.98px)', $css);
        $this->assertStringContainsString('@media (min-width: 1200px) and (max-height: 700px)', $css);
        $this->assertStringContainsString('@media (max-width: 1199.98px) and (max-height: 500px) and (orientation: landscape)', $css);
        $this->assertStringNotContainsString('linear-gradient(145deg, var(--rs-auth-blue-dark), var(--rs-auth-blue))', $css);
    }

    public function test_the_dashboard_requires_authentication(): void
    {
        $this->get(route('dashboard'))
            ->assertRedirect(route('login'));
    }

    public function test_public_registration_routes_are_unavailable(): void
    {
        $this->get('/daftar')->assertNotFound();

        $this->post('/daftar', [
            'name' => 'Pengguna Baru',
            'email' => 'baru@example.test',
            'password' => 'KataSandi123!',
            'password_confirmation' => 'KataSandi123!',
        ])->assertNotFound();

        $this->assertGuest();
    }

    public function test_an_active_account_can_login(): void
    {
        $user = $this->makeLoginUser();

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_an_account_cannot_login_with_an_invalid_password(): void
    {
        $user = $this->makeLoginUser();

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'salah-password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_an_inactive_account_cannot_login(): void
    {
        $user = $this->makeLoginUser(['is_active' => false]);

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_an_authenticated_user_can_logout(): void
    {
        $user = $this->makeLoginUser();

        $this->actingAs($user)
            ->post(route('logout'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    private function makeLoginUser(array $attributes = []): User
    {
        $role = Role::query()->create([
            'name' => 'Admin Surat',
            'slug' => 'admin_surat',
        ]);

        $user = new User;
        $user->forceFill(array_merge([
            'name' => 'Admin Login',
            'email' => 'admin.login@example.test',
            'password' => Hash::make('password'),
            'role_id' => $role->id,
            'is_active' => true,
        ], $attributes));
        $user->save();

        return $user;
    }
}
