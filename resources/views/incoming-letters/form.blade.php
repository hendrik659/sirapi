@extends('layouts.dashboard')

@php
    $editing = isset($incomingLetter);
    $receivedViaOptions = [
        'email' => 'Email',
        'whatsapp' => 'WhatsApp',
        'fisik' => 'Fisik',
        'lainnya' => 'Lainnya',
    ];
    $priorityOptions = [
        'biasa' => 'Biasa',
        'segera' => 'Segera',
    ];
@endphp

@section('title', $editing ? 'Edit Surat Masuk' : 'Tambah Surat Masuk')

@section('content')
    <header class="rs-page-header mb-4">
        <h1 class="rs-page-title h3 mb-1">{{ $editing ? 'Edit Surat Masuk' : 'Tambah Surat Masuk' }}</h1>
        <p class="rs-page-description text-body-secondary mb-0">
            {{ $editing ? 'Perbarui data dan dokumen surat masuk.' : 'Catat surat masuk baru ke dalam sistem.' }}
        </p>
    </header>

    <form
        class="rs-document-form-layout"
        method="POST"
        action="{{ $editing ? route('incoming-letters.update', $incomingLetter) : route('incoming-letters.store') }}"
        enctype="multipart/form-data"
    >
        @csrf
        @if ($editing)
            @method('PUT')
        @endif

        <div class="row g-4 align-items-start">
            <div class="col-12 col-lg-7">
                <section class="card rs-card rs-document-form-card shadow-sm">
                    <div class="card-body p-3 p-md-4">
            <div class="row g-3">
                <div class="col-12 col-md-6">
                    <label class="form-label" for="agenda_number">Nomor Agenda <span class="text-danger" aria-hidden="true">*</span><span class="visually-hidden"> (wajib)</span></label>
                    <input
                        class="form-control @error('agenda_number') is-invalid @enderror"
                        id="agenda_number"
                        name="agenda_number"
                        type="text"
                        value="{{ old('agenda_number', $incomingLetter->agenda_number ?? '') }}"
                        maxlength="100"
                        required
                    >
                    @error('agenda_number')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-12 col-md-6">
                    <label class="form-label" for="letter_number">Nomor Surat</label>
                    <input
                        class="form-control @error('letter_number') is-invalid @enderror"
                        id="letter_number"
                        name="letter_number"
                        type="text"
                        value="{{ old('letter_number', $incomingLetter->letter_number ?? '') }}"
                        maxlength="100"
                    >
                    @error('letter_number')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-12 col-md-6">
                    <label class="form-label" for="sender_name">Pengirim <span class="text-danger" aria-hidden="true">*</span><span class="visually-hidden"> (wajib)</span></label>
                    <input
                        class="form-control @error('sender_name') is-invalid @enderror"
                        id="sender_name"
                        name="sender_name"
                        type="text"
                        value="{{ old('sender_name', $incomingLetter->sender_name ?? '') }}"
                        maxlength="255"
                        required
                    >
                    @error('sender_name')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-12 col-md-6">
                    <label class="form-label" for="addressed_to">Tujuan pada Surat <span class="text-body-secondary fw-normal">(Opsional)</span></label>
                    <input
                        class="form-control @error('addressed_to') is-invalid @enderror"
                        id="addressed_to"
                        name="addressed_to"
                        type="text"
                        value="{{ old('addressed_to', $incomingLetter->addressed_to ?? '') }}"
                        maxlength="255"
                    >
                    @error('addressed_to')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-12 col-md-6">
                    <label class="form-label" for="letter_date">Tanggal Surat</label>
                    <input
                        class="form-control @error('letter_date') is-invalid @enderror"
                        id="letter_date"
                        name="letter_date"
                        type="date"
                        value="{{ old('letter_date', $editing ? $incomingLetter->letter_date?->format('Y-m-d') : '') }}"
                    >
                    @error('letter_date')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-12 col-md-6">
                    <label class="form-label" for="received_date">Tanggal Diterima <span class="text-danger" aria-hidden="true">*</span><span class="visually-hidden"> (wajib)</span></label>
                    <input
                        class="form-control @error('received_date') is-invalid @enderror"
                        id="received_date"
                        name="received_date"
                        type="date"
                        value="{{ old('received_date', $editing ? $incomingLetter->received_date?->format('Y-m-d') : '') }}"
                        required
                    >
                    @error('received_date')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-12 col-md-6">
                    <label class="form-label" for="received_via">Media Penerimaan <span class="text-danger" aria-hidden="true">*</span><span class="visually-hidden"> (wajib)</span></label>
                    <select class="form-select @error('received_via') is-invalid @enderror" id="received_via" name="received_via" required>
                        <option value="">Pilih Media Penerimaan</option>
                        @foreach ($receivedViaOptions as $value => $label)
                            <option value="{{ $value }}" @selected(old('received_via', $incomingLetter->received_via ?? '') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('received_via')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-12 col-md-6">
                    <label class="form-label" for="priority">Prioritas <span class="text-danger" aria-hidden="true">*</span><span class="visually-hidden"> (wajib)</span></label>
                    <select class="form-select @error('priority') is-invalid @enderror" id="priority" name="priority" required>
                        @foreach ($priorityOptions as $value => $label)
                            <option value="{{ $value }}" @selected(old('priority', $incomingLetter->priority ?? 'biasa') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('priority')
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
                        value="{{ old('subject', $incomingLetter->subject ?? '') }}"
                        maxlength="500"
                        required
                    >
                    @error('subject')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-12">
                    <label class="form-label" for="document">
                        Dokumen
                        @unless ($editing)<span class="text-danger" aria-hidden="true">*</span><span class="visually-hidden"> (wajib)</span>@endunless
                    </label>
                    <input
                        class="form-control @error('document') is-invalid @enderror"
                        id="document"
                        name="document"
                        type="file"
                        accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png"
                        data-incoming-letter-document
                        @required(! $editing)
                    >
                    <div class="form-text">
                        PDF, JPG, JPEG, atau PNG. Ukuran maksimum 5 MB.
                        @if ($editing)
                            Biarkan kosong untuk tetap menggunakan dokumen lama.
                        @endif
                    </div>
                    @error('document')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    <div class="invalid-feedback d-none mt-2" role="alert" data-document-error></div>
                </div>
            </div>

                        <div class="d-grid d-sm-flex flex-wrap gap-2 mt-4">
                            <a
                                class="btn btn-outline-secondary d-inline-flex align-items-center justify-content-center gap-2"
                                href="{{ $editing ? route('incoming-letters.show', $incomingLetter) : route('incoming-letters.index') }}"
                            >
                                <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
                                <span>Kembali</span>
                            </a>
                            <button class="btn btn-primary d-inline-flex align-items-center justify-content-center gap-2" type="submit">
                                <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i>
                                <span>{{ $editing ? 'Simpan Perubahan' : 'Simpan' }}</span>
                            </button>
                        </div>
                    </div>
                </section>
            </div>

            <div class="col-12 col-lg-5">
                <aside class="rs-document-preview-sticky" aria-label="Panel preview dokumen">

            <section
                class="card rs-card rs-document-preview-card shadow-sm"
                aria-labelledby="incomingDocumentPreviewTitle"
                data-document-preview-area
            >
                <div class="card-header bg-body py-3">
                    <h2 class="h5 mb-0" id="incomingDocumentPreviewTitle">Preview Dokumen</h2>
                </div>
                <div class="card-body p-3">
                    <dl class="row g-2 small rs-document-meta mb-3">
                        <div class="col-12">
                            <dt class="text-body-secondary">Nama File</dt>
                            <dd class="text-break mb-0" data-document-name>{{ $editing ? $incomingLetter->original_document_name : '-' }}</dd>
                        </div>
                        <div class="col-12 col-sm-6">
                            <dt class="text-body-secondary">Tipe</dt>
                            <dd class="mb-0" data-document-type>{{ $editing ? $incomingLetter->document_mime_type : '-' }}</dd>
                        </div>
                        <div class="col-12 col-sm-6">
                            <dt class="text-body-secondary">Ukuran</dt>
                            <dd class="mb-0" data-document-size>
                                {{ $editing ? number_format($incomingLetter->document_size / 1024, 1, ',', '.').' KB' : '-' }}
                            </dd>
                        </div>
                    </dl>
                    <div class="rs-document-preview" data-document-preview-content>
                        @if ($editing && $incomingLetter->document_mime_type === 'application/pdf')
                            <object
                                class="rs-document-frame"
                                data="{{ route('incoming-letters.preview', $incomingLetter) }}"
                                type="application/pdf"
                                title="Preview {{ $incomingLetter->original_document_name }}"
                            >
                                <p class="mb-0">Dokumen tidak dapat ditampilkan pada browser ini. Silakan unduh dokumen untuk melihatnya.</p>
                            </object>
                        @elseif ($editing && str_starts_with($incomingLetter->document_mime_type, 'image/'))
                            <img
                                class="rs-document-image"
                                src="{{ route('incoming-letters.preview', $incomingLetter) }}"
                                alt="Preview {{ $incomingLetter->original_document_name }}"
                            >
                        @elseif ($editing)
                            <p class="mb-0">Dokumen tidak dapat ditampilkan pada browser ini. Silakan unduh dokumen untuk melihatnya.</p>
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
