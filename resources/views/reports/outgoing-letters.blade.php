@extends('layouts.dashboard')

@section('title', 'Laporan Surat Keluar')

@section('content')
    @php($hasFilters = collect($filters)->contains(fn ($value) => filled($value)))

    <header class="rs-page-header d-flex flex-column flex-md-row align-items-md-start justify-content-between gap-3 mb-4">
        <div>
            <h1 class="rs-page-title h3 mb-1">Laporan Surat Keluar</h1>
            <p class="rs-page-description text-body-secondary mb-0">Laporan hanya-baca berdasarkan tanggal surat keluar.</p>
        </div>
        <a
            class="btn btn-outline-primary d-inline-flex align-items-center justify-content-center gap-2"
            href="{{ route('reports.outgoing-letters.export', $filters) }}"
            data-testid="outgoing-report-export"
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

    <section class="card rs-card shadow-sm mb-4" aria-label="Filter laporan surat keluar">
        <div class="card-body p-3 p-md-4">
            <form method="GET" action="{{ route('reports.outgoing-letters.index') }}" class="row g-3 align-items-end">
                <div class="col-12 col-lg-6 col-xl-4">
                    <label class="form-label" for="outgoing-report-search">Pencarian</label>
                    <div class="input-group">
                        <span class="input-group-text bg-body" aria-hidden="true">
                            <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                        </span>
                        <input
                            class="form-control"
                            id="outgoing-report-search"
                            name="search"
                            type="search"
                            value="{{ $filters['search'] ?? '' }}"
                            placeholder="Kode sistem, nomor surat, tujuan, atau perihal"
                        >
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-lg-3 col-xl-2">
                    <label class="form-label" for="outgoing-report-start-date">Tanggal Awal</label>
                    <input class="form-control" id="outgoing-report-start-date" name="start_date" type="date" value="{{ $filters['start_date'] ?? '' }}">
                </div>
                <div class="col-12 col-sm-6 col-lg-3 col-xl-2">
                    <label class="form-label" for="outgoing-report-end-date">Tanggal Akhir</label>
                    <input class="form-control" id="outgoing-report-end-date" name="end_date" type="date" value="{{ $filters['end_date'] ?? '' }}">
                </div>

                @if ($hasGlobalScope)
                    <div class="col-12 col-sm-6 col-lg-4 col-xl-3" data-testid="outgoing-report-division-filter">
                        <label class="form-label" for="outgoing-report-division">Divisi</label>
                        <select class="form-select" id="outgoing-report-division" name="division_id">
                            <option value="">Semua Divisi</option>
                            @foreach ($divisions as $division)
                                <option value="{{ $division->id }}" @selected(($filters['division_id'] ?? null) == $division->id)>{{ $division->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @else
                    <div class="col-12 col-sm-6 col-lg-4 col-xl-3" data-testid="outgoing-report-own-division">
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
                        <a class="btn btn-outline-secondary d-inline-flex align-items-center justify-content-center gap-2" href="{{ route('reports.outgoing-letters.index') }}">
                            <i class="fa-solid fa-rotate-left" aria-hidden="true"></i>
                            <span>Reset</span>
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </section>

    <section class="row g-3 mb-4" aria-label="Ringkasan laporan surat keluar">
        <div class="col-12 col-sm-6 col-xl-3">
            <article class="card rs-summary-card h-100 shadow-sm">
                <div class="card-body d-flex align-items-center gap-3">
                    <span class="rs-summary-icon d-inline-flex align-items-center justify-content-center"><i class="fa-solid fa-paper-plane" aria-hidden="true"></i></span>
                    <div><div class="rs-summary-label">Total Surat Keluar</div><strong class="rs-summary-value" data-summary-label="Total Surat Keluar">{{ $summary['total'] }}</strong></div>
                </div>
            </article>
        </div>
        @if ($hasGlobalScope)
            <div class="col-12 col-sm-6 col-xl-3">
                <article class="card rs-summary-card h-100 shadow-sm">
                    <div class="card-body d-flex align-items-center gap-3">
                        <span class="rs-summary-icon d-inline-flex align-items-center justify-content-center"><i class="fa-solid fa-building" aria-hidden="true"></i></span>
                        <div><div class="rs-summary-label">Jumlah Divisi</div><strong class="rs-summary-value" data-summary-label="Jumlah Divisi">{{ $summary['division_count'] }}</strong></div>
                    </div>
                </article>
            </div>
        @endif
    </section>

    @if ($hasGlobalScope)
        <section class="card rs-card shadow-sm mb-4" aria-label="Rekap surat keluar per divisi" data-testid="outgoing-report-recap">
            <div class="card-header bg-body fw-semibold">Rekap per Divisi</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0 rs-table rs-report-recap-table">
                        <thead class="table-light"><tr><th scope="col">Divisi</th><th class="text-end" scope="col">Jumlah Surat</th></tr></thead>
                        <tbody>
                            @forelse ($recap as $item)
                                <tr><td>{{ $item->division_name ?? '-' }}</td><td class="text-end fw-semibold">{{ $item->total }}</td></tr>
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

    <section class="card rs-card shadow-sm overflow-hidden" aria-label="Data laporan surat keluar">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 rs-table rs-report-outgoing-table">
                <thead class="table-light">
                    <tr>
                        <th scope="col">No</th>
                        <th scope="col">Kode Sistem</th>
                        <th scope="col">Nomor Surat</th>
                        <th scope="col">Tanggal Surat</th>
                        <th scope="col">Tujuan</th>
                        <th scope="col">Perihal</th>
                        <th scope="col">Divisi</th>
                        <th scope="col">Dicatat Oleh</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($outgoingLetters as $outgoingLetter)
                        <tr data-testid="outgoing-report-row">
                            <td>{{ $outgoingLetters->firstItem() + $loop->index }}</td>
                            <td class="fw-semibold text-body-emphasis">{{ $outgoingLetter->reference_code }}</td>
                            <td>{{ $outgoingLetter->letter_number }}</td>
                            <td>{{ $outgoingLetter->letter_date?->format('d-m-Y') ?? '-' }}</td>
                            <td>{{ $outgoingLetter->recipient_name }}</td>
                            <td>{{ $outgoingLetter->subject }}</td>
                            <td>{{ $outgoingLetter->division?->name ?? '-' }}</td>
                            <td>{{ $outgoingLetter->creator?->name ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td class="rs-empty-state text-center text-body-secondary py-5" colspan="8" data-testid="outgoing-report-empty-state">
                                @if ($hasFilters)
                                    <x-empty-state
                                        icon="fa-solid fa-magnifying-glass"
                                        title="Data tidak ditemukan"
                                        description="Tidak ada data yang sesuai dengan pencarian atau filter."
                                        :action-url="route('reports.outgoing-letters.index')"
                                        action-label="Reset"
                                    />
                                @else
                                    <x-empty-state
                                        title="Belum ada Surat Keluar"
                                        description="Data laporan surat keluar akan tampil di sini."
                                    />
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($outgoingLetters->hasPages())
            <div class="card-footer bg-body d-flex flex-column flex-xl-row align-items-xl-center justify-content-between gap-3 py-3">
                <span class="small text-body-secondary">Halaman {{ $outgoingLetters->currentPage() }} dari {{ $outgoingLetters->lastPage() }} ({{ $outgoingLetters->total() }} surat)</span>
                <div class="rs-pagination">{{ $outgoingLetters->onEachSide(1)->links('pagination::bootstrap-5') }}</div>
            </div>
        @endif
    </section>
@endsection
