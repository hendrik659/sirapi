@php
    $isDesktopSidebar = $mode === 'desktop';
    $primaryNavigation = [
        [
            'label' => 'Dashboard',
            'route' => 'dashboard',
            'pattern' => 'dashboard',
            'icon' => 'fa-house',
            'testId' => null,
        ],
        [
            'label' => 'Surat Masuk',
            'route' => 'incoming-letters.index',
            'pattern' => 'incoming-letters.*',
            'icon' => 'fa-envelope-open-text',
            'testId' => 'incoming-letter-menu',
        ],
        [
            'label' => 'Surat Keluar',
            'route' => 'outgoing-letters.index',
            'pattern' => 'outgoing-letters.*',
            'icon' => 'fa-paper-plane',
            'testId' => 'outgoing-letter-menu',
        ],
    ];

    if ($canViewCertificates) {
        $primaryNavigation[] = [
            'label' => 'Sertifikat',
            'route' => 'dashboard.certificates.index',
            'pattern' => 'dashboard.certificates.*',
            'icon' => 'fa-award',
            'testId' => 'certificate-menu',
        ];
    }

    if ($currentUser?->role?->slug === 'admin_surat') {
        $primaryNavigation[] = [
            'label' => 'Pengguna',
            'route' => 'users.index',
            'pattern' => 'users.*',
            'icon' => 'fa-users',
            'testId' => null,
        ];
        $primaryNavigation[] = [
            'label' => 'Divisi',
            'route' => 'divisions.index',
            'pattern' => 'divisions.*',
            'icon' => 'fa-building',
            'testId' => 'division-menu',
        ];
    }
@endphp

<nav class="rs-sidebar-navigation flex-grow-1 {{ $isDesktopSidebar ? 'p-3' : 'p-0' }}" aria-label="{{ $isDesktopSidebar ? 'Navigasi dashboard' : 'Navigasi dashboard mobile' }}">
    <ul class="nav nav-pills flex-column gap-1 rs-sidebar-nav">
        @foreach ($primaryNavigation as $item)
            @php($itemActive = request()->routeIs($item['pattern']))
            <li class="nav-item">
                <a
                    class="nav-link rs-nav-link {{ $itemActive ? 'active' : '' }}"
                    href="{{ route($item['route']) }}"
                    @if ($item['testId']) data-testid="{{ $item['testId'] }}-{{ $mode }}" @endif
                    @if ($isDesktopSidebar) data-sidebar-tooltip="{{ $item['label'] }}" aria-label="{{ $item['label'] }}" @endif
                    @if ($itemActive) aria-current="page" @endif
                >
                    <i class="fa-solid {{ $item['icon'] }} rs-nav-icon" aria-hidden="true"></i>
                    <span class="rs-sidebar-label">{{ $item['label'] }}</span>
                </a>
            </li>
        @endforeach

        @if ($canViewReports)
            <li class="nav-item" data-testid="reports-menu-{{ $mode }}">
                <button
                    class="nav-link rs-nav-link rs-nav-collapse-button {{ $reportsActive ? 'active' : '' }}"
                    type="button"
                    data-bs-toggle="collapse"
                    data-bs-target="#{{ $reportMenuId }}"
                    data-sidebar-report-toggle
                    @if ($isDesktopSidebar) data-sidebar-tooltip="Laporan" aria-label="Laporan" @endif
                    aria-expanded="{{ $reportsActive ? 'true' : 'false' }}"
                    aria-controls="{{ $reportMenuId }}"
                >
                    <i class="fa-solid fa-chart-column rs-nav-icon" aria-hidden="true"></i>
                    <span class="rs-sidebar-label">Laporan</span>
                    <i class="fa-solid fa-chevron-down rs-nav-collapse-icon" aria-hidden="true"></i>
                </button>
                <div class="collapse {{ $reportsActive ? 'show' : '' }}" id="{{ $reportMenuId }}">
                    <ul class="nav flex-column rs-nav-submenu">
                        <li class="nav-item">
                            <a
                                class="nav-link rs-nav-link rs-nav-sublink {{ request()->routeIs('reports.incoming-letters.*') ? 'active' : '' }}"
                                href="{{ route('reports.incoming-letters.index') }}"
                                data-testid="incoming-report-menu-{{ $mode }}"
                                @if (request()->routeIs('reports.incoming-letters.*')) aria-current="page" @endif
                            >
                                <i class="fa-solid fa-envelope-open-text rs-nav-icon" aria-hidden="true"></i>
                                <span>Surat Masuk</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a
                                class="nav-link rs-nav-link rs-nav-sublink {{ request()->routeIs('reports.outgoing-letters.*') ? 'active' : '' }}"
                                href="{{ route('reports.outgoing-letters.index') }}"
                                data-testid="outgoing-report-menu-{{ $mode }}"
                                @if (request()->routeIs('reports.outgoing-letters.*')) aria-current="page" @endif
                            >
                                <i class="fa-solid fa-paper-plane rs-nav-icon" aria-hidden="true"></i>
                                <span>Surat Keluar</span>
                            </a>
                        </li>
                        @if ($canViewCertificates)
                            <li class="nav-item">
                                <a
                                    class="nav-link rs-nav-link rs-nav-sublink {{ request()->routeIs('reports.certificates.*') ? 'active' : '' }}"
                                    href="{{ route('reports.certificates.index') }}"
                                    data-testid="certificate-report-menu-{{ $mode }}"
                                    @if (request()->routeIs('reports.certificates.*')) aria-current="page" @endif
                                >
                                    <i class="fa-solid fa-award rs-nav-icon" aria-hidden="true"></i>
                                    <span>Sertifikat</span>
                                </a>
                            </li>
                        @endif
                    </ul>
                </div>
            </li>
        @endif
    </ul>
</nav>
