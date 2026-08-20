@extends('layouts.dashboard')

@section('title', 'Detail Divisi')

@section('content')
    <header class="rs-page-header d-flex flex-column flex-lg-row align-items-lg-start justify-content-between gap-3 mb-4">
        <div>
            <h1 class="rs-page-title h3 mb-1">Detail Divisi</h1>
            <p class="rs-page-description text-body-secondary mb-0">Informasi lengkap divisi dan pengguna yang terdaftar.</p>
        </div>

        <div class="d-grid d-sm-flex flex-wrap gap-2">
            <a class="btn btn-outline-secondary d-inline-flex align-items-center justify-content-center gap-2" href="{{ route('divisions.index') }}">
                <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
                <span>Kembali</span>
            </a>
            <a class="btn btn-outline-primary d-inline-flex align-items-center justify-content-center gap-2" href="{{ route('divisions.edit', $division) }}">
                <i class="fa-solid fa-pen-to-square" aria-hidden="true"></i>
                <span>Edit Divisi</span>
            </a>
            <form
                method="POST"
                action="{{ route('divisions.status', $division) }}"
                data-confirmation
                data-confirmation-title="{{ $division->is_active ? 'Nonaktifkan Divisi' : 'Aktifkan Divisi' }}"
                data-confirmation-message="{{ $division->is_active
                    ? 'Divisi '.$division->name.' akan dinonaktifkan. Pastikan tidak ada pengguna aktif yang masih terhubung.'
                    : 'Divisi '.$division->name.' akan diaktifkan dan dapat kembali digunakan.' }}"
                data-confirmation-action-label="{{ $division->is_active ? 'Nonaktifkan' : 'Aktifkan' }}"
                data-confirmation-variant="{{ $division->is_active ? 'danger' : 'success' }}"
                data-confirmation-icon="{{ $division->is_active ? 'fa-circle-xmark' : 'fa-circle-check' }}"
                data-testid="division-status-form"
            >
                @csrf
                @method('PATCH')
                <input type="hidden" name="is_active" value="{{ $division->is_active ? 0 : 1 }}">
                <button
                    class="btn {{ $division->is_active ? 'btn-outline-danger' : 'btn-outline-success' }} d-inline-flex align-items-center justify-content-center gap-2 w-100"
                    type="submit"
                >
                    @if ($division->is_active)
                        <i class="fa-solid fa-circle-xmark" aria-hidden="true"></i>
                        <span>Nonaktifkan</span>
                    @else
                        <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
                        <span>Aktifkan</span>
                    @endif
                </button>
            </form>
        </div>
    </header>

    <section class="card rs-card shadow-sm mb-4" aria-label="Informasi divisi">
        <div class="card-header bg-body py-3">
            <h2 class="h5 mb-0">Informasi Divisi</h2>
        </div>
        <div class="card-body p-3 p-md-4">
            <dl class="row g-3 mb-0 rs-detail-list">
                <div class="col-12 col-md-6 rs-detail-item border-bottom pb-3">
                    <dt class="rs-detail-label small text-body-secondary">Nama Divisi</dt>
                    <dd>{{ $division->name }}</dd>
                </div>
                <div class="col-12 col-md-6 rs-detail-item border-bottom pb-3">
                    <dt class="rs-detail-label small text-body-secondary">Kode Divisi</dt>
                    <dd>{{ $division->code ?: '-' }}</dd>
                </div>
                <div class="col-12 col-md-6 rs-detail-item border-bottom pb-3">
                    <dt class="rs-detail-label small text-body-secondary">Status Divisi</dt>
                    <dd>
                        <span
                            class="badge {{ $division->is_active ? 'text-bg-success' : 'text-bg-secondary' }}"
                            data-testid="division-status-badge"
                        >
                            {{ $division->is_active ? 'Aktif' : 'Nonaktif' }}
                        </span>
                    </dd>
                </div>
                <div class="col-12 col-md-6 rs-detail-item border-bottom pb-3">
                    <dt class="rs-detail-label small text-body-secondary">Jumlah Pengguna</dt>
                    <dd>{{ $division->users_count }}</dd>
                </div>
                <div class="col-12 col-md-6 rs-detail-item border-bottom pb-3">
                    <dt class="rs-detail-label small text-body-secondary">Tanggal Dibuat</dt>
                    <dd>{{ $division->created_at?->format('d-m-Y') ?? '-' }}</dd>
                </div>
            </dl>
        </div>
    </section>

    <section class="card rs-card shadow-sm overflow-hidden" aria-label="Daftar pengguna divisi">
        <div class="card-header bg-body py-3">
            <h2 class="h5 mb-0">Daftar Pengguna Divisi</h2>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 rs-table">
                <thead class="table-light">
                    <tr>
                        <th scope="col">Nama</th>
                        <th scope="col">Email</th>
                        <th scope="col">Jabatan</th>
                        <th scope="col">Peran</th>
                        <th scope="col">Status Akun</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($division->users as $user)
                        <tr>
                            <td class="fw-semibold text-body-emphasis">{{ $user->name }}</td>
                            <td>{{ $user->email }}</td>
                            <td>{{ $user->position ?: '-' }}</td>
                            <td>{{ $user->role?->display_name ?? '-' }}</td>
                            <td>
                                <span class="badge {{ $user->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">
                                    {{ $user->is_active ? 'Aktif' : 'Nonaktif' }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td class="rs-empty-state text-center text-body-secondary py-5" colspan="5">
                                <x-empty-state
                                    icon="fa-solid fa-user-group"
                                    title="Belum ada Pengguna"
                                    description="Belum ada pengguna yang terdaftar pada divisi ini."
                                />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
