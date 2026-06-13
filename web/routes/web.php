<?php

use App\Http\Controllers\BackupController;
use App\Http\Controllers\DatabaseController;
use App\Http\Controllers\FilesController;
use App\Http\Controllers\PhpMyAdminController;
use Illuminate\Support\Facades\Route;

// File manager download / edit / save — guarded by the panel's auth guard.
Route::middleware('auth')->group(function () {
    Route::get('/files/download', [FilesController::class, 'download'])->name('files.download');
    Route::get('/databases/{database}/export', [DatabaseController::class, 'export'])->name('databases.export');
    Route::get('/backup/download', [BackupController::class, 'download'])->name('backup.download');
    Route::get('/phpmyadmin-sso', [PhpMyAdminController::class, 'signon'])->name('pma.sso');
});
