<?php

use App\Http\Controllers\ArticleController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\SiteController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])->middleware('throttle:5,1')->name('login.store');
});

Route::middleware('auth')->group(function (): void {
    Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::get('/sites', [SiteController::class, 'index'])->name('sites.index');
    Route::get('/sites/create', [SiteController::class, 'create'])->name('sites.create');
    Route::post('/sites', [SiteController::class, 'store'])->name('sites.store');
    Route::get('/sites/{site}/edit', [SiteController::class, 'edit'])->whereNumber('site')->name('sites.edit');
    Route::put('/sites/{site}', [SiteController::class, 'update'])->whereNumber('site')->name('sites.update');
    Route::post('/sites/{site}/test', [SiteController::class, 'test'])->whereNumber('site')->name('sites.test');
    Route::post('/sites/{site}/pause', [SiteController::class, 'pause'])->whereNumber('site')->name('sites.pause');
    Route::post('/sites/{site}/resume', [SiteController::class, 'resume'])->whereNumber('site')->name('sites.resume');
    Route::delete('/sites/{site}/connection', [SiteController::class, 'disconnect'])->whereNumber('site')->name('sites.disconnect');

    Route::get('/articles', [ArticleController::class, 'index'])->name('articles.index');
    Route::get('/articles/create', [ArticleController::class, 'create'])->name('articles.create');
    Route::post('/articles', [ArticleController::class, 'store'])->name('articles.store');
    Route::post('/articles/{article}/send', [ArticleController::class, 'send'])->whereNumber('article')->name('articles.send');
});
