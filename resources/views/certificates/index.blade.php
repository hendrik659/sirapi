@extends('layouts.dashboard')

@section('title', 'Arsip Sertifikat')

@section('content')
    @php
        $canManageCertificates = auth()->user()?->role?->slug === 'ketua_divisi'
            && auth()->user()?->division?->code === 'SDM';
        $hasFilters = collect($filters)->contains(fn ($value) => filled($value));
        $formatDate = static fn ($date) => $date?->locale('id')->translatedFormat('d F Y') ?? '-';
    @endphp

    <header class="rs-page-header d-flex flex-column flex-md-row align-items-md-start justify-content-between gap-3 mb-4">
        <div>
            <h1 class="rs-page-title h3 mb-1">Arsip Sertifikat</h1>
            <p class="rs-page-description text-body-secondary mb-0">Daftar arsip sertifikat peserta magang/PKL.</p>
        </div>
        @if ($canManageCertificates)
            <a
                class="btn btn-primary d-inline-flex align-items-center justify-content-center gap-2"
                href="{{ route('dashboard.certificates.create') }}"
                data-testid="certificate-create-link"
            >
                <i class="fa-solid fa-plus" aria-hidden="true"></i>
                <span>Tambah Sertifikat</span>
            </a>
        @endif
    </header>

    <section class="card rs-card shadow-sm mb-4" aria-label="Pencarian dan filter arsip sertifikat">
        <div class="card-body p-3 p-md-4">
            <form class="row g-3 align-items-end" method="GET" action="{{ route('dashboard.certificates.index') }}">
                <div class="col-12 col-lg-8">
                    <label class="form-label" for="search">Pencarian</label>
                    <div class="input-group">
                        <span class="input-group-text bg-body" aria-hidden="true">
                            <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                        </span>
                        <input
                            class="form-control"
                            id="search"
                            name="search"
                            type="search"
                            value="{{ $filters['search'] ?? '' }}"
                            placeholder="Cari nama peserta, institusi, atau program studi/jurusan..."
                        >
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-lg-4">
                    <label class="form-label" for="year">Tahun Selesai</label>
                    <select class="form-select" id="year" name="year">
                        <option value="">Semua Tahun</option>
                        @foreach ($years as $year)
                            <option value="{{ $year }}" @selected(($filters['year'] ?? null) == $year)>{{ $year }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12">
                    <div class="d-grid d-sm-flex gap-2">
                        <button class="btn btn-primary d-inline-flex align-items-center justify-content-center gap-2" type="submit">
                            <i class="fa-solid fa-filter" aria-hidden="true"></i>
                            <span>Terapkan Filter</span>
                        </button>
                        <a class="btn btn-outline-secondary d-inline-flex align-items-center justify-content-center gap-2" href="{{ route('dashboard.certificates.index') }}">
                            <i class="fa-solid fa-rotate-left" aria-hidden="true"></i>
                            <span>Reset</span>
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </section>

    <section class="card rs-card shadow-sm overflow-hidden" aria-label="Daftar arsip sertifikat">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 rs-table rs-certificate-table">
                <thead class="table-light">
                    <tr>
                        <th scope="col">No</th>
                        <th scope="col">Nama Peserta</th>
                        <th scope="col">Institusi</th>
                        <th scope="col">Program Studi / Jurusan</th>
                        <th scope="col">Periode</th>
                        <th class="text-center" scope="col">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($certificates as $certificate)
                        <tr>
                            <td>{{ ($certificates->firstItem() ?? 1) + $loop->index }}</td>
                            <td class="fw-semibold text-body-emphasis">{{ $certificate->participant_name }}</td>
                            <td>{{ $certificate->institution_name }}</td>
                            <td>{{ $certificate->major_name }}</td>
                            <td class="text-nowrap">{{ $formatDate($certificate->start_date) }} – {{ $formatDate($certificate->end_date) }}</td>
                            <td class="text-center">
                                <div class="d-flex flex-wrap justify-content-center gap-2">
                                    <a class="btn btn-sm btn-outline-primary d-inline-flex align-items-center gap-1" href="{{ route('dashboard.certificates.show', $certificate) }}">
                                        <i class="fa-solid fa-eye" aria-hidden="true"></i>
                                        <span>Detail</span>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td class="rs-empty-state text-center text-body-secondary py-5" colspan="6">
                                @if ($hasFilters)
                                    <x-empty-state
                                        icon="fa-solid fa-magnifying-glass"
                                        title="Data tidak ditemukan"
                                        description="Tidak ada data yang sesuai dengan pencarian atau filter."
                                        :action-url="route('dashboard.certificates.index')"
                                        action-label="Reset"
                                    />
                                @else
                                    <x-empty-state
                                        icon="fa-solid fa-award"
                                        title="Belum ada Sertifikat"
                                        description="Arsip sertifikat peserta akan tampil di sini."
                                        :action-url="$canManageCertificates ? route('dashboard.certificates.create') : null"
                                        :action-label="$canManageCertificates ? 'Tambah Sertifikat' : null"
                                        action-icon="fa-plus"
                                        action-variant="primary"
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
                <span class="small text-body-secondary">
                    Halaman {{ $certificates->currentPage() }} dari {{ $certificates->lastPage() }}
                    ({{ $certificates->total() }} sertifikat)
                </span>
                <div class="rs-pagination">
                    {{ $certificates->onEachSide(1)->links('pagination::bootstrap-5') }}
                </div>
            </div>
        @endif
    </section>
@endsection
