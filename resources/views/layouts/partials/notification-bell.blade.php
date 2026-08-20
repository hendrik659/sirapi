@php
    $notificationUnreadCount = $currentUser->unreadNotifications()->count();
    $latestNotifications = $currentUser->notifications()->latest()->limit(5)->get();
    $notificationBadge = $notificationUnreadCount > 99 ? '99+' : (string) $notificationUnreadCount;
@endphp

<div class="dropdown rs-notification-bell" data-testid="notification-bell">
    <button
        class="btn rs-notification-toggle d-inline-flex align-items-center justify-content-center position-relative"
        type="button"
        id="rsNotificationMenu"
        data-bs-toggle="dropdown"
        aria-expanded="false"
        aria-label="Notifikasi, {{ $notificationUnreadCount }} belum dibaca"
    >
        <i class="fa-regular fa-bell" aria-hidden="true"></i>
        @if ($notificationUnreadCount > 0)
            <span class="badge rounded-pill rs-notification-badge" data-testid="notification-unread-badge">
                {{ $notificationBadge }}
            </span>
        @endif
    </button>

    <div class="dropdown-menu dropdown-menu-end rs-notification-dropdown shadow" aria-labelledby="rsNotificationMenu">
        <div class="rs-notification-dropdown-header d-flex align-items-center justify-content-between gap-3">
            <strong>Notifikasi</strong>
            @if ($notificationUnreadCount > 0)
                <span class="small text-body-secondary">{{ $notificationBadge }} belum dibaca</span>
            @endif
        </div>

        <div class="rs-notification-dropdown-list">
            @forelse ($latestNotifications as $notification)
                @php($notificationData = $notification->data)
                <form method="POST" action="{{ route('notifications.open', $notification->id) }}">
                    @csrf
                    @method('PATCH')
                    <button
                        class="dropdown-item rs-notification-dropdown-item d-flex align-items-start gap-3 {{ $notification->read_at === null ? 'rs-notification-unread' : '' }}"
                        type="submit"
                        data-testid="notification-dropdown-item"
                    >
                        <span class="rs-notification-icon d-inline-flex align-items-center justify-content-center flex-shrink-0" aria-hidden="true">
                            <i class="{{ $notificationData['icon'] ?? 'fa-regular fa-bell' }}"></i>
                        </span>
                        <span class="rs-notification-copy flex-grow-1">
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
                    </button>
                </form>
            @empty
                <div class="rs-notification-empty text-center text-body-secondary">
                    Belum ada notifikasi.
                </div>
            @endforelse
        </div>

        <div class="rs-notification-dropdown-footer">
            <a class="d-flex align-items-center justify-content-center gap-2" href="{{ route('notifications.index') }}">
                <span>Lihat Semua Notifikasi</span>
                <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
            </a>
        </div>
    </div>
</div>
