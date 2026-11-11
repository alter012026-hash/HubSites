<?php

use Illuminate\Support\Facades\Route;

Route::get('/health', fn () => response()->json([
    'status' => 'ok',
    'api_version' => 'v1',
]))->name('api.v1.health');
