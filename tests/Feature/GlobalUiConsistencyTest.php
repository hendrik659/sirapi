<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class GlobalUiConsistencyTest extends TestCase
{
    /**
     * @return array<string>
     */
    private function filterViewPaths(): array
    {
        return [
            resource_path('views/incoming-letters/index.blade.php'),
            resource_path('views/outgoing-letters/index.blade.php'),
            resource_path('views/certificates/index.blade.php'),
            resource_path('views/users/index.blade.php'),
            resource_path('views/reports/incoming-letters.blade.php'),
            resource_path('views/reports/outgoing-letters.blade.php'),
            resource_path('views/reports/certificates/index.blade.php'),
        ];
    }

    public function test_search_filter_and_reset_controls_use_the_global_button_mapping(): void
    {
        foreach ($this->filterViewPaths() as $path) {
            $view = File::get($path);

            $this->assertStringContainsString('type="search"', $view, $path);
            $this->assertStringContainsString('fa-magnifying-glass', $view, $path);
            $this->assertStringContainsString('fa-filter', $view, $path);
            $this->assertStringContainsString('<span>Terapkan Filter</span>', $view, $path);
            $this->assertStringContainsString('fa-rotate-left', $view, $path);
            $this->assertStringContainsString('<span>Reset</span>', $view, $path);
        }
    }

    public function test_all_reports_use_the_same_excel_export_label_and_icon(): void
    {
        $paths = [
            resource_path('views/reports/incoming-letters.blade.php'),
            resource_path('views/reports/outgoing-letters.blade.php'),
            resource_path('views/reports/certificates/index.blade.php'),
        ];

        foreach ($paths as $path) {
            $view = File::get($path);

            $this->assertStringContainsString('fa-file-excel', $view, $path);
            $this->assertStringContainsString('<span>Ekspor Excel</span>', $view, $path);
            $this->assertStringNotContainsString('Export Excel', $view, $path);
        }
    }

    public function test_navigation_and_dashboard_use_final_indonesian_labels(): void
    {
        $layout = File::get(resource_path('views/layouts/dashboard.blade.php'));
        $login = File::get(resource_path('views/auth/login.blade.php'));
        $navigation = File::get(resource_path('views/layouts/partials/sidebar-navigation.blade.php'));
        $profile = File::get(resource_path('views/layouts/partials/sidebar-profile.blade.php'));
        $dashboard = File::get(resource_path('views/dashboard/index.blade.php'));

        $this->assertStringContainsString('<html lang="id">', $layout);
        $this->assertStringContainsString('<html lang="id">', $login);
        $this->assertStringContainsString('Pengguna', $navigation);
        $this->assertStringContainsString('Divisi', $navigation);
        $this->assertStringNotContainsString('>Users<', $navigation);
        $this->assertStringNotContainsString('>Divisions<', $navigation);
        $this->assertStringContainsString('Keluar', $profile);
        $this->assertStringNotContainsString('Log Out', $profile);
        $this->assertStringContainsString('Dashboard Admin SIRAPI', $dashboard);
        $this->assertStringContainsString('Pengguna Aktif', $dashboard);
        $this->assertStringContainsString('Pengguna Nonaktif', $dashboard);
    }

    public function test_incoming_status_labels_and_badges_are_consistent(): void
    {
        $presenter = File::get(app_path('Support/IncomingLetterStatusPresenter.php'));
        $component = File::get(resource_path('views/components/incoming-letter-status-badge.blade.php'));
        $css = File::get(resource_path('css/app.css'));
        $paths = [
            resource_path('views/incoming-letters/index.blade.php'),
            resource_path('views/incoming-letters/show.blade.php'),
            resource_path('views/incoming-letters/review.blade.php'),
            resource_path('views/dashboard/index.blade.php'),
            resource_path('views/reports/incoming-letters.blade.php'),
        ];

        foreach (['Baru Diterima', 'Menunggu Pemeriksaan', 'Selesai'] as $label) {
            $this->assertStringContainsString("'label' => '".$label."'", $presenter);
        }

        foreach (['new', 'waiting', 'done'] as $variant) {
            $this->assertStringContainsString("'variant' => '".$variant."'", $presenter);
            $this->assertStringContainsString('.rs-status-badge.rs-status-'.$variant, $css);
        }

        $this->assertStringContainsString("['badge', 'rs-status-badge'", $component);
        $this->assertStringContainsString('IncomingLetterStatusPresenter::variant', $component);
        $this->assertStringContainsString('data-incoming-letter-status', $component);

        foreach ($paths as $path) {
            $view = File::get($path);

            $this->assertStringContainsString('<x-incoming-letter-status-badge', $view, $path);
            $this->assertStringNotContainsString('$statusBadgeClasses', $view, $path);
        }
    }

    public function test_outgoing_letters_and_certificates_do_not_show_fake_statuses(): void
    {
        $paths = [
            resource_path('views/outgoing-letters/index.blade.php'),
            resource_path('views/outgoing-letters/show.blade.php'),
            resource_path('views/reports/outgoing-letters.blade.php'),
            resource_path('views/certificates/index.blade.php'),
            resource_path('views/certificates/show.blade.php'),
            resource_path('views/reports/certificates/index.blade.php'),
        ];

        foreach ($paths as $path) {
            $view = File::get($path);

            $this->assertStringNotContainsString('Terkirim', $view, $path);
            $this->assertStringNotContainsString('Diarsipkan', $view, $path);
        }
    }
}
