<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\NoteController;
use App\Http\Controllers\Api\TagController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

// Auth : Register, login, logout, forgot pass, reset pass, email verif...

// Nom conventionnel Laravel attendu par la notification VerifyEmail.
Route::get('/email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])
    ->middleware('signed')
    ->name('verification.verify');

Route::controller(AuthController::class)->name('auth.')->group(function () {
    Route::post('/register', 'register')->name('register');
    Route::post('/login', 'login')->name('login');
    Route::post('/forgot-password', 'forgotPassword')->name('forgot-password');
    Route::post('/reset-password', 'resetPassword')->name('reset-password');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', 'logout')->name('logout');
        Route::post('/email/verification-notification', 'sendVerificationEmail')
            ->middleware('throttle:6,1')
            ->name('email.verification-notification');
    });
});

Route::middleware('auth:sanctum')->group(function () {

    // User : Show, Edit, Delete


    Route::controller(UserController::class)->prefix('/user')->name('user.')->group(function () {
        Route::get('/', 'show')->name('show');
        Route::put('/profile', 'updateProfile')->name('profile.update');
        Route::put('/password', 'updatePassword')->name('password.update');
        Route::delete('/', 'destroy')->name('destroy');
    });

    // Notes: CRUD

    Route::controller(NoteController::class)->prefix('/notes')->name('notes.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->name('store');
        Route::delete('/{note}', 'destroy')->name('destroy');
    });

    // Tags: CRUD

    Route::controller(TagController::class)->prefix('/tags')->name('tags.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->name('store');
    });
});
