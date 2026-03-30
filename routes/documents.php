<?php

use App\Http\Controllers\DocumentAnnotationController;
use App\Http\Controllers\DocumentController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'tenant'])->group(function (): void {
    Route::get('matters/{matter}/documents/create', [DocumentController::class, 'create'])
        ->name('matters.documents.create');
    Route::post('matters/{matter}/documents', [DocumentController::class, 'store'])
        ->name('matters.documents.store');

    Route::get('documents/{document}/preview', [DocumentController::class, 'preview'])
        ->name('documents.preview');
    Route::post('documents/{document}/review', [DocumentController::class, 'review'])
        ->name('documents.review');
    Route::post('documents/{document}/approve', [DocumentController::class, 'approve'])
        ->name('documents.approve');
    Route::post('documents/{document}/reject', [DocumentController::class, 'reject'])
        ->name('documents.reject');
    Route::patch('documents/{document}/reviewer', [DocumentController::class, 'assignReviewer'])
        ->name('documents.reviewer.assign');
    Route::post('documents/{document}/annotations', [DocumentAnnotationController::class, 'store'])
        ->name('documents.annotations.store');
    Route::delete('documents/{document}/annotations/{annotation}', [DocumentAnnotationController::class, 'destroy'])
        ->name('documents.annotations.destroy');

    Route::resource('documents', DocumentController::class)
        ->except(['create', 'store']);

    Route::get('documents/{document}/download', [DocumentController::class, 'download'])
        ->name('documents.download');
});
