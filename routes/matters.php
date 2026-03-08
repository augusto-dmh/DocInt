<?php

use App\Http\Controllers\MatterController;
use Illuminate\Support\Facades\Route;

Route::resource('matters', MatterController::class)
    ->middleware(['auth', 'verified', 'tenant']);
