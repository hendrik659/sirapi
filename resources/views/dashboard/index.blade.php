@extends('layouts.dashboard')

@section('title', $isAdminDashboard ? 'Dashboard Admin SIRAPI' : 'Dashboard')

@section('content')
    @php
        $dashboardUser = auth()->user();
        $dashboardUser?->loadMissing(['role', 'division']);
        $dashboardRole = $dashboardUser?->role?->slug;
        $isSdmLeader = $dashboardRole === 'ketua_divisi' && $dashboardUser?->division?->code === 'SDM';
        $canAccessCertificates = $dashboardUser?->can('viewAny', \App\Models\InternshipCertificate::class) ?? false;
        $canAccessReports = in_array($dashboardRole, ['admin_surat', 'pimpinan'], true)
            || (in_array($dashboardRole, ['ketua_divisi', 'anggota_divisi'], true) && $dashboardUser?->division_id !== null);
        $dashboardProfile = match (true) {
            $dashboardRole === 'admin_surat' => [
                'title' => 'Dashboard Admin SIRAPI',
                'description' => 'Ringkasan administrasi surat internal.',
            ],
            $dashboardRole === 'pimpinan' => [
                'title' => 'Dashboard Pimpinan',
                'description' => 'Ringkasan monitoring arsip dan administrasi.',
            ],
            $isSdmLeader => [
                'title' => 'Dashboard Ketua Divisi',
                'description' => 'Ringkasan administrasi dan arsip.',
            ],
            $dashboardRole === 'ketua_divisi' => [
                'title' => 'Dashboard Ketua Divisi',
                'description' => 'Ringkasan aktivitas divisi Anda.',
            ],
            $dashboardRole === 'anggota_divisi' => [
                'title' => 'Dashboard Anggota Divisi',
                'description' => 'Ringkasan aktivitas divisi Anda.',
            ],
            default => [
                'title' => 'Dashboard',
                'description' => 'Akses cepat ke administrasi dan arsip internal.',
            ],
        };
        $dashboardDate = $todayLabel ?? now()->locale('id')->translatedFormat('l, j F Y');
        $quickAccessItems = [];

        if ($dashboardRole === 'admin_surat') {
            $quickAccessItems[] = [
                'label' => 'Tambah Surat Masuk',
                'icon' => 'fa-square-plus',
                'route' => route('incoming-letters.create'),
            ];
        }

        $quickAccessItems[] = [
            'label' => 'Surat Masuk',
            'icon' => 'fa-envelope-open-text',
            'route' => route('incoming-letters.index'),
        ];
        $quickAccessItems[] = [
            'label' => 'Surat Keluar',
            'icon' => 'fa-paper-plane',
            'route' => route('outgoing-letters.index'),
        ];

        if ($canAccessCertificates) {
            $quickAccessItems[] = [
                'label' => 'Sertifikat',
                'icon' => 'fa-award',
                'route' => route('dashboard.certificates.index'),
            ];
        }

        if ($canAccessReports) {
            $quickAccessItems[] = [
                'label' => 'Laporan Surat Masuk',
                'icon' => 'fa-chart-line',
                'route' => route('reports.incoming-letters.index'),
            ];
            $quickAccessItems[] = [
                'label' => 'Laporan Surat Keluar',
                'icon' => 'fa-chart-column',
                'route' => route('reports.outgoing-letters.index'),
            ];

            if ($canAccessCertificates) {
                $quickAccessItems[] = [
                    'label' => 'Laporan Sertifikat',
                    'icon' => 'fa-file-contract',
                    'route' => route('reports.certificates.index'),
                ];
            }
        }

        if ($dashboardRole === 'admin_surat') {
            $quickAccessItems[] = [
                'label' => 'Pengguna',
                'icon' => 'fa-users',
                'route' => route('users.index'),
            ];
            $quickAccessItems[] = [
                'label' => 'Divisi',
                'icon' => 'fa-building',
                'route' => route('divisions.index'),
            ];
        }
    @endphp

    <section
        class="rs-dashboard-banner d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4"
        style="--rs-dashboard-building: url('{{ asset('images/auth/radar-kediri-building.png') }}')"
        aria-labelledby="dashboardBannerTitle"
        data-testid="{{ $isAdminDashboard ? 'dashboard-admin-banner' : 'dashboard-role-banner' }}"
    >
        <div class="rs-dashboard-banner-content d-flex align-items-center gap-3">
            <span class="rs-dashboard-banner-icon d-inline-flex align-items-center justify-content-center" aria-hidden="true">
                <i class="fa-solid fa-table-cells-large" aria-hidden="true"></i>
            </span>
            <div>
                <h1 class="h4 mb-1" id="dashboardBannerTitle">{{ $dashboardProfile['title'] }}</h1>
                <p class="mb-0">{{ $dashboardProfile['description'] }}</p>
            </div>
        </div>
        <time class="rs-dashboard-date" datetime="{{ now()->toDateString() }}">
            <i class="fa-regular fa-calendar me-2" aria-hidden="true"></i>{{ $dashboardDate }}
        </time>
    </section>

    @if ($isAdminDashboard)
        <section class="mb-4" aria-labelledby="dashboardStatisticsTitle">
            <h2 class="visually-hidden" id="dashboardStatisticsTitle">Statistik utama surat</h2>
            <div class="row g-3">
                @foreach ([
                    ['Total Surat Masuk', $totalIncomingLetters, 'fa-envelope-open-text', 'primary'],
                    ['Baru Diterima', $newIncomingLetters, 'fa-inbox', 'info'],
                    ['Menunggu Pemeriksaan', $waitingReviewIncomingLetters, 'fa-hourglass-half', 'warning'],
                    ['Total Surat Keluar', $totalOutgoingLetters, 'fa-paper-plane', 'success'],
                ] as [$label, $value, $icon, $accent])
                    <div class="col-12 col-sm-6 col-xl-3">
                        <article class="card rs-dashboard-stat h-100 shadow-sm" data-testid="dashboard-kpi">
                            <div class="card-body d-flex align-items-center justify-content-between gap-3">
                                <div>
                                    <div class="rs-dashboard-stat-label">{{ $label }}</div>
                                    <strong class="rs-dashboard-stat-value" data-kpi-label="{{ $label }}">{{ $value }}</strong>
                                </div>
                                <span class="rs-dashboard-stat-icon rs-dashboard-stat-icon-{{ $accent }} d-inline-flex align-items-center justify-content-center" aria-hidden="true">
                                    <i class="fa-solid {{ $icon }}" aria-hidden="true"></i>
                                </span>
                            </div>
                        </article>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    <section class="mb-4" aria-labelledby="quickAccessTitle">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <h2 class="rs-section-title h5 mb-0" id="quickAccessTitle">Akses Cepat</h2>
        </div>
        <div class="rs-quick-access-grid">
            @foreach ($quickAccessItems as $item)
                <a class="rs-quick-card d-flex h-100 flex-column align-items-center justify-content-center gap-2 text-center text-decoration-none" href="{{ $item['route'] }}" data-testid="dashboard-quick-access">
                    <span class="rs-quick-card-icon d-inline-flex align-items-center justify-content-center" aria-hidden="true">
                        <i class="fa-solid {{ $item['icon'] }}" aria-hidden="true"></i>
                    </span>
                    <strong>{{ $item['label'] }}</strong>
                </a>
            @endforeach
        </div>
    </section>

    @if ($isAdminDashboard)
        <section class="card rs-dashboard-panel rs-dashboard-chart-panel shadow-sm mb-4" aria-labelledby="letterTrendTitle" data-testid="dashboard-trend-section">
            <div class="card-body p-3 p-md-4">
                <div class="mb-3">
                    <h2 class="h5 mb-1" id="letterTrendTitle">Tren Surat (6 Bulan Terakhir)</h2>
                    <p class="small text-body-secondary mb-0" id="letterTrendDescription">Perbandingan jumlah Surat Masuk berdasarkan tanggal diterima dan Surat Keluar berdasarkan tanggal surat.</p>
                </div>
                <div class="rs-dashboard-chart" role="group" aria-labelledby="letterTrendTitle" aria-describedby="letterTrendDescription">
                    <canvas
                        data-dashboard-trend-chart
                        data-chart="{{ json_encode([
                            'labels' => $sixMonthLabels,
                            'incoming' => $sixMonthIncomingTrend,
                            'outgoing' => $sixMonthOutgoingTrend,
                        ]) }}"
                        role="img"
                        aria-label="Diagram garis tren Surat Masuk dan Surat Keluar selama enam bulan terakhir"
                    >
                        Diagram tren Surat Masuk dan Surat Keluar selama enam bulan terakhir.
                    </canvas>
                </div>
            </div>
        </section>

        <div class="row g-4 mb-4">
            <div class="col-12 col-xl-6">
                <section class="card rs-dashboard-panel h-100 shadow-sm" aria-labelledby="recentIncomingTitle">
                    <div class="card-header d-flex align-items-center justify-content-between gap-3 py-3">
                        <h2 class="h5 mb-0" id="recentIncomingTitle">Surat Masuk Terbaru</h2>
                        <a class="small fw-semibold text-decoration-none" href="{{ route('incoming-letters.index') }}">Lihat Semua <span aria-hidden="true">→</span></a>
                    </div>
                    <div class="table-responsive">
                        <table class="table align-middle mb-0 rs-dashboard-table" data-testid="dashboard-recent-table">
                            <thead>
                                <tr>
                                    <th scope="col">Perihal</th>
                                    <th scope="col">Pengirim</th>
                                    <th scope="col">Tanggal</th>
                                    <th scope="col">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($recentIncomingLetters as $letter)
                                    <tr data-testid="dashboard-recent-incoming-row">
                                        <td class="fw-semibold text-body-emphasis rs-dashboard-cell-main">{{ $letter->subject }}</td>
                                        <td>{{ $letter->sender_name }}</td>
                                        <td class="text-nowrap rs-dashboard-cell-date">{{ $letter->received_date?->format('d-m-Y') ?? '-' }}</td>
                                        <td>
                                            <x-incoming-letter-status-badge :status="$letter->status" />
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td class="rs-dashboard-empty rs-empty-state text-center text-body-secondary py-4" colspan="4">
                                            <x-empty-state
                                                icon="fa-solid fa-envelope-open"
                                                title="Belum ada Surat Masuk"
                                                description="Surat masuk terbaru akan tampil di sini."
                                                compact
                                            />
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>

            <div class="col-12 col-xl-6">
                <section class="card rs-dashboard-panel h-100 shadow-sm" aria-labelledby="recentOutgoingTitle">
                    <div class="card-header d-flex align-items-center justify-content-between gap-3 py-3">
                        <h2 class="h5 mb-0" id="recentOutgoingTitle">Surat Keluar Terbaru</h2>
                        <a class="small fw-semibold text-decoration-none" href="{{ route('outgoing-letters.index') }}">Lihat Semua <span aria-hidden="true">→</span></a>
                    </div>
                    <div class="table-responsive">
                        <table class="table align-middle mb-0 rs-dashboard-table" data-testid="dashboard-recent-table">
                            <thead>
                                <tr>
                                    <th scope="col">Perihal</th>
                                    <th scope="col">Tujuan</th>
                                    <th scope="col">Tanggal</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($recentOutgoingLetters as $letter)
                                    <tr data-testid="dashboard-recent-outgoing-row">
                                        <td class="rs-dashboard-cell-main">
                                            <span class="fw-semibold text-body-emphasis d-block">{{ $letter->subject }}</span>
                                            <small class="text-body-secondary">{{ $letter->reference_code }}</small>
                                        </td>
                                        <td>{{ $letter->recipient_name }}</td>
                                        <td class="text-nowrap rs-dashboard-cell-date">{{ $letter->letter_date?->format('d-m-Y') ?? '-' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td class="rs-dashboard-empty rs-empty-state text-center text-body-secondary py-4" colspan="3">
                                            <x-empty-state
                                                title="Belum ada Surat Keluar"
                                                description="Surat keluar terbaru akan tampil di sini."
                                                compact
                                            />
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-12 col-xl-6">
                <section class="card rs-dashboard-panel h-100 shadow-sm" aria-labelledby="recentActivitiesTitle">
                    <div class="card-header py-3">
                        <h2 class="h5 mb-0" id="recentActivitiesTitle">Aktivitas Terbaru</h2>
                    </div>
                    <div class="card-body p-3 p-md-4">
                        <div class="rs-dashboard-timeline">
                            @forelse ($recentActivities as $activity)
                                <article class="rs-dashboard-activity position-relative ps-4 pb-4" data-testid="dashboard-activity">
                                    <span class="rs-dashboard-activity-marker" aria-hidden="true"></span>
                                    <div class="d-flex flex-column flex-sm-row justify-content-between gap-1 mb-1">
                                        <strong>{{ $activity['activity'] }}</strong>
                                        <time class="small text-body-secondary text-nowrap" datetime="{{ $activity['created_at']->toIso8601String() }}">
                                            {{ \App\Support\DateTimeFormatter::human($activity['created_at']) }}
                                        </time>
                                    </div>
                                    <div class="small text-body-secondary">
                                        {{ $activity['reference'] }} · {{ $activity['subject'] }}
                                    </div>
                                    <div class="small mt-1">Oleh {{ $activity['actor'] }}</div>
                                </article>
                            @empty
                                <div class="rs-dashboard-empty text-center text-body-secondary py-4">
                                    <x-empty-state
                                        icon="fa-solid fa-clock-rotate-left"
                                        title="Belum ada Aktivitas"
                                        description="Aktivitas surat terbaru akan tampil di sini."
                                        compact
                                    />
                                </div>
                            @endforelse
                        </div>
                    </div>
                </section>
            </div>

            <div class="col-12 col-xl-6">
                <section class="card rs-dashboard-panel h-100 shadow-sm" aria-labelledby="masterDataTitle">
                    <div class="card-header py-3">
                        <h2 class="h5 mb-0" id="masterDataTitle">Data Master</h2>
                    </div>
                    <div class="card-body p-3 p-md-4">
                        <div class="row g-3">
                            @foreach ([
                                ['Pengguna Aktif', $activeUsers, 'fa-user-check'],
                                ['Pengguna Nonaktif', $inactiveUsers, 'fa-user-slash'],
                                ['Divisi Aktif', $activeDivisions, 'fa-building-circle-check'],
                                ['Total Pengguna', $totalUsers, 'fa-users'],
                            ] as [$label, $value, $icon])
                                <div class="col-12 col-sm-6">
                                    <article class="rs-master-stat h-100 d-flex align-items-center gap-3">
                                        <span class="rs-master-stat-icon d-inline-flex align-items-center justify-content-center" aria-hidden="true"><i class="fa-solid {{ $icon }}"></i></span>
                                        <div><span class="small text-body-secondary d-block">{{ $label }}</span><strong class="fs-4" data-master-label="{{ $label }}">{{ $value }}</strong></div>
                                    </article>
                                </div>
                            @endforeach
                        </div>
                        <div class="d-flex flex-column flex-sm-row gap-2 mt-4">
                            <a class="btn btn-sm btn-outline-secondary rs-dashboard-master-action d-inline-flex align-items-center justify-content-center gap-2" href="{{ route('users.index') }}">
                                <i class="fa-solid fa-users" aria-hidden="true"></i>
                                <span>Kelola Pengguna</span>
                            </a>
                            <a class="btn btn-sm btn-outline-secondary rs-dashboard-master-action d-inline-flex align-items-center justify-content-center gap-2" href="{{ route('divisions.index') }}">
                                <i class="fa-solid fa-building" aria-hidden="true"></i>
                                <span>Kelola Divisi</span>
                            </a>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    @endif
@endsection
