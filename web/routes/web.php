<?php

use App\Http\Controllers\DatabaseController;
use App\Http\Controllers\FilesController;
use Illuminate\Support\Facades\Route;

// File manager download / edit / save — guarded by the panel's auth guard.
Route::middleware('auth')->group(function () {
    Route::get('/files/download', [FilesController::class, 'download'])->name('files.download');
    Route::get('/databases/{database}/export', [DatabaseController::class, 'export'])->name('databases.export');
});
