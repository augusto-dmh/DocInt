<?php

use App\Http\Controllers\BulkDocumentReviewController;
use App\Http\Controllers\DocumentAnnotationController;
use App\Http\Controllers\DocumentCommentController;
use App\Http\Controllers\DocumentController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'tenant'])->group(function (): void {
    Route::get('matters/{matter}/documents/create', [DocumentController::class, 'create'])
        ->name('matters.documents.create');
    Route::post('matters/{matter}/documents', [DocumentController::class, 'store'])
        ->name('matters.documents.store');

    Route::get('documents/{document}/preview', [DocumentController::class, 'preview'])
        ->name('documents.preview');
    Route::post('documents/bulk/approve', [BulkDocumentReviewController::class, 'approve'])
        ->name('documents.bulk.approve');
    Route::post('documents/bulk/reject', [BulkDocumentReviewController::class, 'reject'])
        ->name('documents.bulk.reject');
    Route::patch('documents/bulk/reviewer', [BulkDocumentReviewController::class, 'assignReviewer'])
        ->name('documents.bulk.reviewer.assign');
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
    Route::get('documents/{document}/comments', [DocumentCommentController::class, 'index'])
        ->name('documents.comments.index');
    Route::post('documents/{document}/comments', [DocumentCommentController::class, 'store'])
        ->name('documents.comments.store');
    Route::patch('documents/{document}/comments/{comment}', [DocumentCommentController::class, 'update'])
        ->name('documents.comments.update');
    Route::delete('documents/{document}/comments/{comment}', [DocumentCommentController::class, 'destroy'])
        ->name('documents.comments.destroy');

    Route::resource('documents', DocumentController::class)
        ->except(['create', 'store']);

    Route::get('documents/{document}/download', [DocumentController::class, 'download'])
        ->name('documents.download');
});
