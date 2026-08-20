@extends('layouts.dashboard')

@section('title', 'Laporan Surat Masuk')

@section('content')
    @php
        $statusLabels = \App\Support\IncomingLetterStatusPresenter::labels();
        $priorityLabels = ['biasa' => 'Biasa', 'segera' => 'Segera'];
        $hasFilters = collect($filters)->contains(fn ($value) => filled($value));
    @endphp

    <header class="rs-page-header d-flex flex-column flex-md-row align-items-md-start justify-content-between gap-3 mb-4">
        <div>
            <h1 class="rs-page-title h3 mb-1">Laporan Surat Masuk</h1>
            <p class="rs-page-description text-body-secondary mb-0">Laporan hanya-baca berdasarkan tanggal surat diterima.</p>
        </div>
        <a
            class="btn btn-outline-primary d-inline-flex align-items-center justify-content-center gap-2"
            href="{{ route('reports.incoming-letters.export', $filters) }}"
            data-testid="incoming-report-export"
        >
            <i class="fa-solid fa-file-excel" aria-hidden="true"></i>
            <span>Ekspor Excel</span>
        </a>
    </header>

    @if ($errors->any())
        <div class="alert alert-danger d-flex align-items-start gap-2" role="alert">
            <i class="fa-solid fa-circle-exclamation mt-1" aria-hidden="true"></i>
            <div>
                <div class="fw-semibold mb-1">Filter belum dapat diterapkan.</div>
                <ul class="mb-0 ps-3">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    <section class="card rs-card shadow-sm mb-4" aria-label="Filter laporan surat masuk">
        <div class="card-body p-3 p-md-4">
            <form method="GET" action="{{ route('reports.incoming-letters.index') }}" class="row g-3 align-items-end">
                <div class="col-12 col-xl-4">
                    <label class="form-label" for="incoming-report-search">Pencarian</label>
                    <div class="input-group">
                        <span class="input-group-text bg-body" aria-hidden="true">
                            <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                        </span>
                        <input
                            class="form-control"
                            id="incoming-report-search"
                            name="search"
                            type="search"
                            value="{{ $filters['search'] ?? '' }}"
                            placeholder="Agenda, nomor surat, pengirim, atau perihal"
                        >
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-lg-3 col-xl-2">
                    <label class="form-label" for="incoming-report-start-date">Tanggal Awal</label>
                    <input class="form-control" id="incoming-report-start-date" name="start_date" type="date" value="{{ $filters['start_date'] ?? '' }}">
                </div>
                <div class="col-12 col-sm-6 col-lg-3 col-xl-2">
                    <label class="form-label" for="incoming-report-end-date">Tanggal Akhir</label>
                    <input class="form-control" id="incoming-report-end-date" name="end_date" type="date" value="{{ $filters['end_date'] ?? '' }}">
                </div>
                <div class="col-12 col-sm-6 col-lg-3 col-xl-2">
                    <label class="form-label" for="incoming-report-status">Status</label>
                    <select class="form-select" id="incoming-report-status" name="status">
                        <option value="">Semua Status</option>
                        @foreach ($statusLabels as $value => $label)
                            <option value="{{ $value }}" @selected(($filters['status'] ?? null) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-sm-6 col-lg-3 col-xl-2">
                    <label class="form-label" for="incoming-report-priority">Prioritas</label>
                    <select class="form-select" id="incoming-report-priority" name="priority">
                        <option value="">Semua Prioritas</option>
                        @foreach ($priorityLabels as $value => $label)
                            <option value="{{ $value }}" @selected(($filters['priority'] ?? null) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                @if ($hasGlobalScope)
                    <div class="col-12 col-sm-6 col-lg-4 col-xl-3" data-testid="incoming-report-division-filter">
                        <label class="form-label" for="incoming-report-division">Divisi Tujuan</label>
                        <select class="form-select" id="incoming-report-division" name="division_id">
                            <option value="">Semua Divisi</option>
                            @foreach ($divisions as $division)
                                <option value="{{ $division->id }}" @selected(($filters['division_id'] ?? null) == $division->id)>{{ $division->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @else
                    <div class="col-12 col-sm-6 col-lg-4 col-xl-3" data-testid="incoming-report-own-division">
                        <div class="form-label">Divisi Saya</div>
                        <div class="form-control bg-body-tertiary" aria-label="Divisi pengguna">{{ $divisionLabel }}</div>
                    </div>
                @endif

                <div class="col-12">
                    <div class="d-grid d-sm-flex gap-2">
                        <button class="btn btn-primary d-inline-flex align-items-center justify-content-center gap-2" type="submit">
                            <i class="fa-solid fa-filter" aria-hidden="true"></i>
                            <span>Terapkan Filter</span>
                        </button>
                        <a class="btn btn-outline-secondary d-inline-flex align-items-center justify-content-center gap-2" href="{{ route('reports.incoming-letters.index') }}">
                            <i class="fa-solid fa-rotate-left" aria-hidden="true"></i>
                            <span>Reset</span>
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </section>

    <section class="row g-3 mb-4" aria-label="Ringkasan laporan surat masuk">
        @foreach ([
            ['Total Surat Masuk', $summary['total'], 'fa-envelope-open-text'],
            ['Baru Diterima', $summary['baru_diterima'], 'fa-inbox'],
            ['Menunggu Pemeriksaan', $summary['menunggu_pemeriksaan'], 'fa-magnifying-glass'],
            ['Selesai', $summary['selesai'], 'fa-circle-check'],
        ] as [$label, $value, $icon])
            <div class="col-12 col-sm-6 col-xl-3">
                <article class="card rs-summary-card h-100 shadow-sm">
                    <div class="card-body d-flex align-items-center gap-3">
                        <span class="rs-summary-icon d-inline-flex align-items-center justify-content-center">
                            <i class="fa-solid {{ $icon }}" aria-hidden="true"></i>
                        </span>
                        <div>
                            <div class="rs-summary-label">{{ $label }}</div>
                            <strong class="rs-summary-value" data-summary-label="{{ $label }}">{{ $value }}</strong>
                        </div>
                    </div>
                </article>
            </div>
        @endforeach
    </section>

    @if ($hasGlobalScope)
        <section class="card rs-card shadow-sm mb-4" aria-label="Rekap surat masuk per divisi" data-testid="incoming-report-recap">
            <div class="card-header bg-body fw-semibold">Rekap per Divisi Tujuan</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0 rs-table rs-report-recap-table">
                        <thead class="table-light">
                            <tr><th scope="col">Divisi</th><th class="text-end" scope="col">Jumlah Surat</th></tr>
                        </thead>
                        <tbody>
                            @forelse ($recap as $item)
                                <tr>
                                    <td>{{ $item->division_name ?? 'Belum Ditentukan' }}</td>
                                    <td class="text-end fw-semibold">{{ $item->total }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td class="rs-empty-state text-center text-body-secondary py-3" colspan="2">
                                        <x-empty-state
                                            title="Belum ada data rekap"
                                            description="Rekap divisi akan tampil setelah data surat tersedia."
                                            compact
                                        />
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    @else
        <div class="alert alert-info d-flex align-items-center gap-2" role="status">
            <i class="fa-solid fa-building" aria-hidden="true"></i>
            <span>Divisi: <strong>{{ $divisionLabel }}</strong> · Total hasil: <strong>{{ $summary['total'] }}</strong></span>
        </div>
    @endif

    <section class="card rs-card shadow-sm overflow-hidden" aria-label="Data laporan surat masuk">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 rs-table rs-report-incoming-table">
                <thead class="table-light">
                    <tr>
                        <th scope="col">No</th>
                        <th scope="col">Agenda</th>
                        <th scope="col">Nomor Surat</th>
                        <th scope="col">Tanggal Diterima</th>
                        <th scope="col">Pengirim</th>
                        <th scope="col">Perihal</th>
                        <th scope="col">Divisi Tujuan</th>
                        <th scope="col">Prioritas</th>
                        <th scope="col">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($incomingLetters as $incomingLetter)
                        <tr data-testid="incoming-report-row">
                            <td>{{ $incomingLetters->firstItem() + $loop->index }}</td>
                            <td class="fw-semibold text-body-emphasis">{{ $incomingLetter->agenda_number }}</td>
                            <td>{{ $incomingLetter->letter_number ?: '-' }}</td>
                            <td>{{ $incomingLetter->received_date?->format('d-m-Y') ?? '-' }}</td>
                            <td>{{ $incomingLetter->sender_name }}</td>
                            <td>{{ $incomingLetter->subject }}</td>
                            <td>{{ $incomingLetter->destinationDivision?->name ?? 'Belum Ditentukan' }}</td>
                            <td><span class="badge {{ $incomingLetter->priority === 'segera' ? 'text-bg-danger' : 'text-bg-primary' }}">{{ $priorityLabels[$incomingLetter->priority] ?? $incomingLetter->priority }}</span></td>
                            <td><x-incoming-letter-status-badge :status="$incomingLetter->status" /></td>
                        </tr>
                    @empty
                        <tr>
                            <td class="rs-empty-state text-center text-body-secondary py-5" colspan="9" data-testid="incoming-report-empty-state">
                                @if ($hasFilters)
                                    <x-empty-state
                                        icon="fa-solid fa-magnifying-glass"
                                        title="Data tidak ditemukan"
                                        description="Tidak ada data yang sesuai dengan pencarian atau filter."
                                        :action-url="route('reports.incoming-letters.index')"
                                        action-label="Reset"
                                    />
                                @else
                                    <x-empty-state
                                        title="Belum ada Surat Masuk"
                                        description="Data laporan surat masuk akan tampil di sini."
                                    />
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($incomingLetters->hasPages())
            <div class="card-footer bg-body d-flex flex-column flex-xl-row align-items-xl-center justify-content-between gap-3 py-3">
                <span class="small text-body-secondary">Halaman {{ $incomingLetters->currentPage() }} dari {{ $incomingLetters->lastPage() }} ({{ $incomingLetters->total() }} surat)</span>
                <div class="rs-pagination">{{ $incomingLetters->onEachSide(1)->links('pagination::bootstrap-5') }}</div>
            </div>
        @endif
    </section>
@endsection
