<?php

namespace Tests\Feature;

use App\Models\Division;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DivisionBackendTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('divisions.index'))
            ->assertRedirect(route('login'));
    }

    public function test_admin_surat_can_store_a_normalized_division(): void
    {
        $admin = $this->makeUser('admin_surat', 'Admin Surat');

        $response = $this->actingAs($admin)->post(route('divisions.store'), [
            'name' => '  Redaksi Digital  ',
            'code' => '  rd-01  ',
        ]);

        $division = Division::query()->firstOrFail();

        $response
            ->assertRedirect(route('divisions.show', $division))
            ->assertSessionHas('success', 'Data divisi berhasil ditambahkan.');

        $this->assertDatabaseHas('divisions', [
            'id' => $division->id,
            'name' => 'Redaksi Digital',
            'code' => 'RD-01',
            'is_active' => true,
        ]);
    }

    public function test_non_admin_role_is_forbidden(): void
    {
        $member = $this->makeUser('anggota_divisi', 'Anggota Divisi');

        $this->actingAs($member)
            ->get(route('divisions.index'))
            ->assertForbidden();
    }

    public function test_inactive_account_cannot_access_division_backend(): void
    {
        $admin = $this->makeUser('admin_surat', 'Admin Nonaktif', false);

        $this->actingAs($admin)
            ->get(route('divisions.index'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_name_and_code_are_required(): void
    {
        $admin = $this->makeUser('admin_surat', 'Admin Surat');

        $this->actingAs($admin)
            ->post(route('divisions.store'))
            ->assertSessionHasErrors(['name', 'code']);

        $this->assertDatabaseCount('divisions', 0);
    }

    public function test_store_ignores_submitted_inactive_status(): void
    {
        $admin = $this->makeUser('admin_surat', 'Admin Surat');

        $this->actingAs($admin)
            ->post(route('divisions.store'), [
                'name' => 'Editorial',
                'code' => 'EDT',
                'is_active' => '0',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('divisions', [
            'name' => 'Editorial',
            'code' => 'EDT',
            'is_active' => true,
        ]);
    }

    public function test_duplicate_name_is_rejected_after_trimming(): void
    {
        $admin = $this->makeUser('admin_surat', 'Admin Surat');
        Division::query()->create($this->validDivisionData());

        $this->actingAs($admin)
            ->post(route('divisions.store'), [
                'name' => '  Editorial  ',
                'code' => 'OTHER',
            ])
            ->assertSessionHasErrors('name');

        $this->assertDatabaseCount('divisions', 1);
    }

    public function test_duplicate_code_is_rejected_after_uppercase_normalization(): void
    {
        $admin = $this->makeUser('admin_surat', 'Admin Surat');
        Division::query()->create($this->validDivisionData());

        $this->actingAs($admin)
            ->post(route('divisions.store'), [
                'name' => 'Marketing',
                'code' => 'edt',
            ])
            ->assertSessionHasErrors('code');

        $this->assertDatabaseCount('divisions', 1);
    }

    public function test_code_with_invalid_characters_is_rejected(): void
    {
        $admin = $this->makeUser('admin_surat', 'Admin Surat');

        $this->actingAs($admin)
            ->post(route('divisions.store'), [
                'name' => 'Editorial',
                'code' => 'EDT 01!',
            ])
            ->assertSessionHasErrors('code');

        $this->assertDatabaseCount('divisions', 0);
    }

    public function test_admin_can_update_and_normalize_division_data(): void
    {
        $admin = $this->makeUser('admin_surat', 'Admin Surat');
        $division = Division::query()->create($this->validDivisionData());

        $response = $this->actingAs($admin)->put(route('divisions.update', $division), [
            'name' => '  Konten Kreatif  ',
            'code' => '  kk_02  ',
            'is_active' => '0',
        ]);

        $response
            ->assertRedirect(route('divisions.show', $division))
            ->assertSessionHas('success', 'Data divisi berhasil diperbarui.');

        $this->assertDatabaseHas('divisions', [
            'id' => $division->id,
            'name' => 'Konten Kreatif',
            'code' => 'KK_02',
            'is_active' => true,
        ]);
    }

    public function test_update_ignores_unique_rules_for_the_same_division(): void
    {
        $admin = $this->makeUser('admin_surat', 'Admin Surat');
        $division = Division::query()->create($this->validDivisionData());

        $this->actingAs($admin)
            ->put(route('divisions.update', $division), $this->validDivisionData())
            ->assertRedirect(route('divisions.show', $division))
            ->assertSessionDoesntHaveErrors(['name', 'code']);
    }

    public function test_update_rejects_name_and_code_owned_by_another_division(): void
    {
        $admin = $this->makeUser('admin_surat', 'Admin Surat');
        $division = Division::query()->create($this->validDivisionData());
        $other = Division::query()->create([
            'name' => 'Marketing',
            'code' => 'MKT',
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->put(route('divisions.update', $division), [
                'name' => $other->name,
                'code' => $other->code,
            ])
            ->assertSessionHasErrors(['name', 'code']);

        $this->assertDatabaseHas('divisions', [
            'id' => $division->id,
            'name' => 'Editorial',
            'code' => 'EDT',
        ]);
    }

    public function test_division_without_active_users_can_be_deactivated_and_reactivated(): void
    {
        $admin = $this->makeUser('admin_surat', 'Admin Surat');
        $division = Division::query()->create($this->validDivisionData());

        $this->actingAs($admin)
            ->from(route('divisions.index'))
            ->patch(route('divisions.status', $division), ['is_active' => '0'])
            ->assertRedirect(route('divisions.index'))
            ->assertSessionHas('success', 'Data divisi berhasil dinonaktifkan.');

        $this->assertFalse($division->fresh()->is_active);

        $this->actingAs($admin)
            ->from(route('divisions.index'))
            ->patch(route('divisions.status', $division), ['is_active' => '1'])
            ->assertRedirect(route('divisions.index'))
            ->assertSessionHas('success', 'Data divisi berhasil diaktifkan.');

        $this->assertTrue($division->fresh()->is_active);
    }

    public function test_active_users_prevent_status_deactivation(): void
    {
        $admin = $this->makeUser('admin_surat', 'Admin Surat');
        $division = Division::query()->create($this->validDivisionData());
        $this->makeUser('anggota_divisi', 'Pengguna Aktif', true, $division);

        $this->actingAs($admin)
            ->patch(route('divisions.status', $division), ['is_active' => '0'])
            ->assertUnprocessable();

        $this->assertTrue($division->fresh()->is_active);
    }

    public function test_full_update_ignores_status_for_a_division_with_active_users(): void
    {
        $admin = $this->makeUser('admin_surat', 'Admin Surat');
        $division = Division::query()->create($this->validDivisionData());
        $this->makeUser('anggota_divisi', 'Pengguna Aktif', true, $division);

        $this->actingAs($admin)
            ->put(route('divisions.update', $division), [
                'name' => 'Nama Baru',
                'code' => 'NEW',
                'is_active' => '0',
            ])
            ->assertRedirect(route('divisions.show', $division));

        $this->assertDatabaseHas('divisions', [
            'id' => $division->id,
            'name' => 'Nama Baru',
            'code' => 'NEW',
            'is_active' => true,
        ]);
    }

    public function test_inactive_users_do_not_prevent_deactivation(): void
    {
        $admin = $this->makeUser('admin_surat', 'Admin Surat');
        $division = Division::query()->create($this->validDivisionData());
        $this->makeUser('anggota_divisi', 'Pengguna Nonaktif', false, $division);

        $this->actingAs($admin)
            ->patch(route('divisions.status', $division), ['is_active' => '0'])
            ->assertRedirect();

        $this->assertFalse($division->fresh()->is_active);
    }

    public function test_destroy_route_does_not_exist_and_delete_does_not_remove_division(): void
    {
        $admin = $this->makeUser('admin_surat', 'Admin Surat');
        $division = Division::query()->create($this->validDivisionData());

        $this->assertFalse(collect(app('router')->getRoutes())->contains(
            fn ($route) => $route->getName() === 'divisions.destroy',
        ));

        $this->actingAs($admin)
            ->delete('/dashboard/divisions/'.$division->id)
            ->assertMethodNotAllowed();

        $this->assertDatabaseHas('divisions', ['id' => $division->id]);
    }

    /**
     * @return array{name: string, code: string, is_active: bool}
     */
    private function validDivisionData(): array
    {
        return [
            'name' => 'Editorial',
            'code' => 'EDT',
            'is_active' => true,
        ];
    }

    private function makeUser(
        string $roleSlug,
        string $name,
        bool $isActive = true,
        ?Division $division = null,
    ): User {
        $role = Role::query()->firstOrCreate(
            ['slug' => $roleSlug],
            ['name' => str($roleSlug)->replace('_', ' ')->title()->toString()],
        );

        $user = new User;
        $user->forceFill([
            'name' => $name,
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'role_id' => $role->id,
            'division_id' => $division?->id,
            'is_active' => $isActive,
        ]);
        $user->save();

        return $user;
    }
}
