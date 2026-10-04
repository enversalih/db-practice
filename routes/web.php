<?php

use App\Http\Controllers\HospitalController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HospitalController::class, 'index'])->name('home');
Route::get('/api/tables/{table}', [HospitalController::class, 'table'])->name('tables.data');
Route::post('/api/training/evaluate', [HospitalController::class, 'evaluate'])->name('training.evaluate');
