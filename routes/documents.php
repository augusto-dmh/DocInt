<?php

use App\Http\Controllers\DocumentController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'tenant'])->group(function (): void {
    Route::get('matters/{matter}/documents/create', [DocumentController::class, 'create'])
        ->name('matters.documents.create');
    Route::post('matters/{matter}/documents', [DocumentController::class, 'store'])
        ->name('matters.documents.store');

    Route::resource('documents', DocumentController::class)
        ->except(['create', 'store']);

    Route::get('documents/{document}/download', [DocumentController::class, 'download'])
        ->name('documents.download');
});
