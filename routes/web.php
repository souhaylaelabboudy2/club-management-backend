<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\GoogleAuthController;

// ============================================
// GOOGLE OAUTH — WEB ROUTES
// Ces routes restent dans web.php car elles
// effectuent des redirections navigateur et
// utilisent la session Laravel pour le state OAuth.
// ============================================
Route::prefix('api/auth/google')->group(function () {
    Route::get('/',              [GoogleAuthController::class, 'loginRedirect'])->name('google.login');
    Route::get('/callback',      [GoogleAuthController::class, 'loginCallback'])->name('google.callback');
    Route::get('/link',          [GoogleAuthController::class, 'linkRedirect'])->name('google.link');
    Route::get('/link/callback', [GoogleAuthController::class, 'linkCallback'])->name('google.link.callback');
});