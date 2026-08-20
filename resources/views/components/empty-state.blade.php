@props([
    'icon' => 'fa-regular fa-folder-open',
    'title' => 'Belum ada data',
    'description' => null,
    'actionUrl' => null,
    'actionLabel' => null,
    'actionIcon' => 'fa-rotate-left',
    'actionVariant' => 'outline-secondary',
    'compact' => false,
])

<div @class([
    'rs-empty-state-content d-flex flex-column align-items-center justify-content-center text-center',
    'rs-empty-state-compact' => $compact,
])>
    <i class="{{ $icon }} rs-empty-state-icon" aria-hidden="true"></i>
    <strong class="rs-empty-state-title d-block">{{ $title }}</strong>

    @if (filled($description))
        <p class="rs-empty-state-description mb-0">{{ $description }}</p>
    @endif

    @if ($actionUrl && $actionLabel)
        <a class="btn btn-sm btn-{{ $actionVariant }} d-inline-flex align-items-center justify-content-center gap-2 mt-3" href="{{ $actionUrl }}">
            <i class="fa-solid {{ $actionIcon }}" aria-hidden="true"></i>
            <span>{{ $actionLabel }}</span>
        </a>
    @endif
</div>
