<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\AntecipacaoController;
use App\Http\Controllers\ConciliacaoController;
use App\Http\Controllers\GuiaController;
use App\Http\Controllers\LancamentoController;
use App\Http\Controllers\SolicitacaoController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    Route::get('/solicitacoes', [SolicitacaoController::class, 'index']);
    Route::post('/solicitacoes', [SolicitacaoController::class, 'store']);
    Route::get('/solicitacoes/{solicitacao}', [SolicitacaoController::class, 'show']);
    Route::patch('/solicitacoes/{solicitacao}/aprovar', [SolicitacaoController::class, 'aprovar']);
    Route::patch('/solicitacoes/{solicitacao}/negar', [SolicitacaoController::class, 'negar']);

    Route::get('/guias', [GuiaController::class, 'index']);
    Route::post('/guias', [GuiaController::class, 'store']);
    Route::get('/guias/{guia}', [GuiaController::class, 'show']);
    Route::patch('/guias/{guia}/finalizar', [GuiaController::class, 'finalizar']);
    Route::patch('/guias/{guia}/negar', [GuiaController::class, 'negar']);

    Route::get('/antecipacoes', [AntecipacaoController::class, 'index']);
    Route::get('/antecipacoes/{antecipacao}', [AntecipacaoController::class, 'show']);
    Route::post('/antecipacoes/{antecipacao}/lancamentos', [LancamentoController::class, 'store']);

    Route::get('/lancamentos', [LancamentoController::class, 'index']);

    Route::post('/guias/{guia}/conciliacao', [ConciliacaoController::class, 'store']);
    Route::get('/conciliacoes', [ConciliacaoController::class, 'index']);
    Route::patch('/conciliacoes/{conciliacao}/marcar-conferido', [ConciliacaoController::class, 'marcarConferido']);
    Route::patch('/conciliacoes/{conciliacao}/marcar-pago', [ConciliacaoController::class, 'marcarPago']);

    Route::post('/logout', [AuthController::class, 'logout']);
});
