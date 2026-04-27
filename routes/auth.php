<?php

use App\Http\Controllers\Auth\AcceptInviteController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\ConfirmablePasswordController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\EmailVerificationPromptController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\VerifyEmailController;
use Illuminate\Support\Facades\Route;

// Public self-registration is INTENTIONALLY DISABLED. In a multi-tenant SaaS, user
// accounts must always belong to a school and have a role assigned -- both are
// excluded from User::$fillable to prevent privilege escalation. New users are
// created exclusively via:
//   - school_admin → POST /admin/users (CreateUserAction)
//   - super_admin  → ProvisionSchoolAction (creates School + first school_admin)
//
// Removing the register routes also closes the gap that allowed anonymous users
// to bypass the `verified` middleware (User does not implement MustVerifyEmail),
// which could create accounts in a permissions limbo.
Route::middleware('guest')->group(function () {
    Route::get('login', [AuthenticatedSessionController::class, 'create'])
        ->name('login');

    Route::post('login', [AuthenticatedSessionController::class, 'store']);

    Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])
        ->name('password.request');

    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])
        ->name('password.email');

    Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])
        ->name('password.reset');

    Route::post('reset-password', [NewPasswordController::class, 'store'])
        ->name('password.store');

    // Wave 9 invite flow: anonymous endpoints because the bearer of a valid
    // token IS authenticated as the target user for password setup.
    // AcceptInviteController re-checks the token on every request.
    Route::get('invite/{token}', [AcceptInviteController::class, 'show'])
        ->name('invite.accept');

    Route::post('invite/{token}', [AcceptInviteController::class, 'accept'])
        ->name('invite.store');
});

Route::middleware('auth')->group(function () {
    Route::get('verify-email', EmailVerificationPromptController::class)
        ->name('verification.notice');

    Route::get('verify-email/{id}/{hash}', VerifyEmailController::class)
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    Route::post('email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('verification.send');

    Route::get('confirm-password', [ConfirmablePasswordController::class, 'show'])
        ->name('password.confirm');

    Route::post('confirm-password', [ConfirmablePasswordController::class, 'store']);

    Route::put('password', [PasswordController::class, 'update'])->name('password.update');

    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');
});
