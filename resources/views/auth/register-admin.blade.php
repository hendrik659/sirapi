<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <title>Register Admin - SIRAPI</title>

    @vite([
        'resources/css/app.css',
        'resources/js/app.js'
    ])
</head>

<body>

<main class="container py-5">

    <div
        class="card border-0 shadow-sm mx-auto"
        style="max-width: 560px;"
    >
        <div class="card-body p-4 p-md-5">

            <div class="mb-4">
                <h1 class="h3 mb-2">
                    Register Admin SIRAPI
                </h1>

                <p class="text-muted mb-0">
                    Buat Admin pertama untuk memulai
                    penggunaan SIRAPI.
                </p>
            </div>

            <form
                method="POST"
                action="{{ route('initial-admin-register.store') }}"
            >
                @csrf

                {{-- SETUP CODE --}}
                <div class="mb-3">

                    <label
                        for="setup_code"
                        class="form-label"
                    >
                        Setup Code
                    </label>

                    <input
                        id="setup_code"
                        name="setup_code"
                        type="password"
                        class="form-control @error('setup_code') is-invalid @enderror"
                        autocomplete="off"
                        required
                    >

                    @error('setup_code')
                        <div class="invalid-feedback">
                            {{ $message }}
                        </div>
                    @enderror

                </div>

                {{-- NAME --}}
                <div class="mb-3">

                    <label
                        for="name"
                        class="form-label"
                    >
                        Nama Admin
                    </label>

                    <input
                        id="name"
                        name="name"
                        type="text"
                        value="{{ old('name') }}"
                        class="form-control @error('name') is-invalid @enderror"
                        autocomplete="name"
                        required
                    >

                    @error('name')
                        <div class="invalid-feedback">
                            {{ $message }}
                        </div>
                    @enderror

                </div>

                {{-- EMAIL --}}
                <div class="mb-3">

                    <label
                        for="email"
                        class="form-label"
                    >
                        Email
                    </label>

                    <input
                        id="email"
                        name="email"
                        type="email"
                        value="{{ old('email') }}"
                        class="form-control @error('email') is-invalid @enderror"
                        autocomplete="email"
                        required
                    >

                    @error('email')
                        <div class="invalid-feedback">
                            {{ $message }}
                        </div>
                    @enderror

                </div>

                {{-- PASSWORD --}}
                <div class="mb-3">

                    <label
                        for="password"
                        class="form-label"
                    >
                        Kata Sandi
                    </label>

                    <input
                        id="password"
                        name="password"
                        type="password"
                        class="form-control @error('password') is-invalid @enderror"
                        autocomplete="new-password"
                        required
                    >

                    @error('password')
                        <div class="invalid-feedback">
                            {{ $message }}
                        </div>
                    @enderror

                </div>

                {{-- CONFIRM PASSWORD --}}
                <div class="mb-4">

                    <label
                        for="password_confirmation"
                        class="form-label"
                    >
                        Konfirmasi Kata Sandi
                    </label>

                    <input
                        id="password_confirmation"
                        name="password_confirmation"
                        type="password"
                        class="form-control"
                        autocomplete="new-password"
                        required
                    >

                </div>

                <button
                    type="submit"
                    class="btn btn-primary w-100"
                >
                    <i
                        class="fa-solid fa-user-shield me-2"
                        aria-hidden="true"
                    ></i>

                    Daftar sebagai Admin
                </button>

            </form>

        </div>
    </div>

</main>

</body>
</html>
