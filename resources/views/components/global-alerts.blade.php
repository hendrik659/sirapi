@php
    $alertTypes = [
        'success' => [
            'title' => 'Berhasil',
            'icon' => 'fa-circle-check',
            'delay' => 5000,
            'role' => 'status',
            'live' => 'polite',
        ],
        'error' => [
            'title' => 'Gagal',
            'icon' => 'fa-circle-exclamation',
            'delay' => 9000,
            'role' => 'alert',
            'live' => 'assertive',
        ],
        'warning' => [
            'title' => 'Perhatian',
            'icon' => 'fa-triangle-exclamation',
            'delay' => 7000,
            'role' => 'alert',
            'live' => 'assertive',
        ],
        'info' => [
            'title' => 'Informasi',
            'icon' => 'fa-circle-info',
            'delay' => 5000,
            'role' => 'status',
            'live' => 'polite',
        ],
    ];

    $activeAlerts = collect($alertTypes)
        ->filter(fn (array $config, string $type) => session()->has($type));
@endphp

@if ($activeAlerts->isNotEmpty())
    <div
        class="toast-container rs-toast-container"
        aria-label="Notifikasi sistem"
        data-global-toast-container
    >
        @foreach ($activeAlerts as $type => $config)
            <div
                class="toast rs-toast rs-toast-{{ $type }}"
                role="{{ $config['role'] }}"
                aria-live="{{ $config['live'] }}"
                aria-atomic="true"
                data-bs-autohide="true"
                data-bs-delay="{{ $config['delay'] }}"
                data-global-toast
                data-testid="global-toast-{{ $type }}"
            >
                <div class="toast-header rs-toast-header">
                    <span class="rs-toast-icon rs-toast-icon-{{ $type }}" aria-hidden="true">
                        <i class="fa-solid {{ $config['icon'] }}"></i>
                    </span>
                    <strong class="me-auto">{{ $config['title'] }}</strong>
                    <button
                        class="btn-close"
                        type="button"
                        data-bs-dismiss="toast"
                        aria-label="Tutup notifikasi {{ strtolower($config['title']) }}"
                    ></button>
                </div>
                <div class="toast-body rs-toast-body">{{ session($type) }}</div>
            </div>
        @endforeach
    </div>
@endif
