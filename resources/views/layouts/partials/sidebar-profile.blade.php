@php($isDesktopProfile = $mode === 'desktop')

<div
    class="rs-sidebar-profile {{ $isDesktopProfile ? 'p-3' : 'mt-auto pt-4' }}"
    data-testid="sidebar-profile-{{ $mode }}"
>
    <div
        class="rs-sidebar-profile-identity d-flex align-items-center gap-3 mb-3"
        @if ($isDesktopProfile)
            data-sidebar-tooltip="{{ $currentUser?->name }} — {{ $currentUserRoleLabel }}"
            aria-label="{{ $currentUser?->name }} — {{ $currentUserRoleLabel }}"
            tabindex="0"
        @endif
    >
        <span class="rs-sidebar-avatar d-inline-flex align-items-center justify-content-center" aria-hidden="true">{{ $currentUserInitials }}</span>
        <div class="rs-sidebar-profile-details">
            <strong class="rs-sidebar-profile-name d-block text-truncate" title="{{ $currentUser?->name }}">{{ $currentUser?->name }}</strong>
            <span class="rs-sidebar-profile-role d-block">{{ $currentUserRoleLabel }}</span>
        </div>
    </div>
    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button
            class="btn rs-sidebar-logout d-flex w-100 align-items-center justify-content-center gap-2"
            type="submit"
            @if ($isDesktopProfile) data-sidebar-tooltip="Keluar" aria-label="Keluar" @endif
        >
            <i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i>
            <span class="rs-sidebar-label">Keluar</span>
        </button>
    </form>
</div>
