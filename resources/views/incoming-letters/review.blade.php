@extends('layouts.dashboard')

@section('title', 'Periksa dan Teruskan Surat')

@section('content')
    @php
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
        $selectedAction = old('action', 'forward');
        $isDirectArchive = $selectedAction === 'archive_directly';
    @endphp

    <header class="rs-page-header mb-4">
        <h1 class="rs-page-title h3 mb-1">Periksa Surat Masuk</h1>
        <p class="rs-page-description text-body-secondary mb-2">Nomor agenda {{ $incomingLetter->agenda_number }}</p>
        <x-incoming-letter-status-badge :status="$incomingLetter->status" />
    </header>

    <div class="row g-4 align-items-start">
        <div class="col-12 col-xl-7">
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
                                data-testid="incoming-letter-review-preview"
                            >
                                <p class="mb-0">Dokumen tidak dapat ditampilkan pada browser ini. Silakan buka preview dokumen.</p>
                            </object>
                        @elseif (str_starts_with($incomingLetter->document_mime_type, 'image/'))
                            <img
                                class="rs-document-image"
                                src="{{ route('incoming-letters.preview', $incomingLetter) }}"
                                alt="Preview {{ $incomingLetter->original_document_name }}"
                                data-testid="incoming-letter-review-preview"
                            >
                        @else
                            <p class="mb-0">Dokumen tidak dapat ditampilkan pada browser ini. Silakan buka preview dokumen.</p>
                        @endif
                    </div>
                </div>
            </section>

            <section class="card rs-card shadow-sm" aria-label="Informasi surat masuk">
                <div class="card-header bg-body py-3">
                    <h2 class="h5 mb-0">Informasi Surat</h2>
                </div>
                <div class="card-body p-3 p-md-4">
                    <dl class="row g-3 mb-0 rs-detail-list">
                        @foreach ([
                            ['Nomor Agenda', $incomingLetter->agenda_number ?: '-'],
                            ['Nomor Surat', $incomingLetter->letter_number ?: '-'],
                            ['Pengirim', $incomingLetter->sender_name ?: '-'],
                            ['Tujuan Surat', $incomingLetter->addressed_to ?: 'Tidak dicantumkan'],
                            ['Tanggal Surat', $incomingLetter->letter_date?->format('d-m-Y') ?? '-'],
                            ['Tanggal Diterima', $incomingLetter->received_date?->format('d-m-Y') ?? '-'],
                            ['Media Penerimaan', $receivedViaLabels[$incomingLetter->received_via] ?? ($incomingLetter->received_via ?: '-')],
                            ['Perihal', $incomingLetter->subject ?: '-'],
                            ['Prioritas', $priorityLabels[$incomingLetter->priority] ?? $incomingLetter->priority],
                        ] as [$label, $value])
                            <div class="col-12 col-md-6 rs-detail-item border-bottom pb-3">
                                <dt class="rs-detail-label small text-body-secondary">{{ $label }}</dt>
                                <dd>{{ $value }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </div>
            </section>
        </div>

        <div class="col-12 col-xl-5">
            <section class="card rs-card shadow-sm" aria-label="Form pemeriksaan surat masuk">
                <div class="card-header bg-body py-3">
                    <h2 class="h5 mb-0">Tindakan Pemeriksaan</h2>
                </div>
                <div class="card-body p-3 p-md-4">
                    <form
                        method="POST"
                        action="{{ route('incoming-letters.review.store', $incomingLetter) }}"
                        data-confirmation
                        data-confirmation-title="{{ $isDirectArchive ? 'Arsipkan Surat Langsung' : 'Teruskan Surat' }}"
                        data-confirmation-message="{{ $isDirectArchive ? 'Surat ini akan diselesaikan tanpa diteruskan ke divisi.' : 'Surat akan diteruskan ke divisi yang dipilih dan pemeriksaan tidak dapat diulang.' }}"
                        data-confirmation-action-label="{{ $isDirectArchive ? 'Arsipkan Langsung' : 'Teruskan ke Divisi' }}"
                        data-confirmation-variant="{{ $isDirectArchive ? 'warning' : 'primary' }}"
                        data-confirmation-icon="{{ $isDirectArchive ? 'fa-box-archive' : 'fa-share-from-square' }}"
                        data-review-action-form
                        data-testid="incoming-letter-review-form"
                    >
                        @csrf

                        <fieldset class="mb-3">
                            <legend class="form-label">Tindakan <span class="text-danger" aria-hidden="true">*</span></legend>
                            <div class="d-flex flex-column gap-2">
                                <div class="form-check">
                                    <input
                                        class="form-check-input @error('action') is-invalid @enderror"
                                        id="action_forward"
                                        name="action"
                                        type="radio"
                                        value="forward"
                                        @checked($selectedAction === 'forward')
                                        required
                                    >
                                    <label class="form-check-label" for="action_forward">Teruskan ke Divisi</label>
                                </div>
                                <div class="form-check">
                                    <input
                                        class="form-check-input @error('action') is-invalid @enderror"
                                        id="action_archive_directly"
                                        name="action"
                                        type="radio"
                                        value="archive_directly"
                                        @checked($isDirectArchive)
                                        required
                                    >
                                    <label class="form-check-label" for="action_archive_directly">Arsipkan Langsung</label>
                                </div>
                            </div>
                            @error('action')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </fieldset>

                        <div @class(['mb-3', 'd-none' => $isDirectArchive]) data-review-destination-field>
                            <label class="form-label" for="destination_division_id">Divisi Tujuan <span class="text-danger" aria-hidden="true">*</span><span class="visually-hidden"> (wajib)</span></label>
                            <select
                                class="form-select @error('destination_division_id') is-invalid @enderror"
                                id="destination_division_id"
                                name="destination_division_id"
                                @required(! $isDirectArchive)
                                @disabled($isDirectArchive)
                            >
                                <option value="">Pilih Divisi Tujuan</option>
                                @foreach ($divisions as $division)
                                    <option value="{{ $division->id }}" @selected(old('destination_division_id') == $division->id)>
                                        {{ $division->name }}{{ $division->code ? ' ('.$division->code.')' : '' }}
                                    </option>
                                @endforeach
                            </select>
                            @error('destination_division_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-4">
                            <label class="form-label" for="review_note">Catatan Pemeriksa <span class="text-body-secondary">(opsional)</span></label>
                            <textarea
                                class="form-control @error('review_note') is-invalid @enderror"
                                id="review_note"
                                name="review_note"
                                rows="6"
                                maxlength="2000"
                            >{{ old('review_note') }}</textarea>
                            <div class="form-text">Maksimal 2000 karakter.</div>
                            @error('review_note')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="d-grid d-sm-flex flex-wrap gap-2">
                            <a
                                class="btn btn-outline-secondary d-inline-flex align-items-center justify-content-center gap-2"
                                href="{{ route('incoming-letters.show', $incomingLetter) }}"
                            >
                                <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
                                <span>Kembali</span>
                            </a>
                            <button @class(['btn', 'btn-warning' => $isDirectArchive, 'btn-primary' => ! $isDirectArchive, 'd-inline-flex', 'align-items-center', 'justify-content-center', 'gap-2']) type="submit" data-review-submit>
                                <i class="fa-solid {{ $isDirectArchive ? 'fa-box-archive' : 'fa-share-from-square' }}" aria-hidden="true" data-review-submit-icon></i>
                                <span data-review-submit-label>{{ $isDirectArchive ? 'Arsipkan Langsung' : 'Teruskan ke Divisi' }}</span>
                            </button>
                        </div>
                    </form>
                </div>
            </section>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.querySelectorAll('[data-review-action-form]').forEach((form) => {
            const actionInputs = form.querySelectorAll('input[name="action"]');
            const destinationField = form.querySelector('[data-review-destination-field]');
            const destinationSelect = form.querySelector('#destination_division_id');
            const submitButton = form.querySelector('[data-review-submit]');
            const submitIcon = form.querySelector('[data-review-submit-icon]');
            const submitLabel = form.querySelector('[data-review-submit-label]');

            const syncAction = () => {
                const selectedAction = form.querySelector('input[name="action"]:checked')?.value ?? 'forward';
                const isDirectArchive = selectedAction === 'archive_directly';

                destinationField?.classList.toggle('d-none', isDirectArchive);

                if (destinationSelect) {
                    destinationSelect.disabled = isDirectArchive;
                    destinationSelect.required = ! isDirectArchive;
                }

                form.dataset.confirmationTitle = isDirectArchive ? 'Arsipkan Surat Langsung' : 'Teruskan Surat';
                form.dataset.confirmationMessage = isDirectArchive
                    ? 'Surat ini akan diselesaikan tanpa diteruskan ke divisi.'
                    : 'Surat akan diteruskan ke divisi yang dipilih dan pemeriksaan tidak dapat diulang.';
                form.dataset.confirmationActionLabel = isDirectArchive ? 'Arsipkan Langsung' : 'Teruskan ke Divisi';
                form.dataset.confirmationVariant = isDirectArchive ? 'warning' : 'primary';
                form.dataset.confirmationIcon = isDirectArchive ? 'fa-box-archive' : 'fa-share-from-square';

                submitButton?.classList.toggle('btn-warning', isDirectArchive);
                submitButton?.classList.toggle('btn-primary', ! isDirectArchive);

                if (submitIcon) {
                    submitIcon.className = `fa-solid ${isDirectArchive ? 'fa-box-archive' : 'fa-share-from-square'}`;
                }

                if (submitLabel) {
                    submitLabel.textContent = isDirectArchive ? 'Arsipkan Langsung' : 'Teruskan ke Divisi';
                }
            };

            actionInputs.forEach((input) => input.addEventListener('change', syncAction));
            syncAction();
        });
    </script>
@endpush
