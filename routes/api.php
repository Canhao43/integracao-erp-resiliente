<?php

use App\Http\Controllers\BeneficiarioController;
use Illuminate\Support\Facades\Route;

Route::post('/beneficiarios', [BeneficiarioController::class, 'store'])->name('beneficiarios.store');
Route::get('/beneficiarios/{beneficiario}', [BeneficiarioController::class, 'show'])->name('beneficiarios.show');
