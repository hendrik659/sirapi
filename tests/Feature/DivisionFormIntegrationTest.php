<?php

namespace Tests\Feature;

use App\Models\Division;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DivisionSeeder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DivisionFormIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_create_division_form_without_status_input(): void
    {
        $response = $this->actingAs($this->makeAdmin())
            ->get(route('divisions.create'));

        $response
            ->assertOk()
            ->assertViewIs('divisions.form')
            ->assertSee('Tambah Divisi')
            ->assertSee('Tambahkan data divisi baru ke dalam sistem.')
            ->assertSee('action="'.route('divisions.store').'"', false)
            ->assertSee('name="name"', false)
            ->assertSee('name="code"', false)
            ->assertSee('Simpan')
            ->assertDontSee('name="is_active"', false);
    }

    public function test_admin_can_open_edit_division_form_with_existing_values_and_no_status_input(): void
    {
        $division = $this->makeDivision('Redaksi Digital', 'RED');

        $response = $this->actingAs($this->makeAdmin())
            ->get(route('divisions.edit', $division));

        $response
            ->assertOk()
            ->assertViewIs('divisions.form')
            ->assertSee('Edit Divisi')
            ->assertSee('Perbarui nama atau kode divisi.')
            ->assertSee('action="'.route('divisions.update', $division).'"', false)
            ->assertSee('name="_method" value="PUT"', false)
            ->assertSee('value="Redaksi Digital"', false)
            ->assertSee('value="RED"', false)
            ->assertSee('Simpan Perubahan')
            ->assertDontSee('name="is_active"', false);
    }

    public function test_division_validation_preserves_normalized_old_input(): void
    {
        $response = $this->actingAs($this->makeAdmin())
            ->from(route('divisions.create'))
            ->post(route('divisions.store'), [
                'name' => '  Divisi Percobaan  ',
                'code' => ' kode salah ',
            ]);

        $response
            ->assertRedirect(route('divisions.create'))
            ->assertSessionHasErrors('code')
            ->assertSessionHasInput('name', 'Divisi Percobaan')
            ->assertSessionHasInput('code', 'KODE SALAH');
    }

    public function test_update_ignores_attempt_to_activate_an_inactive_division(): void
    {
        $division = $this->makeDivision('Divisi Lama', 'DLM', false);

        $this->actingAs($this->makeAdmin())
            ->put(route('divisions.update', $division), [
                'name' => 'Divisi Diperbarui',
                'code' => 'DBR',
                'is_active' => '1',
            ])
            ->assertRedirect(route('divisions.show', $division));

        $this->assertDatabaseHas('divisions', [
            'id' => $division->id,
            'name' => 'Divisi Diperbarui',
            'code' => 'DBR',
            'is_active' => false,
        ]);
    }

    public function test_division_seeder_creates_the_seven_official_active_divisions(): void
    {
        $this->seed(DivisionSeeder::class);

        $expectedDivisions = [
            'PEM' => 'Pemasaran',
            'KEU' => 'Keuangan',
            'IKL' => 'Iklan',
            'RED' => 'Redaksi',
            'OFF' => 'Offprint',
            'PCT' => 'Pracetak',
            'SDM' => 'SDM & Umum',
        ];

        $this->assertDatabaseCount('divisions', 7);

        foreach ($expectedDivisions as $code => $name) {
            $this->assertDatabaseHas('divisions', [
                'code' => $code,
                'name' => $name,
                'is_active' => true,
            ]);
        }
    }

    public function test_division_seeder_is_idempotent_and_preserves_inactive_status_while_syncing_name(): void
    {
        $this->seed(DivisionSeeder::class);

        Division::query()
            ->where('code', 'PEM')
            ->update([
                'name' => 'Nama Lama Pemasaran',
                'is_active' => false,
            ]);

        $this->seed(DivisionSeeder::class);

        $this->assertDatabaseCount('divisions', 7);
        $this->assertDatabaseHas('divisions', [
            'code' => 'PEM',
            'name' => 'Pemasaran',
            'is_active' => false,
        ]);
    }

    public function test_database_seeder_registers_roles_and_divisions(): void
    {
        $this->seed();

        $this->assertDatabaseCount('roles', 4);
        $this->assertDatabaseCount('divisions', 7);
    }

    public function test_create_user_form_only_exposes_active_divisions(): void
    {
        $activeDivision = $this->makeDivision('Redaksi', 'RED');
        $inactiveDivision = $this->makeDivision('Divisi Nonaktif', 'NON', false);

        $this->actingAs($this->makeAdmin())
            ->get(route('users.create'))
            ->assertOk()
            ->assertSee('data-testid="user-division-select"', false)
            ->assertViewHas('divisions', function (Collection $divisions) use ($activeDivision, $inactiveDivision) {
                return $divisions->contains('id', $activeDivision->id)
                    && ! $divisions->contains('id', $inactiveDivision->id);
            });
    }

    public function test_edit_user_form_exposes_active_and_current_inactive_division_only(): void
    {
        $activeDivision = $this->makeDivision('Redaksi', 'RED');
        $currentInactiveDivision = $this->makeDivision('Divisi Lama', 'DLM', false);
        $otherInactiveDivision = $this->makeDivision('Divisi Nonaktif Lain', 'DNL', false);
        $memberRole = $this->makeRole('anggota_divisi', 'Anggota Divisi');
        $user = $this->makeUser(
            $memberRole,
            'Pengguna Divisi Lama',
            $currentInactiveDivision,
        );

        $this->actingAs($this->makeAdmin())
            ->get(route('users.edit', $user))
            ->assertOk()
            ->assertSee('Divisi Lama (Nonaktif)')
            ->assertViewHas('divisions', function (Collection $divisions) use (
                $activeDivision,
                $currentInactiveDivision,
                $otherInactiveDivision,
            ) {
                return $divisions->contains('id', $activeDivision->id)
                    && $divisions->contains('id', $currentInactiveDivision->id)
                    && ! $divisions->contains('id', $otherInactiveDivision->id);
            });
    }

    public function test_ketua_divisi_requires_a_division(): void
    {
        $role = $this->makeRole('ketua_divisi', 'Ketua Divisi');

        $this->actingAs($this->makeAdmin())
            ->post(route('users.store'), $this->userPayload($role, [
                'division_id' => null,
            ]))
            ->assertSessionHasErrors('division_id');
    }

    public function test_anggota_divisi_requires_a_division(): void
    {
        $role = $this->makeRole('anggota_divisi', 'Anggota Divisi');

        $this->actingAs($this->makeAdmin())
            ->post(route('users.store'), $this->userPayload($role, [
                'division_id' => null,
            ]))
            ->assertSessionHasErrors('division_id');
    }

    public function test_pimpinan_and_admin_surat_may_be_created_without_a_division(): void
    {
        $admin = $this->makeAdmin();

        foreach ([
            ['pimpinan', 'Pimpinan'],
            ['admin_surat', 'Admin Surat'],
        ] as [$slug, $name]) {
            $role = $this->makeRole($slug, $name);
            $email = $slug.'.baru@example.test';

            $this->actingAs($admin)
                ->post(route('users.store'), $this->userPayload($role, [
                    'email' => $email,
                    'division_id' => null,
                ]))
                ->assertRedirect();

            $this->assertDatabaseHas('users', [
                'email' => $email,
                'role_id' => $role->id,
                'division_id' => null,
            ]);
        }
    }

    public function test_create_user_rejects_an_inactive_division(): void
    {
        $role = $this->makeRole('anggota_divisi', 'Anggota Divisi');
        $inactiveDivision = $this->makeDivision('Divisi Nonaktif', 'NON', false);
        $email = 'ditolak.nonaktif@example.test';

        $this->actingAs($this->makeAdmin())
            ->post(route('users.store'), $this->userPayload($role, [
                'email' => $email,
                'division_id' => $inactiveDivision->id,
            ]))
            ->assertSessionHasErrors('division_id');

        $this->assertDatabaseMissing('users', ['email' => $email]);
    }

    public function test_update_user_rejects_another_inactive_division(): void
    {
        $role = $this->makeRole('anggota_divisi', 'Anggota Divisi');
        $activeDivision = $this->makeDivision('Redaksi', 'RED');
        $inactiveDivision = $this->makeDivision('Divisi Nonaktif', 'NON', false);
        $user = $this->makeUser($role, 'Pengguna Redaksi', $activeDivision);

        $this->actingAs($this->makeAdmin())
            ->put(route('users.update', $user), $this->updateUserPayload($user, [
                'division_id' => $inactiveDivision->id,
            ]))
            ->assertSessionHasErrors('division_id');

        $this->assertSame($activeDivision->id, $user->fresh()->division_id);
    }

    public function test_update_user_requires_division_when_role_changes_to_ketua_divisi(): void
    {
        $pimpinanRole = $this->makeRole('pimpinan', 'Pimpinan');
        $ketuaRole = $this->makeRole('ketua_divisi', 'Ketua Divisi');
        $user = $this->makeUser($pimpinanRole, 'Calon Ketua');

        $this->actingAs($this->makeAdmin())
            ->put(route('users.update', $user), $this->updateUserPayload($user, [
                'role_id' => $ketuaRole->id,
                'division_id' => null,
            ]))
            ->assertSessionHasErrors('division_id');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'role_id' => $pimpinanRole->id,
            'division_id' => null,
        ]);
    }

    public function test_update_user_accepts_unchanged_current_inactive_division(): void
    {
        $role = $this->makeRole('anggota_divisi', 'Anggota Divisi');
        $inactiveDivision = $this->makeDivision('Divisi Lama', 'DLM', false);
        $user = $this->makeUser($role, 'Nama Lama', $inactiveDivision);

        $this->actingAs($this->makeAdmin())
            ->put(route('users.update', $user), $this->updateUserPayload($user, [
                'name' => 'Nama Diperbarui',
                'division_id' => $inactiveDivision->id,
            ]))
            ->assertRedirect(route('users.show', $user));

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Nama Diperbarui',
            'division_id' => $inactiveDivision->id,
        ]);
    }

    public function test_update_user_accepts_active_division_move_without_changing_other_data(): void
    {
        $role = $this->makeRole('anggota_divisi', 'Anggota Divisi');
        $oldDivision = $this->makeDivision('Redaksi', 'RED');
        $newDivision = $this->makeDivision('Iklan', 'IKL');
        $user = $this->makeUser($role, 'Pengguna Lengkap', $oldDivision, [
            'phone' => '081234567890',
            'employee_number' => 'EMP-100',
            'position' => 'Reporter',
        ]);

        $this->actingAs($this->makeAdmin())
            ->put(route('users.update', $user), $this->updateUserPayload($user, [
                'division_id' => $newDivision->id,
            ]))
            ->assertRedirect(route('users.show', $user));

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'division_id' => $newDivision->id,
            'phone' => '081234567890',
            'employee_number' => 'EMP-100',
            'position' => 'Reporter',
            'is_active' => true,
        ]);
    }

    public function test_invalid_role_id_is_rejected_without_accessing_a_missing_role(): void
    {
        $role = new Role;
        $role->forceFill(['id' => 999999]);

        $this->actingAs($this->makeAdmin())
            ->post(route('users.store'), $this->userPayload($role))
            ->assertSessionHasErrors('role_id');
    }

    private function makeAdmin(): User
    {
        return $this->makeUser(
            $this->makeRole('admin_surat', 'Admin Surat'),
            'Admin Integrasi',
            attributes: ['email' => 'admin.integrasi@example.test'],
        );
    }

    private function makeRole(string $slug, string $name): Role
    {
        return Role::query()->firstOrCreate(
            ['slug' => $slug],
            ['name' => $name],
        );
    }

    private function makeDivision(
        string $name,
        string $code,
        bool $isActive = true,
    ): Division {
        return Division::query()->create([
            'name' => $name,
            'code' => $code,
            'is_active' => $isActive,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function makeUser(
        Role $role,
        string $name,
        ?Division $division = null,
        array $attributes = [],
    ): User {
        $user = new User;
        $user->forceFill(array_merge([
            'name' => $name,
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'role_id' => $role->id,
            'division_id' => $division?->id,
            'is_active' => true,
        ], $attributes));
        $user->save();

        return $user;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function userPayload(Role $role, array $overrides = []): array
    {
        return array_merge([
            'name' => 'Pengguna Baru',
            'email' => fake()->unique()->safeEmail(),
            'phone' => null,
            'employee_number' => null,
            'position' => null,
            'role_id' => $role->id,
            'division_id' => null,
            'is_active' => '1',
            'password' => 'password-baru',
            'password_confirmation' => 'password-baru',
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function updateUserPayload(User $user, array $overrides = []): array
    {
        return array_merge([
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'employee_number' => $user->employee_number,
            'position' => $user->position,
            'role_id' => $user->role_id,
            'division_id' => $user->division_id,
            'is_active' => $user->is_active ? '1' : '0',
            'password' => '',
            'password_confirmation' => '',
        ], $overrides);
    }
}
