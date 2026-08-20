@extends('layouts.dashboard')

@section('title', 'Laporan Sertifikat')

@section('content')
    @php($hasFilters = collect($filters)->contains(fn ($value) => filled($value)))

    <header class="rs-page-header d-flex flex-column flex-md-row align-items-md-start justify-content-between gap-3 mb-4">
        <div>
            <h1 class="rs-page-title h3 mb-1">Laporan Sertifikat</h1>
            <p class="rs-page-description text-body-secondary mb-0">Ringkasan arsip sertifikat peserta magang/PKL.</p>
        </div>
        <a
            class="btn btn-outline-primary d-inline-flex align-items-center justify-content-center gap-2"
            href="{{ route('reports.certificates.export', $filters) }}"
            data-testid="certificate-report-export"
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

    <section class="card rs-card shadow-sm mb-4" aria-label="Filter laporan sertifikat">
        <div class="card-body p-3 p-md-4">
            <form method="GET" action="{{ route('reports.certificates.index') }}" class="row g-3 align-items-end">
                <div class="col-12 col-lg-7 col-xl-6">
                    <label class="form-label" for="certificate-report-search">Pencarian</label>
                    <div class="input-group">
                        <span class="input-group-text bg-body" aria-hidden="true">
                            <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                        </span>
                        <input
                            class="form-control"
                            id="certificate-report-search"
                            name="search"
                            type="search"
                            value="{{ $filters['search'] ?? '' }}"
                            placeholder="Cari nama peserta, institusi, atau program studi/jurusan..."
                        >
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-lg-3 col-xl-2">
                    <label class="form-label" for="certificate-report-year">Tahun</label>
                    <select class="form-select" id="certificate-report-year" name="year">
                        <option value="">Semua Tahun</option>
                        @foreach ($years as $year)
                            <option value="{{ $year }}" @selected(($filters['year'] ?? null) == $year)>{{ $year }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12">
                    <div class="d-grid d-sm-flex flex-sm-wrap gap-2">
                        <button class="btn btn-primary d-inline-flex align-items-center justify-content-center gap-2" type="submit">
                            <i class="fa-solid fa-filter" aria-hidden="true"></i>
                            <span>Terapkan Filter</span>
                        </button>
                        <a class="btn btn-outline-secondary d-inline-flex align-items-center justify-content-center gap-2" href="{{ route('reports.certificates.index') }}">
                            <i class="fa-solid fa-rotate-left" aria-hidden="true"></i>
                            <span>Reset</span>
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </section>

    <section class="row g-3 mb-4" aria-label="Ringkasan laporan sertifikat">
        <div class="col-12 col-sm-6 col-xl-3">
            <article class="card rs-summary-card h-100 shadow-sm">
                <div class="card-body d-flex align-items-center gap-3">
                    <span class="rs-summary-icon d-inline-flex align-items-center justify-content-center">
                        <i class="fa-solid fa-award" aria-hidden="true"></i>
                    </span>
                    <div>
                        <div class="rs-summary-label">Total Sertifikat</div>
                        <strong class="rs-summary-value" data-summary-label="Total Sertifikat">{{ $summary['total'] }}</strong>
                    </div>
                </div>
            </article>
        </div>
    </section>

    <section class="card rs-card shadow-sm overflow-hidden" aria-label="Data laporan sertifikat">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 rs-table rs-report-certificate-table">
                <thead class="table-light">
                    <tr>
                        <th scope="col">No</th>
                        <th scope="col">Nama Peserta</th>
                        <th scope="col">Institusi</th>
                        <th scope="col">Program Studi / Jurusan</th>
                        <th scope="col">Periode</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($certificates as $certificate)
                        <tr data-testid="certificate-report-row">
                            <td>{{ $certificates->firstItem() + $loop->index }}</td>
                            <td class="fw-semibold text-body-emphasis">{{ $certificate->participant_name }}</td>
                            <td>{{ $certificate->institution_name }}</td>
                            <td>{{ $certificate->major_name }}</td>
                            <td class="text-nowrap">
                                {{ $certificate->start_date->locale('id')->translatedFormat('d F Y') }}
                                &ndash;
                                {{ $certificate->end_date->locale('id')->translatedFormat('d F Y') }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td class="rs-empty-state text-center text-body-secondary py-5" colspan="5" data-testid="certificate-report-empty-state">
                                @if (! $hasAnyCertificates)
                                    <x-empty-state
                                        icon="fa-solid fa-award"
                                        title="Belum ada Sertifikat"
                                        description="Data laporan sertifikat akan tampil di sini."
                                    />
                                @else
                                    <x-empty-state
                                        icon="fa-solid fa-magnifying-glass"
                                        title="Data tidak ditemukan"
                                        description="Tidak ada data yang sesuai dengan pencarian atau filter."
                                        :action-url="$hasFilters ? route('reports.certificates.index') : null"
                                        :action-label="$hasFilters ? 'Reset' : null"
                                    />
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($certificates->hasPages())
            <div class="card-footer bg-body d-flex flex-column flex-xl-row align-items-xl-center justify-content-between gap-3 py-3">
                <span class="small text-body-secondary">Halaman {{ $certificates->currentPage() }} dari {{ $certificates->lastPage() }} ({{ $certificates->total() }} sertifikat)</span>
                <div class="rs-pagination">{{ $certificates->onEachSide(1)->links('pagination::bootstrap-5') }}</div>
            </div>
        @endif
    </section>
@endsection
