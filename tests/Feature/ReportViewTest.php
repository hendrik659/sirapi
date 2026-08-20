<?php

namespace Tests\Feature;

use App\Models\Division;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReportViewTest extends TestCase
{
    use RefreshDatabase;

    public function test_sidebar_report_collapse_and_submenus_render_on_desktop_and_mobile(): void
    {
        $admin = $this->makeUser('admin_surat');

        $this->actingAs($admin)
            ->get(route('reports.incoming-letters.index'))
            ->assertOk()
            ->assertSee('data-testid="reports-menu-desktop"', false)
            ->assertSee('data-testid="reports-menu-mobile"', false)
            ->assertSee('data-testid="incoming-report-menu-desktop"', false)
            ->assertSee('data-testid="outgoing-report-menu-desktop"', false)
            ->assertSee('data-testid="incoming-report-menu-mobile"', false)
            ->assertSee('data-testid="outgoing-report-menu-mobile"', false)
            ->assertSee('id="rsDesktopReportsMenu"', false)
            ->assertSee('id="rsMobileReportsMenu"', false)
            ->assertSee('collapse show', false)
            ->assertSee('aria-expanded="true"', false)
            ->assertSee('Laporan')
            ->assertSee('Surat Masuk')
            ->assertSee('Surat Keluar');
    }

    public function test_sidebar_marks_the_correct_report_submenu_active(): void
    {
        $admin = $this->makeUser('admin_surat');

        $response = $this->actingAs($admin)
            ->get(route('reports.outgoing-letters.index'))
            ->assertOk();

        $this->assertMatchesRegularExpression(
            '/rs-nav-sublink active"\s+href="'.preg_quote(route('reports.outgoing-letters.index'), '/').'"/',
            $response->getContent(),
        );
        $this->assertDoesNotMatchRegularExpression(
            '/rs-nav-sublink active"\s+href="'.preg_quote(route('reports.incoming-letters.index'), '/').'"/',
            $response->getContent(),
        );
    }

    public function test_global_roles_see_division_filters_recap_and_export_buttons(): void
    {
        $division = $this->makeDivision();

        foreach (['admin_surat', 'pimpinan'] as $role) {
            $user = $this->makeUser($role);

            $this->actingAs($user)
                ->get(route('reports.incoming-letters.index'))
                ->assertOk()
                ->assertSee('data-testid="incoming-report-division-filter"', false)
                ->assertSee('data-testid="incoming-report-recap"', false)
                ->assertSee('data-testid="incoming-report-export"', false)
                ->assertSee($division->name)
                ->assertDontSee('data-testid="incoming-report-own-division"', false);

            $this->actingAs($user)
                ->get(route('reports.outgoing-letters.index'))
                ->assertOk()
                ->assertSee('data-testid="outgoing-report-division-filter"', false)
                ->assertSee('data-testid="outgoing-report-recap"', false)
                ->assertSee('data-testid="outgoing-report-export"', false)
                ->assertSee($division->name)
                ->assertDontSee('data-testid="outgoing-report-own-division"', false);
        }
    }

    public function test_division_roles_see_own_division_information_without_division_picker_or_cross_recap(): void
    {
        $division = $this->makeDivision('Redaksi', 'RED');

        foreach (['ketua_divisi', 'anggota_divisi'] as $role) {
            $user = $this->makeUser($role, $division);

            $this->actingAs($user)
                ->get(route('reports.incoming-letters.index'))
                ->assertOk()
                ->assertSee('data-testid="incoming-report-own-division"', false)
                ->assertSee('Divisi Saya')
                ->assertSee('Redaksi')
                ->assertDontSee('data-testid="incoming-report-division-filter"', false)
                ->assertDontSee('data-testid="incoming-report-recap"', false);

            $this->actingAs($user)
                ->get(route('reports.outgoing-letters.index'))
                ->assertOk()
                ->assertSee('data-testid="outgoing-report-own-division"', false)
                ->assertSee('Divisi Saya')
                ->assertSee('Redaksi')
                ->assertDontSee('data-testid="outgoing-report-division-filter"', false)
                ->assertDontSee('data-testid="outgoing-report-recap"', false);
        }
    }

    public function test_report_pages_have_clear_empty_states_and_no_mutation_actions(): void
    {
        $admin = $this->makeUser('admin_surat');

        $this->actingAs($admin)
            ->get(route('reports.incoming-letters.index'))
            ->assertOk()
            ->assertSee('data-testid="incoming-report-empty-state"', false)
            ->assertSee('Belum ada Surat Masuk')
            ->assertSee('Data laporan surat masuk akan tampil di sini.')
            ->assertDontSee('Edit')
            ->assertDontSee('Hapus')
            ->assertDontSee('Arsipkan');

        $this->actingAs($admin)
            ->get(route('reports.outgoing-letters.index'))
            ->assertOk()
            ->assertSee('data-testid="outgoing-report-empty-state"', false)
            ->assertSee('Belum ada Surat Keluar')
            ->assertSee('Data laporan surat keluar akan tampil di sini.')
            ->assertDontSee('Edit')
            ->assertDontSee('Hapus')
            ->assertDontSee('Arsipkan')
            ->assertDontSee('Status');
    }

    public function test_report_tables_render_only_the_required_read_only_columns(): void
    {
        $admin = $this->makeUser('admin_surat');

        $this->actingAs($admin)
            ->get(route('reports.incoming-letters.index'))
            ->assertSeeInOrder([
                'No', 'Agenda', 'Nomor Surat', 'Tanggal Diterima', 'Pengirim', 'Perihal',
                'Divisi Tujuan', 'Prioritas', 'Status',
            ]);

        $this->actingAs($admin)
            ->get(route('reports.outgoing-letters.index'))
            ->assertSeeInOrder([
                'No', 'Kode Sistem', 'Nomor Surat', 'Tanggal Surat', 'Tujuan', 'Perihal',
                'Divisi', 'Dicatat Oleh',
            ]);
    }

    private function makeDivision(string $name = 'Keuangan', string $code = 'KEU'): Division
    {
        return Division::query()->create(['name' => $name, 'code' => $code, 'is_active' => true]);
    }

    private function makeUser(string $roleSlug, ?Division $division = null): User
    {
        $role = Role::query()->firstOrCreate(['slug' => $roleSlug], ['name' => Str::headline($roleSlug)]);

        return User::query()->create([
            'name' => Str::headline($roleSlug),
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'role_id' => $role->id,
            'division_id' => $division?->id,
            'is_active' => true,
        ]);
    }
}
