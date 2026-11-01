<?php

use App\Http\Controllers\BeneficiarioController;
use App\Http\Middleware\GarantirIdempotencia;
use Illuminate\Support\Facades\Route;

Route::post('/beneficiarios', [BeneficiarioController::class, 'store'])
    ->middleware(GarantirIdempotencia::class)
    ->name('beneficiarios.store');
Route::get('/beneficiarios/{beneficiario}', [BeneficiarioController::class, 'show'])->name('beneficiarios.show');
Route::get('/beneficiarios/{beneficiario}/chamadas-erp', [BeneficiarioController::class, 'chamadasErp'])->name('beneficiarios.chamadas-erp');
