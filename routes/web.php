<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FailedLoginController;
use App\Http\Controllers\LoginLogController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\StaffController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('dashboard'));

/*
|--------------------------------------------------------------------------
| Module 1 — Authentication (guests only)
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    // Brute-force lockout (5 failed attempts -> 1 minute) is handled inside LoginController,
    // so the login page can show a countdown instead of a raw 429 error page.
    Route::post('/login', [LoginController::class, 'store'])->name('login.store');
});

/*
|--------------------------------------------------------------------------
| Logged-in area (ACTIVE accounts only)
|--------------------------------------------------------------------------
| Other modules: add your routes inside this group, and wrap Owner-only
| screens in ->middleware('role:OWNER'). Cashier-allowed screens use
| ->middleware('role:OWNER,CASHIER') or simply no role middleware.
*/
Route::middleware(['auth', 'active', 'password.changed'])->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    // Module 1 — My Profile (every role; also the only page open while a password change is pending)
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');

    // Module 1 — Owner only
    Route::middleware('role:OWNER')->group(function () {
        Route::resource('staff', StaffController::class)
            ->parameters(['staff' => 'user'])
            ->except('show');
        Route::patch('staff/{user}/status', [StaffController::class, 'toggleStatus'])
            ->name('staff.toggle-status');

        Route::get('reports/login-logs', [LoginLogController::class, 'index'])
            ->name('reports.login-logs');
        Route::get('reports/login-logs/export', [LoginLogController::class, 'export'])
            ->name('reports.login-logs.export');
        Route::get('reports/failed-logins', [FailedLoginController::class, 'index'])
            ->name('reports.failed-logins');
    });
});
