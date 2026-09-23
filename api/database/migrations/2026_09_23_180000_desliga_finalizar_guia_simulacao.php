<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Desliga o modo simulação da finalização Unimed nas clínicas existentes.
 *
 * Decisão do responsável em 23/09/2026, depois de conferir as simulações: a
 * partir do deploy, finalizar grava e finaliza a guia na operadora. O default
 * da coluna continua ligado — clínica nova começa em simulação, pelo mesmo
 * motivo da migration que criou a coluna.
 *
 * Ver openspec/changes/ajustes-dashboard-lancamento-simulacao.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('configuracoes_globais')->update(['automacao_finalizar_guia_simulacao_ativo' => false]);
    }

    public function down(): void
    {
        DB::table('configuracoes_globais')->update(['automacao_finalizar_guia_simulacao_ativo' => true]);
    }
};
