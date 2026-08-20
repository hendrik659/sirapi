@extends('layouts.dashboard')

@php($editing = isset($division))
@section('title', $editing ? 'Edit Divisi' : 'Tambah Divisi')

@section('content')
    <header class="rs-page-header mb-4">
        <h1 class="rs-page-title h3 mb-1">{{ $editing ? 'Edit Divisi' : 'Tambah Divisi' }}</h1>
        <p class="rs-page-description text-body-secondary mb-0">
            {{ $editing ? 'Perbarui nama atau kode divisi.' : 'Tambahkan data divisi baru ke dalam sistem.' }}
        </p>
    </header>

    <form
        class="card rs-card rs-form-card shadow-sm"
        method="POST"
        action="{{ $editing ? route('divisions.update', $division) : route('divisions.store') }}"
    >
        @csrf
        @if ($editing)
            @method('PUT')
        @endif

        <div class="card-body p-3 p-md-4">
            <div class="row g-3">
                <div class="col-12 col-md-6">
                    <label class="form-label" for="name">Nama Divisi <span class="text-danger" aria-hidden="true">*</span><span class="visually-hidden"> (wajib)</span></label>
                    <input
                        class="form-control @error('name') is-invalid @enderror"
                        id="name"
                        name="name"
                        type="text"
                        value="{{ old('name', $division->name ?? '') }}"
                        maxlength="100"
                        required
                    >
                    @error('name')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-12 col-md-6">
                    <label class="form-label" for="code">Kode Divisi <span class="text-danger" aria-hidden="true">*</span><span class="visually-hidden"> (wajib)</span></label>
                    <input
                        class="form-control @error('code') is-invalid @enderror"
                        id="code"
                        name="code"
                        type="text"
                        value="{{ old('code', $division->code ?? '') }}"
                        maxlength="20"
                        autocomplete="off"
                        required
                    >
                    <div class="form-text">Gunakan kode singkat tanpa spasi. Kode akan disimpan dalam huruf kapital.</div>
                    @error('code')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="d-grid d-sm-flex flex-wrap gap-2 mt-4">
                <button class="btn btn-primary d-inline-flex align-items-center justify-content-center gap-2" type="submit">
                    <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i>
                    <span>{{ $editing ? 'Simpan Perubahan' : 'Simpan' }}</span>
                </button>
                <a
                    class="btn btn-outline-secondary d-inline-flex align-items-center justify-content-center gap-2"
                    href="{{ $editing ? route('divisions.show', $division) : route('divisions.index') }}"
                >
                    <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
                    <span>Kembali</span>
                </a>
            </div>
        </div>
    </form>
@endsection
