<?php

namespace Tests\Feature;

use App\Models\Division;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DivisionViewTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_division_index(): void
    {
        $this->actingAs($this->makeAdmin())
            ->get(route('divisions.index'))
            ->assertOk()
            ->assertViewIs('divisions.index')
            ->assertSee('Data Divisi')
            ->assertSee('Kelola data divisi yang tersedia pada Radar Kediri.')
            ->assertDontSee('name="search"', false)
            ->assertDontSee('Cari nama atau kode divisi');
    }

    public function test_index_displays_division_data_user_count_and_detail_edit_actions(): void
    {
        $admin = $this->makeAdmin();
        $division = $this->makeDivision();
        $this->makeDivisionUser($division, 'Reporter Satu');

        $this->actingAs($admin)
            ->get(route('divisions.index'))
            ->assertOk()
            ->assertSee($division->name)
            ->assertSee($division->code)
            ->assertSee('data-testid="division-detail-link"', false)
            ->assertSee('data-testid="division-edit-link"', false)
            ->assertSee('class="btn btn-sm btn-outline-primary d-inline-flex align-items-center gap-1"', false)
            ->assertSee('class="btn btn-sm btn-outline-secondary d-inline-flex align-items-center gap-1"', false)
            ->assertSee('href="'.route('divisions.show', $division).'"', false)
            ->assertSee('href="'.route('divisions.edit', $division).'"', false)
            ->assertViewHas('divisions', function (Collection $divisions) use ($division) {
                return $divisions->firstWhere('id', $division->id)?->users_count === 1;
            });
    }

    public function test_index_does_not_display_status_or_status_actions(): void
    {
        $admin = $this->makeAdmin();
        $activeDivision = $this->makeDivision();
        $inactiveDivision = $this->makeDivision('Bisnis', 'BSN', false);

        $this->actingAs($admin)
            ->get(route('divisions.index'))
            ->assertOk()
            ->assertDontSee('data-testid="division-status-form"', false)
            ->assertDontSee('action="'.route('divisions.status', $activeDivision).'"', false)
            ->assertDontSee('action="'.route('divisions.status', $inactiveDivision).'"', false)
            ->assertDontSee('<th scope="col">Status</th>', false);
    }

    public function test_index_displays_all_divisions_without_pagination(): void
    {
        $admin = $this->makeAdmin();

        foreach (range(1, 12) as $number) {
            $this->makeDivision(
                sprintf('Divisi %02d', $number),
                sprintf('D%02d', $number),
            );
        }

        $response = $this->actingAs($admin)->get(route('divisions.index'));

        $response
            ->assertOk()
            ->assertDontSee('pagination')
            ->assertViewHas('divisions', fn (Collection $divisions) => $divisions->count() === 12);

        foreach (range(1, 12) as $number) {
            $response->assertSee(sprintf('Divisi %02d', $number));
        }
    }

    public function test_index_displays_initial_empty_state(): void
    {
        $this->actingAs($this->makeAdmin())
            ->get(route('divisions.index'))
            ->assertOk()
            ->assertSee('Belum ada Divisi')
            ->assertSee('Data divisi yang dibuat akan tampil di sini.')
            ->assertSee('colspan="4"', false);
    }

    public function test_show_displays_complete_division_information(): void
    {
        $admin = $this->makeAdmin();
        $division = $this->makeDivision();
        $this->makeDivisionUser($division);

        $this->actingAs($admin)
            ->get(route('divisions.show', $division))
            ->assertOk()
            ->assertViewIs('divisions.show')
            ->assertSee('Detail Divisi')
            ->assertSee($division->name)
            ->assertSee($division->code)
            ->assertSee('data-testid="division-status-badge"', false)
            ->assertSee('Aktif')
            ->assertSee($division->created_at->format('d-m-Y'))
            ->assertViewHas('division', function (Division $viewDivision) {
                return $viewDivision->users_count === 1;
            });
    }

    public function test_show_displays_users_belonging_to_the_division(): void
    {
        $admin = $this->makeAdmin();
        $division = $this->makeDivision();
        $user = $this->makeDivisionUser(
            $division,
            'Lala Reporter',
            'lala.reporter@example.test',
            'Reporter',
            'Anggota Divisi',
        );

        $this->actingAs($admin)
            ->get(route('divisions.show', $division))
            ->assertOk()
            ->assertSee($user->name)
            ->assertSee($user->email)
            ->assertSee($user->position)
            ->assertSee('Anggota Divisi')
            ->assertSee('Status Akun');
    }

    public function test_show_does_not_display_users_from_another_division(): void
    {
        $admin = $this->makeAdmin();
        $division = $this->makeDivision();
        $otherDivision = $this->makeDivision('Keuangan', 'KEU');
        $includedUser = $this->makeDivisionUser($division, 'Pengguna Editorial');
        $excludedUser = $this->makeDivisionUser($otherDivision, 'Pengguna Keuangan');

        $this->actingAs($admin)
            ->get(route('divisions.show', $division))
            ->assertOk()
            ->assertSee($includedUser->name)
            ->assertDontSee($excludedUser->name);
    }

    public function test_show_displays_empty_user_state(): void
    {
        $division = $this->makeDivision();

        $this->actingAs($this->makeAdmin())
            ->get(route('divisions.show', $division))
            ->assertOk()
            ->assertSee('Belum ada pengguna yang terdaftar pada divisi ini.')
            ->assertSee('colspan="5"', false);
    }

    public function test_active_division_show_has_deactivation_form(): void
    {
        $division = $this->makeDivision();

        $this->actingAs($this->makeAdmin())
            ->get(route('divisions.show', $division))
            ->assertOk()
            ->assertSee('data-testid="division-status-form"', false)
            ->assertSee('action="'.route('divisions.status', $division).'"', false)
            ->assertSee('name="_method" value="PATCH"', false)
            ->assertSee('name="is_active" value="0"', false)
            ->assertSee('data-confirmation-title="Nonaktifkan Divisi"', false)
            ->assertSee('data-confirmation-variant="danger"', false)
            ->assertSee('Nonaktifkan');
    }

    public function test_inactive_division_show_has_activation_form(): void
    {
        $division = $this->makeDivision('Keuangan', 'KEU', false);

        $this->actingAs($this->makeAdmin())
            ->get(route('divisions.show', $division))
            ->assertOk()
            ->assertSee('data-testid="division-status-form"', false)
            ->assertSee('action="'.route('divisions.status', $division).'"', false)
            ->assertSee('name="_method" value="PATCH"', false)
            ->assertSee('name="is_active" value="1"', false)
            ->assertSee('data-confirmation-title="Aktifkan Divisi"', false)
            ->assertSee('data-confirmation-variant="success"', false)
            ->assertSee('Aktifkan');
    }

    public function test_admin_sidebar_has_active_mobile_and_desktop_division_links(): void
    {
        $division = $this->makeDivision();

        $this->actingAs($this->makeAdmin())
            ->get(route('divisions.show', $division))
            ->assertOk()
            ->assertSee('data-testid="division-menu-mobile"', false)
            ->assertSee('data-testid="division-menu-desktop"', false)
            ->assertSee('href="'.route('divisions.index').'"', false)
            ->assertSee('class="nav-link rs-nav-link active"', false);
    }

    private function makeAdmin(): User
    {
        return $this->makeUser(
            roleSlug: 'admin_surat',
            roleName: 'Admin Surat',
            name: 'Admin Divisi',
            email: 'admin.divisi@example.test',
        );
    }

    private function makeDivision(
        string $name = 'Editorial',
        string $code = 'EDT',
        bool $isActive = true,
    ): Division {
        return Division::query()->create([
            'name' => $name,
            'code' => $code,
            'is_active' => $isActive,
        ]);
    }

    private function makeDivisionUser(
        Division $division,
        string $name = 'Pengguna Divisi',
        ?string $email = null,
        string $position = 'Staf',
        string $roleName = 'Anggota Divisi',
        bool $isActive = true,
    ): User {
        return $this->makeUser(
            roleSlug: 'anggota_divisi',
            roleName: $roleName,
            name: $name,
            email: $email ?? fake()->unique()->safeEmail(),
            division: $division,
            position: $position,
            isActive: $isActive,
        );
    }

    private function makeUser(
        string $roleSlug,
        string $roleName,
        string $name,
        string $email,
        ?Division $division = null,
        ?string $position = null,
        bool $isActive = true,
    ): User {
        $role = Role::query()->firstOrCreate(
            ['slug' => $roleSlug],
            ['name' => $roleName],
        );

        $user = new User;
        $user->forceFill([
            'name' => $name,
            'email' => $email,
            'password' => 'password',
            'position' => $position,
            'role_id' => $role->id,
            'division_id' => $division?->id,
            'is_active' => $isActive,
        ]);
        $user->save();

        return $user;
    }
}
