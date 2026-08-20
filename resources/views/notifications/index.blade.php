@extends('layouts.dashboard')

@section('title', 'Notifikasi')

@section('content')
    <header class="rs-page-header d-flex flex-column flex-md-row align-items-md-start justify-content-between gap-3 mb-4">
        <div>
            <h1 class="rs-page-title h3 mb-1">Notifikasi</h1>
            <p class="rs-page-description text-body-secondary mb-0">Pemberitahuan terbaru dari SIRAPI.</p>
        </div>

        @if ($unreadCount > 0)
            <form method="POST" action="{{ route('notifications.read-all') }}">
                @csrf
                @method('PATCH')
                <button class="btn btn-outline-primary d-inline-flex align-items-center justify-content-center gap-2" type="submit">
                    <i class="fa-solid fa-check-double" aria-hidden="true"></i>
                    <span>Tandai Semua Dibaca</span>
                </button>
            </form>
        @endif
    </header>

    <nav class="rs-notification-filters d-flex flex-wrap gap-2 mb-4" aria-label="Filter notifikasi">
        <a
            @class(['btn', 'btn-primary' => $filter === 'all', 'btn-outline-primary' => $filter !== 'all'])
            href="{{ route('notifications.index', ['filter' => 'all']) }}"
            @if ($filter === 'all') aria-current="page" @endif
        >
            Semua
        </a>
        <a
            @class(['btn', 'btn-primary' => $filter === 'unread', 'btn-outline-primary' => $filter !== 'unread'])
            href="{{ route('notifications.index', ['filter' => 'unread']) }}"
            @if ($filter === 'unread') aria-current="page" @endif
        >
            Belum Dibaca
        </a>
    </nav>

    <section class="card rs-card rs-notification-list shadow-sm" aria-label="Daftar notifikasi">
        <div class="list-group list-group-flush">
            @forelse ($notifications as $notification)
                @php($notificationData = $notification->data)
                <form method="POST" action="{{ route('notifications.open', $notification->id) }}">
                    @csrf
                    @method('PATCH')
                    <button
                        class="list-group-item list-group-item-action rs-notification-list-item d-flex align-items-start gap-3 gap-md-4 {{ $notification->read_at === null ? 'rs-notification-unread' : '' }}"
                        type="submit"
                    >
                        <span class="rs-notification-icon d-inline-flex align-items-center justify-content-center flex-shrink-0" aria-hidden="true">
                            <i class="{{ $notificationData['icon'] ?? 'fa-regular fa-bell' }}"></i>
                        </span>
                        <span class="rs-notification-copy flex-grow-1 text-start">
                            <span class="d-flex align-items-start gap-2">
                                <strong class="rs-notification-title flex-grow-1">{{ $notificationData['title'] ?? 'Notifikasi SIRAPI' }}</strong>
                                @if ($notification->read_at === null)
                                    <span class="rs-notification-dot flex-shrink-0" aria-label="Belum dibaca"></span>
                                @endif
                            </span>
                            <span class="rs-notification-message d-block">{{ $notificationData['message'] ?? '-' }}</span>
                            <time class="rs-notification-time d-block" datetime="{{ $notification->created_at->toIso8601String() }}">
                                {{ $notification->created_at->locale('id')->diffForHumans() }}
                            </time>
                        </span>
                        <i class="fa-solid fa-chevron-right rs-notification-open-icon align-self-center flex-shrink-0" aria-hidden="true"></i>
                    </button>
                </form>
            @empty
                <div class="rs-notification-page-empty p-4 p-md-5">
                    <x-empty-state
                        icon="fa-regular fa-bell-slash"
                        :title="$filter === 'unread' ? 'Tidak ada notifikasi yang belum dibaca.' : 'Belum ada notifikasi.'"
                    />
                </div>
            @endforelse
        </div>

        @if ($notifications->hasPages())
            <div class="card-footer p-3 p-md-4">
                <div class="rs-pagination">
                    {{ $notifications->onEachSide(1)->links('pagination::bootstrap-5') }}
                </div>
            </div>
        @endif
    </section>
@endsection
