@extends('layouts.dashboard')

@section('title', 'Data Pengguna')

@section('content')
    @php($hasFilters = collect($filters)->contains(fn ($value) => filled($value)))

    <header class="rs-page-header d-flex flex-column flex-md-row align-items-md-start justify-content-between gap-3 mb-4">
        <div>
            <h1 class="rs-page-title h3 mb-1">Data Pengguna</h1>
            <p class="rs-page-description text-body-secondary mb-0">Daftar akun pengguna internal SIRAPI.</p>
        </div>
        <a class="btn btn-primary d-inline-flex align-items-center justify-content-center gap-2" href="{{ route('users.create') }}">
                <i class="fa-solid fa-plus" aria-hidden="true"></i>
            <span>Tambah Pengguna</span>
        </a>
    </header>

    <section class="card rs-card shadow-sm mb-4" aria-label="Filter pengguna">
        <div class="card-body p-3 p-md-4">
            <form method="GET" action="{{ route('users.index') }}" class="rs-user-filter-form row g-3 align-items-end">
                <div class="col-12 col-lg-6 col-xxl-3">
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
                            placeholder="Nama atau email"
                        >
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-lg-4 col-xl-3 col-xxl-2">
                    <label class="form-label" for="role">Peran</label>
                    <select class="form-select" id="role" name="role">
                        <option value="">Semua peran</option>
                        @foreach ($roles as $role)
                            <option value="{{ $role->id }}" @selected(($filters['role'] ?? null) == $role->id)>
                                {{ $role->display_name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-sm-6 col-lg-4 col-xl-3 col-xxl-2">
                    <label class="form-label" for="division">Divisi</label>
                    <select class="form-select" id="division" name="division">
                        <option value="">Semua divisi</option>
                        @foreach ($divisions as $division)
                            <option value="{{ $division->id }}" @selected(($filters['division'] ?? null) == $division->id)>
                                {{ $division->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-sm-6 col-lg-4 col-xl-3 col-xxl-2">
                    <label class="form-label" for="status">Status akun</label>
                    <select class="form-select" id="status" name="status">
                        <option value="">Semua status</option>
                        <option value="active" @selected(($filters['status'] ?? null) === 'active')>Aktif</option>
                        <option value="inactive" @selected(($filters['status'] ?? null) === 'inactive')>Nonaktif</option>
                    </select>
                </div>
                <div class="col-12 col-lg-8 col-xl-6 col-xxl-3">
                    <div class="rs-user-filter-actions">
                        <button class="btn btn-primary d-inline-flex align-items-center justify-content-center gap-2" type="submit">
                            <i class="fa-solid fa-filter" aria-hidden="true"></i>
                            <span>Terapkan Filter</span>
                        </button>
                        <a class="btn btn-outline-secondary d-inline-flex align-items-center justify-content-center gap-2" href="{{ route('users.index') }}">
                            <i class="fa-solid fa-rotate-left" aria-hidden="true"></i>
                            <span>Reset</span>
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </section>

    <section class="card rs-card shadow-sm overflow-hidden" aria-label="Daftar pengguna">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 rs-table">
                <thead class="table-light">
                    <tr>
                        <th scope="col">Nama</th>
                        <th scope="col">Email</th>
                        <th scope="col">Nomor pegawai</th>
                        <th scope="col">Jabatan</th>
                        <th scope="col">Divisi</th>
                        <th class="text-center" scope="col">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($users as $user)
                        <tr>
                            <td class="fw-semibold text-body-emphasis">{{ $user->name }}</td>
                            <td>{{ $user->email }}</td>
                            <td>{{ $user->employee_number ?: '-' }}</td>
                            <td>{{ $user->position ?: '-' }}</td>
                            <td>{{ $user->division?->name ?: '-' }}</td>
                            <td class="text-center">
                                <div class="d-flex justify-content-center align-items-center">
                                    <a
                                        class="btn btn-sm btn-outline-primary d-inline-flex align-items-center gap-1"
                                        href="{{ route('users.show', $user) }}"
                                        aria-label="Lihat detail {{ $user->name }}"
                                    >
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
                                        :action-url="route('users.index')"
                                        action-label="Reset"
                                    />
                                @else
                                    <x-empty-state
                                        icon="fa-solid fa-users"
                                        title="Belum ada Pengguna"
                                        description="Akun pengguna yang dibuat akan tampil di sini."
                                        :action-url="route('users.create')"
                                        action-label="Tambah Pengguna"
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

        @if ($users->hasPages())
            <div class="card-footer bg-body d-flex flex-column flex-xl-row align-items-xl-center justify-content-between gap-3 py-3">
                <span class="small text-body-secondary">
                    Halaman {{ $users->currentPage() }} dari {{ $users->lastPage() }} ({{ $users->total() }} pengguna)
                </span>
                <div class="rs-pagination">
                    {{ $users->onEachSide(1)->links('pagination::bootstrap-5') }}
                </div>
            </div>
        @endif
    </section>
@endsection
