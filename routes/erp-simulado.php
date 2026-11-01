<?php

use App\ErpSimulado\ErpSimuladoController;
use App\ErpSimulado\ExigirToken;
use Illuminate\Support\Facades\Route;

Route::post('/token', [ErpSimuladoController::class, 'token']);

Route::middleware(ExigirToken::class)->group(function () {
    Route::get('/beneficiarios', [ErpSimuladoController::class, 'buscar']);
    Route::post('/beneficiarios', [ErpSimuladoController::class, 'cadastrar']);
});
