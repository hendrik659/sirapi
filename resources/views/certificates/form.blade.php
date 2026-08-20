@extends('layouts.dashboard')

@php($isEdit = isset($certificate))

@section('title', $isEdit ? 'Edit Sertifikat' : 'Tambah Sertifikat')

@section('content')
    <header class="rs-page-header mb-4">
        <h1 class="rs-page-title h3 mb-1">{{ $isEdit ? 'Edit Sertifikat' : 'Tambah Sertifikat' }}</h1>
        <p class="rs-page-description text-body-secondary mb-0">
            {{ $isEdit ? 'Perbarui metadata atau dokumen arsip sertifikat.' : 'Tambahkan arsip sertifikat peserta magang/PKL.' }}
        </p>
    </header>

    <form
        class="rs-document-form-layout"
        method="POST"
        action="{{ $isEdit ? route('dashboard.certificates.update', $certificate) : route('dashboard.certificates.store') }}"
        enctype="multipart/form-data"
        novalidate
    >
        @csrf
        @if ($isEdit)
            @method('PUT')
        @endif

        <div class="row g-4 align-items-start">
            <div class="col-12 col-lg-7">
                <section class="card rs-card rs-document-form-card shadow-sm">
                    <div class="card-body p-3 p-md-4">
            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label" for="participant_name">Nama Peserta <span class="text-danger" aria-hidden="true">*</span><span class="visually-hidden"> (wajib)</span></label>
                    <input
                        class="form-control @error('participant_name') is-invalid @enderror"
                        id="participant_name"
                        name="participant_name"
                        type="text"
                        value="{{ old('participant_name', $certificate->participant_name ?? '') }}"
                        maxlength="255"
                        required
                    >
                    @error('participant_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-12 col-md-6">
                    <label class="form-label" for="institution_name">Asal Institusi <span class="text-danger" aria-hidden="true">*</span><span class="visually-hidden"> (wajib)</span></label>
                    <input
                        class="form-control @error('institution_name') is-invalid @enderror"
                        id="institution_name"
                        name="institution_name"
                        type="text"
                        value="{{ old('institution_name', $certificate->institution_name ?? '') }}"
                        maxlength="255"
                        required
                    >
                    @error('institution_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-12 col-md-6">
                    <label class="form-label" for="major_name">Program Studi / Jurusan <span class="text-danger" aria-hidden="true">*</span><span class="visually-hidden"> (wajib)</span></label>
                    <input
                        class="form-control @error('major_name') is-invalid @enderror"
                        id="major_name"
                        name="major_name"
                        type="text"
                        value="{{ old('major_name', $certificate->major_name ?? '') }}"
                        maxlength="255"
                        required
                    >
                    @error('major_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-12 col-md-6">
                    <label class="form-label" for="start_date">Tanggal Mulai <span class="text-danger" aria-hidden="true">*</span><span class="visually-hidden"> (wajib)</span></label>
                    <input
                        class="form-control @error('start_date') is-invalid @enderror"
                        id="start_date"
                        name="start_date"
                        type="date"
                        value="{{ old('start_date', isset($certificate) ? $certificate->start_date?->format('Y-m-d') : '') }}"
                        required
                    >
                    @error('start_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-12 col-md-6">
                    <label class="form-label" for="end_date">Tanggal Selesai <span class="text-danger" aria-hidden="true">*</span><span class="visually-hidden"> (wajib)</span></label>
                    <input
                        class="form-control @error('end_date') is-invalid @enderror"
                        id="end_date"
                        name="end_date"
                        type="date"
                        value="{{ old('end_date', isset($certificate) ? $certificate->end_date?->format('Y-m-d') : '') }}"
                        required
                    >
                    @error('end_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-12">
                    <label class="form-label" for="document">
                        Dokumen Sertifikat
                        @unless ($isEdit)<span class="text-danger" aria-hidden="true">*</span><span class="visually-hidden"> (wajib)</span>@endunless
                    </label>
                    <input
                        class="form-control @error('document') is-invalid @enderror"
                        id="document"
                        name="document"
                        type="file"
                        accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png"
                        data-certificate-document
                        @required(! $isEdit)
                    >
                    <div class="form-text">
                        PDF, JPG, JPEG, atau PNG. Maksimal 5 MB.
                        @if ($isEdit) Kosongkan dokumen jika tidak ingin mengganti file. @endif
                    </div>
                    @error('document')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <div class="invalid-feedback d-none mt-2" role="alert" data-certificate-document-error></div>
                </div>
            </div>

                        <div class="d-grid d-sm-flex flex-wrap gap-2 mt-4">
                            <a class="btn btn-outline-secondary d-inline-flex align-items-center justify-content-center gap-2" href="{{ route('dashboard.certificates.index') }}">
                                <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
                                <span>Kembali</span>
                            </a>
                            <button class="btn btn-primary d-inline-flex align-items-center justify-content-center gap-2" type="submit">
                                <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i>
                                <span>{{ $isEdit ? 'Simpan Perubahan' : 'Simpan' }}</span>
                            </button>
                        </div>
                    </div>
                </section>
            </div>

            <div class="col-12 col-lg-5">
                <aside class="rs-document-preview-sticky" aria-label="Panel preview dokumen">

            <section class="card rs-card rs-document-preview-card shadow-sm" aria-labelledby="certificateDocumentPreviewTitle" data-certificate-document-preview-area>
                <div class="card-header bg-body py-3">
                    <h2 class="h5 mb-0" id="certificateDocumentPreviewTitle">Preview Dokumen</h2>
                </div>
                <div class="card-body p-3">
                    <dl class="row g-2 small rs-document-meta mb-3">
                        <div class="col-12">
                            <dt class="text-body-secondary">Nama File</dt>
                            <dd class="text-break mb-0" data-certificate-document-name>{{ $isEdit ? $certificate->original_document_name : '-' }}</dd>
                        </div>
                        <div class="col-12 col-sm-6">
                            <dt class="text-body-secondary">Tipe</dt>
                            <dd class="mb-0" data-certificate-document-type>{{ $isEdit ? $certificate->document_mime_type : '-' }}</dd>
                        </div>
                        <div class="col-12 col-sm-6">
                            <dt class="text-body-secondary">Ukuran</dt>
                            <dd class="mb-0" data-certificate-document-size>
                                {{ $isEdit ? number_format($certificate->document_size / 1024, 1, ',', '.').' KB' : '-' }}
                            </dd>
                        </div>
                    </dl>
                    <div class="rs-document-preview" data-certificate-document-preview-content>
                        @if ($isEdit && $certificate->document_mime_type === 'application/pdf')
                            <object
                                class="rs-document-frame"
                                data="{{ route('dashboard.certificates.preview', $certificate) }}"
                                type="application/pdf"
                                title="Preview {{ $certificate->original_document_name }}"
                            >
                                <p class="mb-0">Dokumen tidak dapat ditampilkan pada browser ini. Silakan buka dokumen melalui preview.</p>
                            </object>
                        @elseif ($isEdit && str_starts_with($certificate->document_mime_type, 'image/'))
                            <img
                                class="rs-document-image"
                                src="{{ route('dashboard.certificates.preview', $certificate) }}"
                                alt="Preview {{ $certificate->original_document_name }}"
                            >
                        @elseif ($isEdit)
                            <p class="mb-0">Dokumen tidak dapat ditampilkan pada browser ini.</p>
                        @else
                            <div class="rs-document-preview-empty d-flex flex-column align-items-center justify-content-center gap-2 p-4 text-body-secondary">
                                <i class="fa-regular fa-file-lines" aria-hidden="true"></i>
                                <p class="mb-0">Belum ada dokumen dipilih. Pilih file untuk melihat preview.</p>
                            </div>
                        @endif
                    </div>
                </div>
            </section>
                </aside>
            </div>
        </div>
    </form>
@endsection
