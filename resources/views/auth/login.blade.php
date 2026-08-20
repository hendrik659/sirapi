<!DOCTYPE html>
<html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="color-scheme" content="light">
        <title>Masuk · SIRAPI</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="rs-auth-body" data-rs-auth-page>
        <main
            class="rs-auth-page"
            style="--rs-auth-building: url('{{ asset('images/auth/radar-kediri-building.png') }}')"
        >
            <section class="rs-auth-hero" aria-label="Jawa Pos Radar Kediri">
                <img
                    class="rs-auth-logo"
                    src="{{ asset('images/auth/radar-kediri-logo-white.png') }}"
                    alt="Radar Kediri"
                    width="2172"
                    height="724"
                >
            </section>

            <section class="rs-auth-panel" aria-labelledby="login-title">
                <div class="rs-auth-card">
                    <header class="rs-auth-card-header">
                        <h1 id="login-title" class="rs-auth-title">Login SIRAPI</h1>
                        <p class="rs-auth-subtitle">Sistem Arsip Jawa Pos Radar Kediri</p>
                    </header>

                    @if (session('status'))
                        <div class="alert alert-success d-flex align-items-start gap-2" role="status">
                            <i class="fa-solid fa-circle-check mt-1" aria-hidden="true"></i>
                            <span>{{ session('status') }}</span>
                        </div>
                    @endif

                    @if ($errors->any())
                        <div class="alert alert-danger d-flex align-items-start gap-2" role="alert">
                            <i class="fa-solid fa-circle-exclamation mt-1" aria-hidden="true"></i>
                            <span>{{ $errors->first() }}</span>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('login.store') }}" novalidate>
                        @csrf

                        <div class="rs-auth-field">
                            <label class="form-label" for="email">Email</label>
                            <div class="input-group rs-auth-input-group">
                                <span class="input-group-text" aria-hidden="true">
                                    <i class="fa-regular fa-envelope" aria-hidden="true"></i>
                                </span>
                                <input
                                    id="email"
                                    class="form-control @error('email') is-invalid @enderror"
                                    name="email"
                                    type="email"
                                    inputmode="email"
                                    value="{{ old('email') }}"
                                    placeholder="nama@jawapos.co.id"
                                    autocomplete="email"
                                    required
                                    autofocus
                                    aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}"
                                    @error('email') aria-describedby="email-error" @enderror
                                >
                            </div>
                            @error('email')
                                <div id="email-error" class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="rs-auth-field">
                            <label class="form-label" for="password">Kata Sandi</label>
                            <div class="input-group rs-auth-input-group">
                                <span class="input-group-text" aria-hidden="true">
                                    <i class="fa-solid fa-lock" aria-hidden="true"></i>
                                </span>
                                <input
                                    id="password"
                                    class="form-control @error('password') is-invalid @enderror"
                                    name="password"
                                    type="password"
                                    placeholder="Masukkan kata sandi"
                                    autocomplete="current-password"
                                    required
                                    data-password-input
                                    aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}"
                                    @error('password') aria-describedby="password-error" @enderror
                                >
                                <button
                                    class="btn rs-auth-password-toggle"
                                    type="button"
                                    data-password-toggle
                                    aria-controls="password"
                                    aria-label="Tampilkan kata sandi"
                                    aria-pressed="false"
                                >
                                    <i class="fa-regular fa-eye" aria-hidden="true" data-password-icon></i>
                                </button>
                            </div>
                            @error('password')
                                <div id="password-error" class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="form-check rs-auth-remember">
                            <input
                                id="remember"
                                class="form-check-input"
                                name="remember"
                                type="checkbox"
                                value="1"
                                @checked(old('remember'))
                            >
                            <label class="form-check-label" for="remember">Ingat saya</label>
                        </div>

                        <button class="btn btn-primary rs-auth-submit d-inline-flex align-items-center justify-content-center gap-2" type="submit">
                            <i class="fa-solid fa-right-to-bracket" aria-hidden="true"></i>
                            <span>Masuk</span>
                        </button>
                    </form>
                </div>
            </section>
        </main>
    </body>
</html>
