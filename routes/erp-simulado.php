<?php

use App\ErpSimulado\ErpSimuladoController;
use Illuminate\Support\Facades\Route;

Route::get('/beneficiarios', [ErpSimuladoController::class, 'buscar']);
Route::post('/beneficiarios', [ErpSimuladoController::class, 'cadastrar']);
