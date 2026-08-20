@extends('layouts.dashboard')

@section('title', 'Surat Keluar')

@section('content')
    @php($hasFilters = collect($filters)->contains(fn ($value) => filled($value)))

    <header class="rs-page-header d-flex flex-column flex-md-row align-items-md-start justify-content-between gap-3 mb-4">
        <div>
            <h1 class="rs-page-title h3 mb-1">Surat Keluar</h1>
            <p class="rs-page-description text-body-secondary mb-0">Catat dan kelola dokumen surat keluar final setiap divisi.</p>
        </div>
        @can('create', App\Models\OutgoingLetter::class)
            <a
                class="btn btn-primary d-inline-flex align-items-center justify-content-center gap-2"
                href="{{ route('outgoing-letters.create') }}"
                data-testid="outgoing-letter-create-link"
            >
                <i class="fa-solid fa-plus" aria-hidden="true"></i>
                <span>Tambah Surat Keluar</span>
            </a>
        @endcan
    </header>

    <section class="card rs-card shadow-sm mb-4" aria-label="Pencarian dan filter surat keluar">
        <div class="card-body p-3 p-md-4">
            <form method="GET" action="{{ route('outgoing-letters.index') }}" class="row g-3 align-items-end">
                <div class="col-12 col-lg-6">
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
                            placeholder="Kode, nomor surat, tujuan, atau perihal"
                        >
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-lg-3">
                    <label class="form-label" for="division_id">Divisi</label>
                    <select class="form-select" id="division_id" name="division_id">
                        <option value="">Semua Divisi</option>
                        @foreach ($divisions as $division)
                            <option value="{{ $division->id }}" @selected(($filters['division_id'] ?? null) == $division->id)>
                                {{ $division->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-sm-6 col-lg-3">
                    <label class="form-label" for="letter_date">Tanggal Surat</label>
                    <input
                        class="form-control"
                        id="letter_date"
                        name="letter_date"
                        type="date"
                        value="{{ $filters['letter_date'] ?? '' }}"
                    >
                </div>
                <div class="col-12">
                    <div class="d-grid d-sm-flex gap-2">
                        <button class="btn btn-primary d-inline-flex align-items-center justify-content-center gap-2" type="submit">
                            <i class="fa-solid fa-filter" aria-hidden="true"></i>
                            <span>Terapkan Filter</span>
                        </button>
                        <a
                            class="btn btn-outline-secondary d-inline-flex align-items-center justify-content-center gap-2"
                            href="{{ route('outgoing-letters.index') }}"
                        >
                            <i class="fa-solid fa-rotate-left" aria-hidden="true"></i>
                            <span>Reset</span>
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </section>

    <section class="card rs-card shadow-sm overflow-hidden" aria-label="Daftar surat keluar">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 rs-table rs-outgoing-table">
                <thead class="table-light">
                    <tr>
                        <th scope="col">Kode Sistem</th>
                        <th scope="col">Nomor Surat</th>
                        <th scope="col">Tanggal Surat</th>
                        <th scope="col">Tujuan</th>
                        <th scope="col">Perihal</th>
                        <th scope="col">Divisi</th>
                        <th class="text-center" scope="col">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($outgoingLetters as $outgoingLetter)
                        <tr>
                            <td class="fw-semibold text-body-emphasis">{{ $outgoingLetter->reference_code ?: '-' }}</td>
                            <td>{{ $outgoingLetter->letter_number ?: '-' }}</td>
                            <td>{{ $outgoingLetter->letter_date?->format('d-m-Y') ?? '-' }}</td>
                            <td>{{ $outgoingLetter->recipient_name ?: '-' }}</td>
                            <td>{{ $outgoingLetter->subject ?: '-' }}</td>
                            <td>{{ $outgoingLetter->division?->name ?? '-' }}</td>
                            <td class="text-center">
                                <div class="rs-outgoing-actions d-flex flex-nowrap justify-content-center align-items-center gap-2">
                                    <a
                                        class="btn btn-sm btn-outline-primary d-inline-flex align-items-center gap-1"
                                        href="{{ route('outgoing-letters.show', $outgoingLetter) }}"
                                        data-testid="outgoing-letter-detail-link"
                                    >
                                        <i class="fa-solid fa-eye" aria-hidden="true"></i>
                                        <span>Detail</span>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td class="rs-empty-state text-center text-body-secondary py-5" colspan="7" data-testid="outgoing-letter-empty-state">
                                @if ($hasFilters)
                                    <x-empty-state
                                        icon="fa-solid fa-magnifying-glass"
                                        title="Data tidak ditemukan"
                                        description="Tidak ada data yang sesuai dengan pencarian atau filter."
                                        :action-url="route('outgoing-letters.index')"
                                        action-label="Reset"
                                    />
                                @else
                                    @can('create', App\Models\OutgoingLetter::class)
                                        <x-empty-state
                                            title="Belum ada Surat Keluar"
                                            description="Surat keluar yang dicatat akan tampil di sini."
                                            :action-url="route('outgoing-letters.create')"
                                            action-label="Tambah Surat Keluar"
                                            action-icon="fa-plus"
                                            action-variant="primary"
                                        />
                                    @else
                                        <x-empty-state
                                            title="Belum ada Surat Keluar"
                                            description="Surat keluar yang dicatat akan tampil di sini."
                                        />
                                    @endcan
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($outgoingLetters->hasPages())
            <div class="card-footer bg-body d-flex flex-column flex-xl-row align-items-xl-center justify-content-between gap-3 py-3">
                <span class="small text-body-secondary">
                    Halaman {{ $outgoingLetters->currentPage() }} dari {{ $outgoingLetters->lastPage() }}
                    ({{ $outgoingLetters->total() }} surat keluar)
                </span>
                <div class="rs-pagination">
                    {{ $outgoingLetters->onEachSide(1)->links('pagination::bootstrap-5') }}
                </div>
            </div>
        @endif
    </section>
@endsection
