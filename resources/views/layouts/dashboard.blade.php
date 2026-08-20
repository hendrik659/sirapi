<!DOCTYPE html>
<html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="color-scheme" content="light">
        <title>@yield('title', 'Dashboard') - SIRAPI</title>
        <script>
            try {
                if (window.matchMedia('(min-width: 992px)').matches && localStorage.getItem('rs-sidebar-state') === 'collapsed') {
                    document.documentElement.classList.add('rs-sidebar-collapsed');
                }
            } catch (error) {
                // The layout safely defaults to an expanded sidebar.
            }
        </script>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @stack('styles')
    </head>
    <body class="rs-body">
        @php
            $currentUser = auth()->user();
            $currentUser?->loadMissing(['role', 'division']);
            $reportRoles = ['admin_surat', 'pimpinan', 'ketua_divisi', 'anggota_divisi'];
            $canViewReports = $currentUser?->is_active
                && in_array($currentUser?->role?->slug, $reportRoles, true)
                && (! in_array($currentUser?->role?->slug, ['ketua_divisi', 'anggota_divisi'], true) || $currentUser?->division_id !== null);
            $canViewCertificates = $currentUser?->is_active
                && $currentUser?->can('viewAny', \App\Models\InternshipCertificate::class);
            $reportsActive = request()->routeIs('reports.*');
            $currentUserRoleLabel = $currentUser?->role?->display_name
                ?? \Illuminate\Support\Str::headline($currentUser?->role?->slug ?? 'Pengguna');
            $currentUserInitials = \Illuminate\Support\Str::of($currentUser?->name ?? 'Pengguna')
                ->trim()
                ->explode(' ')
                ->filter()
                ->take(2)
                ->map(fn ($part) => \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($part, 0, 1)))
                ->implode('');
        @endphp

        <div class="rs-app-shell">
            <aside
                class="rs-sidebar d-none d-lg-flex flex-column"
                id="rsDesktopSidebar"
                data-desktop-sidebar
                data-testid="desktop-sidebar"
                aria-label="Sidebar utama SIRAPI"
            >
                <a
                    class="rs-sidebar-brand d-flex align-items-center justify-content-center text-decoration-none"
                    href="{{ route('dashboard') }}"
                    data-sidebar-tooltip="SIRAPI"
                    aria-label="SIRAPI - Sistem Arsip Jawa Pos Radar Kediri"
                >
                    <img
                        class="rs-sidebar-brand-logo rs-sidebar-label"
                        src="{{ asset('images/auth/radar-kediri-logo-white.png') }}"
                        alt=""
                        width="2172"
                        height="724"
                    >
                    <span class="rs-sidebar-brand-mark d-inline-flex align-items-center justify-content-center" aria-hidden="true">
                        <i class="fa-solid fa-box-archive" aria-hidden="true"></i>
                    </span>
                </a>

                @include('layouts.partials.sidebar-navigation', [
                    'mode' => 'desktop',
                    'reportMenuId' => 'rsDesktopReportsMenu',
                ])

                @include('layouts.partials.sidebar-profile', ['mode' => 'desktop'])
            </aside>

            <div class="offcanvas offcanvas-start rs-offcanvas" tabindex="-1" id="rsMobileSidebar" aria-labelledby="rsMobileSidebarLabel">
                <div class="offcanvas-header rs-offcanvas-header">
                    <div class="rs-offcanvas-brand">
                        <img
                            src="{{ asset('images/auth/radar-kediri-logo-white.png') }}"
                            alt=""
                            width="2172"
                            height="724"
                        >
                        <h2 class="visually-hidden" id="rsMobileSidebarLabel">Menu SIRAPI</h2>
                    </div>
                    <button class="btn-close btn-close-white" type="button" data-bs-dismiss="offcanvas" aria-label="Tutup menu navigasi"></button>
                </div>
                <div class="offcanvas-body d-flex flex-column p-3">
                    @include('layouts.partials.sidebar-navigation', [
                        'mode' => 'mobile',
                        'reportMenuId' => 'rsMobileReportsMenu',
                    ])

                    @include('layouts.partials.sidebar-profile', ['mode' => 'mobile'])
                </div>
            </div>

            <div class="rs-main-wrapper" data-testid="dashboard-main-wrapper">
                <header class="navbar navbar-light rs-navbar" data-testid="dashboard-global-header">
                    <div class="container-fluid flex-nowrap gap-3">
                        <div class="d-flex align-items-center gap-2 gap-sm-3 rs-navbar-start">
                            <button
                                class="btn rs-menu-toggle d-none d-lg-inline-flex align-items-center justify-content-center"
                                type="button"
                                data-desktop-sidebar-toggle
                                data-testid="desktop-sidebar-toggle"
                                aria-controls="rsDesktopSidebar"
                                aria-expanded="true"
                                aria-label="Ciutkan sidebar"
                            >
                                <i class="fa-solid fa-bars" aria-hidden="true"></i>
                            </button>

                            <button
                                class="btn rs-menu-toggle d-inline-flex d-lg-none align-items-center justify-content-center"
                                type="button"
                                data-bs-toggle="offcanvas"
                                data-bs-target="#rsMobileSidebar"
                                data-testid="mobile-sidebar-toggle"
                                aria-controls="rsMobileSidebar"
                                aria-expanded="false"
                                aria-label="Buka menu navigasi"
                            >
                                <i class="fa-solid fa-bars" aria-hidden="true"></i>
                            </button>

                            <div class="rs-global-heading">
                                <div class="rs-global-title">SIRAPI</div>
                                <div class="rs-global-subtitle">Sistem Arsip Jawa Pos Radar Kediri</div>
                            </div>
                        </div>

                        <div class="rs-navbar-notifications d-flex align-items-center justify-content-end">
                            @include('layouts.partials.notification-bell', ['currentUser' => $currentUser])
                        </div>
                    </div>
                </header>

                <main class="rs-main">
                    <div class="rs-content-container d-flex flex-column">
                        <x-global-alerts />

                        @yield('content')

                        @if (request()->routeIs('dashboard'))
                            <footer class="rs-footer d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-2 mt-auto pt-5" aria-label="Informasi aplikasi">
                                <span>© {{ now()->year }} SIRAPI - Jawa Pos Radar Kediri.</span>
                            </footer>
                        @endif
                    </div>
                </main>
            </div>
        </div>

        <x-confirmation-modal />

        @stack('scripts')
    </body>
</html>
