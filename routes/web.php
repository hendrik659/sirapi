<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DivisionController;
use App\Http\Controllers\IncomingLetterController;
use App\Http\Controllers\IncomingLetterReviewController;
use App\Http\Controllers\InternshipCertificateController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OutgoingLetterController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\InitialAdminRegistrationController;
use App\Http\Middleware\EnsureInitialAdminRegistrationAvailable;

Route::middleware([
    'guest',
    'throttle:5,1',
    EnsureInitialAdminRegistrationAvailable::class,
])->group(function () {
    Route::get(
        '/setup/register-admin',
        [InitialAdminRegistrationController::class, 'create']
    )->name('initial-admin-register.create');

    Route::post(
        '/setup/register-admin',
        [InitialAdminRegistrationController::class, 'store']
    )->name('initial-admin-register.store');
});


Route::get('/', function () {
    return Auth::check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
});

Route::middleware(['auth', 'active'])
    ->prefix('dashboard')
    ->group(function () {
        Route::get('/', DashboardController::class)->name('dashboard');

        Route::get('/notifications', [NotificationController::class, 'index'])
            ->name('notifications.index');
        Route::patch('/notifications/read-all', [NotificationController::class, 'readAll'])
            ->name('notifications.read-all');
        Route::patch('/notifications/{notification}/open', [NotificationController::class, 'open'])
            ->name('notifications.open');

        Route::get('/reports/incoming-letters/export', [ReportController::class, 'exportIncomingLetters'])
            ->name('reports.incoming-letters.export');
        Route::get('/reports/incoming-letters', [ReportController::class, 'incomingLetters'])
            ->name('reports.incoming-letters.index');
        Route::get('/reports/outgoing-letters/export', [ReportController::class, 'exportOutgoingLetters'])
            ->name('reports.outgoing-letters.export');
        Route::get('/reports/outgoing-letters', [ReportController::class, 'outgoingLetters'])
            ->name('reports.outgoing-letters.index');
        Route::get('/reports/certificates/export', [ReportController::class, 'exportCertificates'])
            ->name('reports.certificates.export');
        Route::get('/reports/certificates', [ReportController::class, 'certificates'])
            ->name('reports.certificates.index');

        Route::get('/outgoing-letters', [OutgoingLetterController::class, 'index'])
            ->name('outgoing-letters.index');
        Route::get('/outgoing-letters/create', [OutgoingLetterController::class, 'create'])
            ->name('outgoing-letters.create');
        Route::post('/outgoing-letters', [OutgoingLetterController::class, 'store'])
            ->name('outgoing-letters.store');
        Route::get('/outgoing-letters/{outgoingLetter}/preview', [OutgoingLetterController::class, 'preview'])
            ->name('outgoing-letters.preview');
        Route::get('/outgoing-letters/{outgoingLetter}/download', [OutgoingLetterController::class, 'download'])
            ->name('outgoing-letters.download');
        Route::get('/outgoing-letters/{outgoingLetter}', [OutgoingLetterController::class, 'show'])
            ->name('outgoing-letters.show');

        Route::get('/certificates', [InternshipCertificateController::class, 'index'])
            ->name('dashboard.certificates.index');
        Route::get('/certificates/create', [InternshipCertificateController::class, 'create'])
            ->name('dashboard.certificates.create');
        Route::post('/certificates', [InternshipCertificateController::class, 'store'])
            ->name('dashboard.certificates.store');
        Route::get('/certificates/{certificate}/preview', [InternshipCertificateController::class, 'preview'])
            ->name('dashboard.certificates.preview');
        Route::get('/certificates/{certificate}/download', [InternshipCertificateController::class, 'download'])
            ->name('dashboard.certificates.download');
        Route::get('/certificates/{certificate}/edit', [InternshipCertificateController::class, 'edit'])
            ->name('dashboard.certificates.edit');
        Route::match(['put', 'patch'], '/certificates/{certificate}', [InternshipCertificateController::class, 'update'])
            ->name('dashboard.certificates.update');
        Route::get('/certificates/{certificate}', [InternshipCertificateController::class, 'show'])
            ->name('dashboard.certificates.show');

        Route::get('/incoming-letters', [IncomingLetterController::class, 'index'])
            ->name('incoming-letters.index');
        Route::get('/incoming-letters/{incomingLetter}/preview', [IncomingLetterController::class, 'preview'])
            ->name('incoming-letters.preview');
        Route::get('/incoming-letters/{incomingLetter}/download', [IncomingLetterController::class, 'download'])
            ->name('incoming-letters.download');
        Route::get('/incoming-letters/{incomingLetter}/review', [IncomingLetterReviewController::class, 'create'])
            ->name('incoming-letters.review.create');
        Route::post('/incoming-letters/{incomingLetter}/review', [IncomingLetterReviewController::class, 'store'])
            ->name('incoming-letters.review.store');
        Route::middleware('role:admin_surat')->group(function () {
            Route::get('/incoming-letters/create', [IncomingLetterController::class, 'create'])
                ->name('incoming-letters.create');
            Route::post('/incoming-letters', [IncomingLetterController::class, 'store'])
                ->name('incoming-letters.store');
            Route::get('/incoming-letters/{incomingLetter}/edit', [IncomingLetterController::class, 'edit'])
                ->name('incoming-letters.edit');
            Route::match(['put', 'patch'], '/incoming-letters/{incomingLetter}', [IncomingLetterController::class, 'update'])
                ->name('incoming-letters.update');
            Route::patch('/incoming-letters/{incomingLetter}/submit-for-review', [IncomingLetterController::class, 'submitForReview'])
                ->name('incoming-letters.submit-for-review');

            Route::patch('/users/{user}/status', [UserController::class, 'updateStatus'])
                ->name('users.status');
            Route::resource('users', UserController::class)->except('destroy');

            Route::patch('/divisions/{division}/status', [DivisionController::class, 'updateStatus'])
                ->name('divisions.status');
            Route::resource('divisions', DivisionController::class)->except('destroy');
        });

        Route::get('/incoming-letters/{incomingLetter}', [IncomingLetterController::class, 'show'])
            ->name('incoming-letters.show');
    });

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');
