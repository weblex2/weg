<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\EmailController;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');
});

Route::controller(EmailController::class)->group(function () {
    Route::get('/import', 'importEmailsFromImap')->middleware(['auth'])->name('email.import');
    Route::get('/emails', 'showMails')->middleware(['auth'])->name('email.show');
});
