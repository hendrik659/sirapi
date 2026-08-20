@extends('layouts.dashboard')

@section('title', 'Detail Surat Keluar')

@section('content')
    @php
        $formatFileSize = static function (?int $bytes): string {
            if ($bytes === null) {
                return '-';
            }

            if ($bytes >= 1024 * 1024) {
                return number_format($bytes / (1024 * 1024), 2, ',', '.').' MB';
            }

            return number_format($bytes / 1024, 1, ',', '.').' KB';
        };
    @endphp

    <header class="rs-page-header mb-4">
        <h1 class="rs-page-title h3 mb-1">Detail Surat Keluar</h1>
        <p class="rs-page-description text-body-secondary mb-2">
            Kode Sistem {{ $outgoingLetter->reference_code ?: '-' }} · {{ $outgoingLetter->letter_number ?: '-' }}
        </p>
    </header>

    <section class="card rs-card shadow-sm mb-4" aria-label="Preview dokumen surat keluar">
        <div class="card-header bg-body py-3">
            <h2 class="h5 mb-0">Preview Dokumen</h2>
        </div>
        <div class="card-body p-3 p-md-4">
            <div class="rs-document-preview">
                @if ($outgoingLetter->document_mime_type === 'application/pdf')
                    <object
                        class="rs-document-frame"
                        data="{{ route('outgoing-letters.preview', $outgoingLetter) }}"
                        type="application/pdf"
                        data-testid="outgoing-letter-preview"
                    >
                        <p class="mb-0">Dokumen tidak dapat ditampilkan pada browser ini. Silakan unduh dokumen untuk melihatnya.</p>
                    </object>
                @elseif (str_starts_with($outgoingLetter->document_mime_type, 'image/'))
                    <img
                        class="rs-document-image"
                        src="{{ route('outgoing-letters.preview', $outgoingLetter) }}"
                        alt="Preview {{ $outgoingLetter->original_document_name }}"
                        data-testid="outgoing-letter-preview"
                    >
                @else
                    <p class="mb-0">Dokumen tidak dapat ditampilkan pada browser ini. Silakan unduh dokumen untuk melihatnya.</p>
                @endif
            </div>
        </div>
    </section>

    <section class="card rs-card shadow-sm mb-4" aria-label="Informasi surat keluar">
        <div class="card-header bg-body py-3">
            <h2 class="h5 mb-0">Detail Surat</h2>
        </div>
        <div class="card-body p-3 p-md-4">
            <dl class="row g-3 mb-0 rs-detail-list">
                @foreach ([
                    ['Nomor Surat', $outgoingLetter->letter_number ?: '-'],
                    ['Tanggal Surat', $outgoingLetter->letter_date?->format('d-m-Y') ?? '-'],
                    ['Tujuan', $outgoingLetter->recipient_name ?: '-'],
                    ['Alamat Tujuan', $outgoingLetter->recipient_address ?: '-'],
                    ['Perihal', $outgoingLetter->subject ?: '-'],
                    ['Divisi', $outgoingLetter->division?->name ?? '-'],
                    ['Dicatat Oleh', $outgoingLetter->creator?->name ?? '-'],
                    ['Tanggal Dicatat', \App\Support\DateTimeFormatter::human($outgoingLetter->created_at)],
                    ['Nama File', $outgoingLetter->original_document_name ?: '-'],
                    ['Ukuran File', $formatFileSize($outgoingLetter->document_size)],
                ] as [$label, $value])
                    <div class="col-12 col-md-6 rs-detail-item border-bottom pb-3">
                        <dt class="rs-detail-label small text-body-secondary">{{ $label }}</dt>
                        <dd>{{ $value }}</dd>
                    </div>
                @endforeach

            </dl>
        </div>
    </section>

    <section class="card rs-card shadow-sm mb-4" aria-label="Aksi surat keluar">
        <div class="card-header bg-body py-3">
            <h2 class="h5 mb-0">Aksi</h2>
        </div>
        <div class="card-body p-3 p-md-4">
            <div class="d-grid d-sm-flex flex-wrap gap-2">
                <a class="btn btn-outline-secondary d-inline-flex align-items-center justify-content-center gap-2" href="{{ route('outgoing-letters.index') }}">
                    <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
                    <span>Kembali</span>
                </a>
                <a
                    class="btn btn-link rs-utility-action d-inline-flex align-items-center justify-content-center gap-2"
                    href="{{ route('outgoing-letters.preview', $outgoingLetter) }}"
                    target="_blank"
                    rel="noopener"
                    data-testid="outgoing-letter-preview-link"
                >
                    <i class="fa-solid fa-eye" aria-hidden="true"></i>
                    <span>Preview</span>
                </a>
                <a
                    class="btn btn-link rs-utility-action d-inline-flex align-items-center justify-content-center gap-2"
                    href="{{ route('outgoing-letters.download', $outgoingLetter) }}"
                    data-testid="outgoing-letter-download-link"
                >
                    <i class="fa-solid fa-download" aria-hidden="true"></i>
                    <span>Download</span>
                </a>
            </div>
        </div>
    </section>

    <section class="card rs-card shadow-sm" aria-label="Riwayat aktivitas surat keluar">
        <div class="card-header bg-body py-3">
            <h2 class="h5 mb-0">Riwayat Aktivitas</h2>
        </div>
        <div class="card-body p-3 p-md-4">
            @forelse ($outgoingLetter->histories as $history)
                <article class="rs-status-history-item border-start border-3 border-primary ps-3 pb-4 {{ $loop->last ? 'pb-0' : 'mb-3' }}">
                    <div class="d-flex flex-column flex-md-row align-items-md-start justify-content-between gap-1 mb-2">
                        <h3 class="h6 text-body-emphasis text-break mb-0">{{ $history->activity ?: '-' }}</h3>
                        <time class="small text-body-secondary flex-shrink-0">
                            {{ \App\Support\DateTimeFormatter::human($history->created_at) }}
                        </time>
                    </div>
                    @if (filled($history->notes))
                        <p class="small text-break mb-2">{{ $history->notes }}</p>
                    @endif
                    <p class="small text-body-secondary mb-0">Diubah oleh {{ $history->changedBy?->name ?? '-' }}</p>
                </article>
            @empty
                <x-empty-state
                    icon="fa-solid fa-clock-rotate-left"
                    title="Belum ada Riwayat Aktivitas"
                    description="Aktivitas surat keluar akan tampil di sini."
                    compact
                />
            @endforelse
        </div>
    </section>
@endsection
