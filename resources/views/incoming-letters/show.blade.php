@extends('layouts.dashboard')

@section('title', 'Detail Surat Masuk')

@section('content')
    @php
        $currentUser = auth()->user();
        $isAdminSurat = $currentUser?->role?->slug === 'admin_surat';
        $isPimpinan = $currentUser?->role?->slug === 'pimpinan';
        $isSdmDivisionHead = $currentUser?->role?->slug === 'ketua_divisi'
            && $currentUser?->division?->code === 'SDM';
        $canManage = $isAdminSurat && $incomingLetter->status === 'baru_diterima';
        $canReview = ($isPimpinan || $isSdmDivisionHead)
            && $incomingLetter->status === 'menunggu_pemeriksaan'
            && $incomingLetter->review === null;
        $statusLabels = \App\Support\IncomingLetterStatusPresenter::labels();
        $priorityLabels = [
            'biasa' => 'Biasa',
            'segera' => 'Segera',
        ];
        $receivedViaLabels = [
            'email' => 'Email',
            'whatsapp' => 'WhatsApp',
            'fisik' => 'Fisik',
            'lainnya' => 'Lainnya',
        ];
        $wasArchivedDirectly = $incomingLetter->status === \App\Models\IncomingLetter::STATUS_SELESAI
            && $incomingLetter->review !== null
            && $incomingLetter->review->destination_division_id === null;
        $destinationDivisionLabel = $incomingLetter->destinationDivision?->name
            ?? ($wasArchivedDirectly ? 'Tidak diteruskan ke divisi' : 'Belum ditentukan');
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
        <h1 class="rs-page-title h3 mb-1">Detail Surat Masuk</h1>
        <p class="rs-page-description text-body-secondary mb-2">Nomor agenda {{ $incomingLetter->agenda_number }}</p>
        <div class="d-flex flex-wrap gap-2">
            <x-incoming-letter-status-badge :status="$incomingLetter->status" />
            <span class="badge {{ $incomingLetter->priority === 'segera' ? 'text-bg-danger' : 'text-bg-primary' }}">
                {{ $priorityLabels[$incomingLetter->priority] ?? $incomingLetter->priority }}
            </span>
        </div>
    </header>

    <section class="card rs-card shadow-sm mb-4" aria-label="Preview dokumen surat masuk">
        <div class="card-header bg-body py-3">
            <h2 class="h5 mb-0">Preview Dokumen</h2>
        </div>
        <div class="card-body p-3 p-md-4">
            <div class="rs-document-preview">
                @if ($incomingLetter->document_mime_type === 'application/pdf')
                    <object
                        class="rs-document-frame"
                        data="{{ route('incoming-letters.preview', $incomingLetter) }}"
                        type="application/pdf"
                        data-testid="incoming-letter-preview"
                    >
                        <p class="mb-0">
                            Dokumen tidak dapat ditampilkan pada browser ini. Silakan unduh dokumen untuk melihatnya.
                        </p>
                    </object>
                @elseif (str_starts_with($incomingLetter->document_mime_type, 'image/'))
                    <img
                        class="rs-document-image"
                        src="{{ route('incoming-letters.preview', $incomingLetter) }}"
                        alt="Preview {{ $incomingLetter->original_document_name }}"
                        data-testid="incoming-letter-preview"
                    >
                @else
                    <p class="mb-0">Dokumen tidak dapat ditampilkan pada browser ini. Silakan unduh dokumen untuk melihatnya.</p>
                @endif
            </div>
        </div>
    </section>

    <section class="card rs-card shadow-sm mb-4" aria-label="Informasi surat masuk">
        <div class="card-header bg-body py-3">
            <h2 class="h5 mb-0">Informasi Surat</h2>
        </div>
        <div class="card-body p-3 p-md-4">
            <dl class="row g-3 mb-0 rs-detail-list">
                @foreach ([
                    ['Nomor Agenda', $incomingLetter->agenda_number ?: '-'],
                    ['Nomor Surat', $incomingLetter->letter_number ?: '-'],
                    ['Pengirim', $incomingLetter->sender_name ?: '-'],
                    ['Tujuan pada Surat', $incomingLetter->addressed_to ?: 'Tidak dicantumkan'],
                    ['Tanggal Surat', $incomingLetter->letter_date?->format('d-m-Y') ?? '-'],
                    ['Tanggal Diterima', $incomingLetter->received_date?->format('d-m-Y') ?? '-'],
                    ['Media Penerimaan', $receivedViaLabels[$incomingLetter->received_via] ?? ($incomingLetter->received_via ?: '-')],
                    ['Perihal', $incomingLetter->subject ?: '-'],
                    ['Prioritas', $priorityLabels[$incomingLetter->priority] ?? $incomingLetter->priority],
                    ['Divisi Tujuan', $destinationDivisionLabel],
                    ['Status', $statusLabels[$incomingLetter->status] ?? $incomingLetter->status],
                    ['Dibuat oleh', $incomingLetter->creator?->name ?? '-'],
                    ['Tanggal Dicatat', \App\Support\DateTimeFormatter::human($incomingLetter->created_at)],
                    ['Nama File', $incomingLetter->original_document_name ?: '-'],
                    ['Ukuran File', $formatFileSize($incomingLetter->document_size)],
                ] as [$label, $value])
                    <div class="col-12 col-md-6 rs-detail-item border-bottom pb-3">
                        <dt class="rs-detail-label small text-body-secondary">{{ $label }}</dt>
                        <dd>
                            @if ($label === 'Status')
                                <x-incoming-letter-status-badge :status="$incomingLetter->status" />
                            @else
                                {{ $value }}
                            @endif
                        </dd>
                    </div>
                @endforeach
            </dl>
        </div>
    </section>

    @if ($incomingLetter->review)
        <section class="card rs-card shadow-sm mb-4" aria-label="Hasil pemeriksaan surat masuk">
            <div class="card-header bg-body py-3">
                <h2 class="h5 mb-0">Hasil Pemeriksaan</h2>
            </div>
            <div class="card-body p-3 p-md-4">
                <dl class="row g-3 mb-0 rs-detail-list">
                    @foreach ([
                        ['Diperiksa oleh', $incomingLetter->review->reviewer?->name ?? '-'],
                        ['Tanggal Pemeriksaan', \App\Support\DateTimeFormatter::human($incomingLetter->review->reviewed_at)],
                        ['Divisi Tujuan', $incomingLetter->review->destinationDivision?->name ?? 'Tidak diteruskan ke divisi'],
                    ] as [$label, $value])
                        <div class="col-12 col-md-4 rs-detail-item border-bottom pb-3">
                            <dt class="rs-detail-label small text-body-secondary">{{ $label }}</dt>
                            <dd>{{ $value }}</dd>
                        </div>
                    @endforeach
                    <div class="col-12 rs-detail-item">
                        <dt class="rs-detail-label small text-body-secondary">Catatan Pemeriksa</dt>
                        <dd class="text-break">
                            @if (filled($incomingLetter->review->review_note))
                                {!! nl2br(e($incomingLetter->review->review_note)) !!}
                            @else
                                Tidak ada catatan.
                            @endif
                        </dd>
                    </div>
                </dl>
            </div>
        </section>
    @endif

    <section class="card rs-card shadow-sm mb-4" aria-label="Riwayat status surat masuk">
        <div class="card-header bg-body py-3">
            <h2 class="h5 mb-0">Riwayat Status</h2>
        </div>
        <div class="card-body p-3 p-md-4">
            @forelse ($incomingLetter->statusHistories as $history)
                <article class="rs-status-history-item border-start border-3 border-primary ps-3 pb-4 {{ $loop->last ? 'pb-0' : 'mb-3' }}">
                    <div class="d-flex flex-column flex-md-row align-items-md-start justify-content-between gap-1 mb-2">
                        <h3 class="h6 text-body-emphasis text-break mb-0">{{ $history->activity ?: '-' }}</h3>
                        <time class="small text-body-secondary flex-shrink-0">
                            {{ \App\Support\DateTimeFormatter::human($history->created_at) }}
                        </time>
                    </div>
                    <p class="small mb-2">
                        <span class="text-body-secondary">Status:</span>
                        @if ($history->previous_status)
                            <x-incoming-letter-status-badge :status="$history->previous_status" />
                        @else
                            <span>-</span>
                        @endif
                        <i class="fa-solid fa-arrow-right mx-1" aria-hidden="true"></i>
                        @if ($history->new_status)
                            <x-incoming-letter-status-badge :status="$history->new_status" />
                        @else
                            <span>-</span>
                        @endif
                    </p>
                    @if (filled($history->notes))
                        <p class="small text-break mb-2">{{ $history->notes }}</p>
                    @endif
                    <p class="small text-body-secondary mb-0">
                        Diubah oleh {{ $history->changedBy?->name ?? '-' }}
                    </p>
                </article>
            @empty
                <x-empty-state
                    icon="fa-solid fa-clock-rotate-left"
                    title="Belum ada Riwayat Status"
                    description="Perubahan status surat akan tampil di sini."
                    compact
                />
            @endforelse
        </div>
    </section>

    <div class="d-grid d-sm-flex flex-wrap gap-2">
        <a class="btn btn-outline-secondary d-inline-flex align-items-center justify-content-center gap-2" href="{{ route('incoming-letters.index') }}">
            <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
            <span>Kembali</span>
        </a>
        @if ($canReview)
            <a
                class="btn btn-primary d-inline-flex align-items-center justify-content-center gap-2"
                href="{{ route('incoming-letters.review.create', $incomingLetter) }}"
                data-testid="incoming-letter-review-link"
            >
                <i class="fa-solid fa-share-from-square" aria-hidden="true"></i>
                <span>Periksa dan Teruskan</span>
            </a>
        @endif
        @if ($canManage)
            <a
                class="btn btn-outline-primary d-inline-flex align-items-center justify-content-center gap-2"
                href="{{ route('incoming-letters.edit', $incomingLetter) }}"
                data-testid="incoming-letter-edit-link"
            >
                <i class="fa-solid fa-pen-to-square" aria-hidden="true"></i>
                <span>Edit</span>
            </a>
            <form
                method="POST"
                action="{{ route('incoming-letters.submit-for-review', $incomingLetter) }}"
                data-confirmation
                data-confirmation-title="Kirim untuk Pemeriksaan"
                data-confirmation-message="Surat akan dikirim kepada pihak pemeriksa dan tidak dapat diedit kembali oleh Admin."
                data-confirmation-action-label="Kirim untuk Pemeriksaan"
                data-confirmation-variant="primary"
                data-confirmation-icon="fa-paper-plane"
                data-testid="incoming-letter-submit-form"
            >
                @csrf
                @method('PATCH')
                <button class="btn btn-primary d-inline-flex align-items-center justify-content-center gap-2 w-100" type="submit">
                    <i class="fa-solid fa-paper-plane" aria-hidden="true"></i>
                    <span>Kirim Pemeriksaan</span>
                </button>
            </form>
        @endif
        <a
            class="btn btn-link rs-utility-action d-inline-flex align-items-center justify-content-center gap-2"
            href="{{ route('incoming-letters.preview', $incomingLetter) }}"
            target="_blank"
            rel="noopener"
            data-testid="incoming-letter-preview-link"
        >
            <i class="fa-solid fa-eye" aria-hidden="true"></i>
            <span>Preview</span>
        </a>
        <a
            class="btn btn-link rs-utility-action d-inline-flex align-items-center justify-content-center gap-2"
            href="{{ route('incoming-letters.download', $incomingLetter) }}"
            data-testid="incoming-letter-download-link"
        >
            <i class="fa-solid fa-download" aria-hidden="true"></i>
            <span>Download</span>
        </a>
    </div>
@endsection
