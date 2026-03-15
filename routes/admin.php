<?php

use App\Http\Controllers\Admin\QueueHealthController;
use App\Http\Middleware\EnsureSuperAdmin;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', EnsureSuperAdmin::class])->group(function (): void {
    Route::get('admin/queue-health', QueueHealthController::class)
        ->name('admin.queue-health');
});
