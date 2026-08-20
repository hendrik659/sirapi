@extends('layouts.dashboard')

@section('title', 'Detail Sertifikat')

@section('content')
    @php
        $formatDate = static fn ($date) => $date?->locale('id')->translatedFormat('d F Y') ?? '-';
    @endphp

    <header class="rs-page-header d-flex flex-column flex-md-row align-items-md-start justify-content-between gap-3 mb-4">
        <div>
            <h1 class="rs-page-title h3 mb-1">Detail Sertifikat</h1>
            <p class="rs-page-description text-body-secondary mb-0">Arsip sertifikat peserta magang/PKL.</p>
        </div>
        @can('update', $certificate)
            <a class="btn btn-outline-primary d-inline-flex align-items-center justify-content-center gap-2" href="{{ route('dashboard.certificates.edit', $certificate) }}" data-testid="certificate-edit-link">
                <i class="fa-solid fa-pen-to-square" aria-hidden="true"></i>
                <span>Edit</span>
            </a>
        @endcan
    </header>

    <div class="row g-4 align-items-start">
        <div class="col-12 col-xl-5">
            <section class="card rs-card shadow-sm" aria-label="Metadata sertifikat">
                <div class="card-header bg-body py-3">
                    <h2 class="h5 mb-0">Metadata Sertifikat</h2>
                </div>
                <div class="card-body p-3 p-md-4">
                    <dl class="row g-3 mb-0 rs-detail-list">
                        @foreach ([
                            ['Kode Sistem', $certificate->archive_code ?: '-'],
                            ['Nama Peserta', $certificate->participant_name],
                            ['Asal Institusi', $certificate->institution_name],
                            ['Program Studi / Jurusan', $certificate->major_name],
                            ['Periode', $formatDate($certificate->start_date).' – '.$formatDate($certificate->end_date)],
                            ['Dicatat Oleh', $certificate->creator?->name ?? '-'],
                            ['Tanggal Dicatat', \App\Support\DateTimeFormatter::human($certificate->created_at)],
                        ] as [$label, $value])
                            <div class="col-12 rs-detail-item border-bottom pb-3">
                                <dt class="rs-detail-label small text-body-secondary">{{ $label }}</dt>
                                <dd>{{ $value }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </div>
            </section>
        </div>

        <div class="col-12 col-xl-7">
            <section class="card rs-card shadow-sm" aria-label="Preview dokumen sertifikat">
                <div class="card-header bg-body py-3">
                    <h2 class="h5 mb-0">Preview Dokumen</h2>
                </div>
                <div class="card-body p-3 p-md-4">
                    <div class="rs-document-preview">
                        @if ($certificate->document_mime_type === 'application/pdf')
                            <object
                                class="rs-document-frame"
                                data="{{ route('dashboard.certificates.preview', $certificate) }}"
                                type="application/pdf"
                                title="Preview {{ $certificate->original_document_name }}"
                                data-testid="certificate-preview"
                            >
                                <p class="mb-0">Dokumen tidak dapat ditampilkan pada browser ini. Silakan gunakan tombol Preview atau Download.</p>
                            </object>
                        @elseif (str_starts_with($certificate->document_mime_type, 'image/'))
                            <img
                                class="rs-document-image"
                                src="{{ route('dashboard.certificates.preview', $certificate) }}"
                                alt="Preview {{ $certificate->original_document_name }}"
                                data-testid="certificate-preview"
                            >
                        @else
                            <p class="mb-0">Format dokumen tidak didukung untuk preview.</p>
                        @endif
                    </div>
                </div>
            </section>
        </div>
    </div>

    <section class="card rs-card shadow-sm mt-4" aria-label="Aksi sertifikat">
        <div class="card-body p-3 p-md-4">
            <div class="d-grid d-sm-flex flex-wrap gap-2">
                <a class="btn btn-outline-secondary d-inline-flex align-items-center justify-content-center gap-2" href="{{ route('dashboard.certificates.index') }}">
                    <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
                    <span>Kembali</span>
                </a>
                <a class="btn btn-link rs-utility-action d-inline-flex align-items-center justify-content-center gap-2" href="{{ route('dashboard.certificates.preview', $certificate) }}" target="_blank" rel="noopener" data-testid="certificate-preview-link">
                    <i class="fa-solid fa-eye" aria-hidden="true"></i>
                    <span>Preview</span>
                </a>
                <a class="btn btn-link rs-utility-action d-inline-flex align-items-center justify-content-center gap-2" href="{{ route('dashboard.certificates.download', $certificate) }}" data-testid="certificate-download-link">
                    <i class="fa-solid fa-download" aria-hidden="true"></i>
                    <span>Download</span>
                </a>
            </div>
        </div>
    </section>
@endsection
