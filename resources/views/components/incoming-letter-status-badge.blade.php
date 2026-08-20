@props(['status'])

@php
    $statusLabel = \App\Support\IncomingLetterStatusPresenter::label($status);
    $statusVariant = \App\Support\IncomingLetterStatusPresenter::variant($status);
@endphp

<span
    {{ $attributes->class(['badge', 'rs-status-badge', 'rs-status-'.$statusVariant]) }}
    data-incoming-letter-status="{{ $status }}"
>
    {{ $statusLabel }}
</span>
