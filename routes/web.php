<?php

use App\Http\Controllers\DropdownController;
use Illuminate\Support\Facades\Route;

Route::get('/', DropdownController::class)->name('dropdown');
Route::get('/usage', [DropdownController::class, 'current'])->name('dropdown.usage');
Route::post('/refresh', [DropdownController::class, 'refresh'])->name('dropdown.refresh');
