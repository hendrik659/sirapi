@extends('layouts.dashboard')

@section('title', 'Tambah Surat Keluar')

@section('content')
    <header class="rs-page-header mb-4">
        <h1 class="rs-page-title h3 mb-1">Tambah Surat Keluar</h1>
        <p class="rs-page-description text-body-secondary mb-0">
            Catat dokumen final. Setelah disimpan, surat keluar langsung menjadi data hanya-baca.
        </p>
    </header>

    <form
        class="rs-document-form-layout"
        method="POST"
        action="{{ route('outgoing-letters.store') }}"
        enctype="multipart/form-data"
        novalidate
    >
        @csrf

        <div class="row g-4 align-items-start">
            <div class="col-12 col-lg-7">
                <section class="card rs-card rs-document-form-card shadow-sm">
                    <div class="card-body p-3 p-md-4">
            <div class="row g-3">
                <div class="col-12 col-md-6">
                    <label class="form-label" for="letter_number">Nomor Surat <span class="text-danger" aria-hidden="true">*</span><span class="visually-hidden"> (wajib)</span></label>
                    <input
                        class="form-control @error('letter_number') is-invalid @enderror"
                        id="letter_number"
                        name="letter_number"
                        type="text"
                        value="{{ old('letter_number') }}"
                        maxlength="100"
                        required
                    >
                    @error('letter_number')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-12 col-md-6">
                    <label class="form-label" for="letter_date">Tanggal Surat <span class="text-danger" aria-hidden="true">*</span><span class="visually-hidden"> (wajib)</span></label>
                    <input
                        class="form-control @error('letter_date') is-invalid @enderror"
                        id="letter_date"
                        name="letter_date"
                        type="date"
                        value="{{ old('letter_date') }}"
                        required
                    >
                    @error('letter_date')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-12">
                    <label class="form-label" for="recipient_name">Tujuan <span class="text-danger" aria-hidden="true">*</span><span class="visually-hidden"> (wajib)</span></label>
                    <input
                        class="form-control @error('recipient_name') is-invalid @enderror"
                        id="recipient_name"
                        name="recipient_name"
                        type="text"
                        value="{{ old('recipient_name') }}"
                        maxlength="255"
                        required
                    >
                    @error('recipient_name')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-12">
                    <label class="form-label" for="recipient_address">Alamat Tujuan</label>
                    <textarea
                        class="form-control @error('recipient_address') is-invalid @enderror"
                        id="recipient_address"
                        name="recipient_address"
                        rows="3"
                        maxlength="2000"
                    >{{ old('recipient_address') }}</textarea>
                    @error('recipient_address')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-12">
                    <label class="form-label" for="subject">Perihal <span class="text-danger" aria-hidden="true">*</span><span class="visually-hidden"> (wajib)</span></label>
                    <input
                        class="form-control @error('subject') is-invalid @enderror"
                        id="subject"
                        name="subject"
                        type="text"
                        value="{{ old('subject') }}"
                        maxlength="255"
                        required
                    >
                    @error('subject')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-12">
                    <label class="form-label" for="document">Dokumen <span class="text-danger" aria-hidden="true">*</span><span class="visually-hidden"> (wajib)</span></label>
                    <input
                        class="form-control @error('document') is-invalid @enderror"
                        id="document"
                        name="document"
                        type="file"
                        accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png"
                        data-outgoing-letter-document
                        required
                    >
                    <div class="form-text">PDF, JPG, JPEG, atau PNG. Ukuran maksimum 5 MB.</div>
                    @error('document')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    <div class="invalid-feedback d-none mt-2" role="alert" data-outgoing-document-error></div>
                </div>
            </div>

                        <div class="d-grid d-sm-flex flex-wrap gap-2 mt-4">
                            <a
                                class="btn btn-outline-secondary d-inline-flex align-items-center justify-content-center gap-2"
                                href="{{ route('outgoing-letters.index') }}"
                            >
                                <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
                                <span>Kembali</span>
                            </a>
                            <button class="btn btn-primary d-inline-flex align-items-center justify-content-center gap-2" type="submit">
                                <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i>
                                <span>Simpan</span>
                            </button>
                        </div>
                    </div>
                </section>
            </div>

            <div class="col-12 col-lg-5">
                <aside class="rs-document-preview-sticky" aria-label="Panel preview dokumen">

            <section
                class="card rs-card rs-document-preview-card shadow-sm"
                aria-labelledby="outgoingDocumentPreviewTitle"
                data-outgoing-document-preview-area
            >
                <div class="card-header bg-body py-3">
                    <h2 class="h5 mb-0" id="outgoingDocumentPreviewTitle">Preview Dokumen</h2>
                </div>
                <div class="card-body p-3">
                    <dl class="row g-2 small rs-document-meta mb-3">
                        <div class="col-12">
                            <dt class="text-body-secondary">Nama File</dt>
                            <dd class="text-break mb-0" data-outgoing-document-name>-</dd>
                        </div>
                        <div class="col-12 col-sm-6">
                            <dt class="text-body-secondary">Tipe</dt>
                            <dd class="mb-0" data-outgoing-document-type>-</dd>
                        </div>
                        <div class="col-12 col-sm-6">
                            <dt class="text-body-secondary">Ukuran</dt>
                            <dd class="mb-0" data-outgoing-document-size>-</dd>
                        </div>
                    </dl>
                    <div class="rs-document-preview" data-outgoing-document-preview-content>
                        <div class="rs-document-preview-empty d-flex flex-column align-items-center justify-content-center gap-2 p-4 text-body-secondary">
                            <i class="fa-regular fa-file-lines" aria-hidden="true"></i>
                            <p class="mb-0">Belum ada dokumen dipilih. Pilih file untuk melihat preview.</p>
                        </div>
                    </div>
                </div>
            </section>
                </aside>
            </div>
        </div>
    </form>
@endsection
