<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="color-scheme" content="light">
        <title>Laravel</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="bg-body-tertiary">
        <div class="container d-flex min-vh-100 flex-column py-4">
            @if (Route::has('login'))
                <header class="d-flex justify-content-end mb-4">
                    <nav class="d-flex gap-2" aria-label="Navigasi autentikasi">
                        @auth
                            <a class="btn btn-primary" href="{{ url('/dashboard') }}">
                                <i class="fa-solid fa-gauge-high me-2" aria-hidden="true"></i>
                                Dashboard
                            </a>
                        @else
                            <a class="btn btn-outline-primary" href="{{ route('login') }}">
                                <i class="fa-solid fa-right-to-bracket me-2" aria-hidden="true"></i>
                                Log in
                            </a>
                        @endauth
                    </nav>
                </header>
            @endif

            <main class="d-flex flex-grow-1 align-items-center justify-content-center py-4">
                <section class="card w-100 overflow-hidden border-0 shadow-sm" aria-labelledby="welcome-title">
                    <div class="row g-0">
                        <div class="col-12 col-lg-7">
                            <div class="card-body p-4 p-md-5">
                                <h1 id="welcome-title" class="h3 mb-2">Let's get started</h1>
                                <p class="mb-4 text-body-secondary">
                                    Laravel has an incredibly rich ecosystem.<br>
                                    We suggest starting with the following.
                                </p>

                                <ul class="list-group list-group-flush mb-4">
                                    <li class="list-group-item px-0 py-3">
                                        <div class="d-flex align-items-start gap-3">
                                            <span class="text-primary" aria-hidden="true">
                                                <i class="fa-solid fa-book-open"></i>
                                            </span>
                                            <span>
                                                Read the
                                                <a href="https://laravel.com/docs" target="_blank" rel="noopener noreferrer">
                                                    Documentation
                                                    <i class="fa-solid fa-arrow-up-right-from-square ms-1 small" aria-hidden="true"></i>
                                                </a>
                                            </span>
                                        </div>
                                    </li>
                                    <li class="list-group-item px-0 py-3">
                                        <div class="d-flex align-items-start gap-3">
                                            <span class="text-primary" aria-hidden="true">
                                                <i class="fa-solid fa-circle-play"></i>
                                            </span>
                                            <span>
                                                Watch video tutorials at
                                                <a href="https://laracasts.com" target="_blank" rel="noopener noreferrer">
                                                    Laracasts
                                                    <i class="fa-solid fa-arrow-up-right-from-square ms-1 small" aria-hidden="true"></i>
                                                </a>
                                            </span>
                                        </div>
                                    </li>
                                </ul>

                                <a class="btn btn-dark" href="https://cloud.laravel.com" target="_blank" rel="noopener noreferrer">
                                    <i class="fa-solid fa-cloud-arrow-up me-2" aria-hidden="true"></i>
                                    Deploy now
                                </a>
                            </div>
                        </div>
                        <div class="col-12 col-lg-5 d-flex align-items-center justify-content-center bg-primary p-5 text-white">
                            <div class="text-center">
                                <i class="fa-brands fa-laravel fa-5x mb-3" aria-hidden="true"></i>
                                <p class="h4 mb-0 fw-semibold">Laravel</p>
                            </div>
                        </div>
                    </div>
                </section>
            </main>
        </div>
    </body>
</html>
