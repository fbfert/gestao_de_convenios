<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Redundante desde a migration anterior: todo pedido médico (inclusive os
 * de solicitações antigas, via backfill) já tem uma linha própria em
 * solicitacao_documentos + paciente_arquivos. SolicitacaoResource e a rota de
 * download passam a ler de lá.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('solicitacoes', function (Blueprint $table) {
            $table->dropColumn([
                'pedido_medico_path',
                'pedido_medico_nome_original',
                'pedido_medico_mime',
                'pedido_medico_ai_result',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('solicitacoes', function (Blueprint $table) {
            $table->string('pedido_medico_path')->nullable();
            $table->string('pedido_medico_nome_original')->nullable();
            $table->string('pedido_medico_mime')->nullable();
            $table->json('pedido_medico_ai_result')->nullable();
        });
    }
};
