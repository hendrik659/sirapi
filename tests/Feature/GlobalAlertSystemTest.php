<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class GlobalAlertSystemTest extends TestCase
{
    use RefreshDatabase;

    public function test_shared_layout_maps_and_stacks_all_global_flash_types_as_toasts(): void
    {
        $response = $this
            ->withSession([
                'success' => 'Operasi berhasil.',
                'error' => 'Operasi gagal.',
                'warning' => 'Periksa kembali data.',
                'info' => 'Informasi terbaru.',
            ])
            ->actingAs($this->makeAdmin())
            ->get(route('dashboard'))
            ->assertOk();

        foreach ([
            'success' => ['Berhasil', 'fa-circle-check', '5000', 'status', 'polite'],
            'error' => ['Gagal', 'fa-circle-exclamation', '9000', 'alert', 'assertive'],
            'warning' => ['Perhatian', 'fa-triangle-exclamation', '7000', 'alert', 'assertive'],
            'info' => ['Informasi', 'fa-circle-info', '5000', 'status', 'polite'],
        ] as $type => [$title, $icon, $delay, $role, $live]) {
            $response
                ->assertSee('data-testid="global-toast-'.$type.'"', false)
                ->assertSee('class="toast rs-toast rs-toast-'.$type.'"', false)
                ->assertSee('role="'.$role.'"', false)
                ->assertSee('aria-live="'.$live.'"', false)
                ->assertSee('data-bs-delay="'.$delay.'"', false)
                ->assertSee($title)
                ->assertSee($icon, false);
        }

        $response
            ->assertSee('data-global-toast-container', false)
            ->assertSee('data-bs-dismiss="toast"', false);
    }

    public function test_global_confirmation_modal_and_modular_javascript_replace_native_confirm(): void
    {
        $response = $this->actingAs($this->makeAdmin())->get(route('dashboard'))->assertOk();
        $javascriptFiles = File::allFiles(resource_path('js'));
        $javascript = collect($javascriptFiles)
            ->map(fn ($file) => File::get($file->getPathname()))
            ->implode("\n");

        $response
            ->assertSee('id="rsConfirmationModal"', false)
            ->assertSee('aria-labelledby="rsConfirmationModalTitle"', false)
            ->assertSee('aria-describedby="rsConfirmationModalMessage"', false)
            ->assertSee('data-confirmation-submit', false)
            ->assertSee('Batal');

        $this->assertStringContainsString("import './global-alerts';", File::get(resource_path('js/app.js')));
        $this->assertStringContainsString("form[data-confirmation]", $javascript);
        $this->assertStringNotContainsString('window.confirm(', $javascript);
        $this->assertStringNotContainsString('window.alert(', $javascript);
    }

    public function test_form_validation_remains_inline_and_does_not_become_a_toast(): void
    {
        $this->actingAs($this->makeAdmin())
            ->from(route('users.create'))
            ->post(route('users.store'), [])
            ->assertRedirect(route('users.create'))
            ->assertSessionHasErrors('name');

        $response = $this->get(route('users.create'))->assertOk();

        $response
            ->assertSee('name="name"', false)
            ->assertSee('is-invalid', false)
            ->assertSee('class="invalid-feedback"', false)
            ->assertDontSee('data-global-toast', false);
    }

    public function test_login_errors_stay_inside_the_login_card(): void
    {
        $this->post(route('login.store'), [
            'email' => 'bukan-email',
            'password' => '',
        ])->assertSessionHasErrors();

        $this->get(route('login'))
            ->assertOk()
            ->assertSee('alert alert-danger', false)
            ->assertSee('role="alert"', false)
            ->assertDontSee('data-global-toast', false)
            ->assertDontSee('rsConfirmationModal', false);
    }

    private function makeAdmin(): User
    {
        $role = Role::query()->firstOrCreate(
            ['slug' => 'admin_surat'],
            ['name' => 'Admin Surat'],
        );

        return User::query()->create([
            'name' => 'Admin Alert',
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'role_id' => $role->id,
            'is_active' => true,
        ]);
    }
}
