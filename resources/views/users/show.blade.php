@extends('layouts.dashboard')

@section('title', 'Detail Pengguna')

@section('content')
    <header class="rs-page-header mb-4">
        <h1 class="rs-page-title h3 mb-1">Detail Pengguna</h1>
        <p class="rs-page-description text-body-secondary mb-0">Informasi akun {{ $user->name }}.</p>
    </header>

    <section class="card rs-card rs-detail-card shadow-sm">
        <div class="card-body p-3 p-md-4">
            <dl class="row g-3 mb-0 rs-detail-list">
                <div class="col-12 col-md-6 rs-detail-item border-bottom pb-3">
                    <dt class="rs-detail-label small text-body-secondary">Nama</dt>
                    <dd>{{ $user->name }}</dd>
                </div>
                <div class="col-12 col-md-6 rs-detail-item border-bottom pb-3">
                    <dt class="rs-detail-label small text-body-secondary">Email</dt>
                    <dd>{{ $user->email }}</dd>
                </div>
                <div class="col-12 col-md-6 rs-detail-item border-bottom pb-3">
                    <dt class="rs-detail-label small text-body-secondary">Telepon</dt>
                    <dd>{{ $user->phone ?: '-' }}</dd>
                </div>
                <div class="col-12 col-md-6 rs-detail-item border-bottom pb-3">
                    <dt class="rs-detail-label small text-body-secondary">Nomor pegawai</dt>
                    <dd>{{ $user->employee_number ?: '-' }}</dd>
                </div>
                <div class="col-12 col-md-6 rs-detail-item border-bottom pb-3">
                    <dt class="rs-detail-label small text-body-secondary">Jabatan</dt>
                    <dd>{{ $user->position ?: '-' }}</dd>
                </div>
                <div class="col-12 col-md-6 rs-detail-item border-bottom pb-3">
                    <dt class="rs-detail-label small text-body-secondary">Peran</dt>
                    <dd>{{ $user->role?->display_name ?? '-' }}</dd>
                </div>
                <div class="col-12 col-md-6 rs-detail-item border-bottom pb-3">
                    <dt class="rs-detail-label small text-body-secondary">Divisi</dt>
                    <dd>{{ $user->division?->name ?? '-' }}</dd>
                </div>
                <div class="col-12 col-md-6 rs-detail-item border-bottom pb-3">
                    <dt class="rs-detail-label small text-body-secondary">Status</dt>
                    <dd>
                        <span class="badge {{ $user->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">
                            {{ $user->is_active ? 'Aktif' : 'Nonaktif' }}
                        </span>
                    </dd>
                </div>
            </dl>

            <div class="d-flex flex-wrap gap-2 mt-4">
                <a class="btn btn-outline-secondary d-inline-flex align-items-center gap-2" href="{{ route('users.index') }}">
                    <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
                    <span>Kembali</span>
                </a>
                <a
                    class="btn btn-outline-primary d-inline-flex align-items-center gap-2"
                    href="{{ route('users.edit', $user) }}"
                    data-testid="user-edit-link"
                >
                    <i class="fa-solid fa-pen-to-square" aria-hidden="true"></i>
                    <span>Edit</span>
                </a>
                @if (auth()->user()->is($user) && $user->is_active)
                    <span
                        class="badge text-bg-light border text-secondary d-inline-flex align-items-center gap-2 px-3 py-2"
                        title="Akun yang sedang digunakan tidak dapat dinonaktifkan"
                    >
                        <i class="fa-solid fa-shield-halved" aria-hidden="true"></i>
                        <span>Akun Anda</span>
                    </span>
                @else
                    <form
                        method="POST"
                        action="{{ route('users.status', $user) }}"
                        data-confirmation
                        data-confirmation-title="{{ $user->is_active ? 'Nonaktifkan Pengguna' : 'Aktifkan Pengguna' }}"
                        data-confirmation-message="{{ $user->is_active
                            ? 'Akun '.$user->name.' akan dinonaktifkan dan tidak dapat mengakses SIRAPI.'
                            : 'Akun '.$user->name.' akan diaktifkan dan dapat kembali mengakses SIRAPI.' }}"
                        data-confirmation-action-label="{{ $user->is_active ? 'Nonaktifkan' : 'Aktifkan' }}"
                        data-confirmation-variant="{{ $user->is_active ? 'danger' : 'success' }}"
                        data-confirmation-icon="{{ $user->is_active ? 'fa-circle-xmark' : 'fa-circle-check' }}"
                        data-testid="user-status-form"
                    >
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="is_active" value="{{ $user->is_active ? 0 : 1 }}">
                        <button
                            class="btn {{ $user->is_active ? 'btn-outline-danger' : 'btn-outline-success' }} d-inline-flex align-items-center gap-2"
                            type="submit"
                        >
                            @if ($user->is_active)
                                <i class="fa-solid fa-circle-xmark" aria-hidden="true"></i>
                                <span>Nonaktifkan</span>
                            @else
                                <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
                                <span>Aktifkan</span>
                            @endif
                        </button>
                    </form>
                @endif
            </div>
        </div>
    </section>
@endsection
