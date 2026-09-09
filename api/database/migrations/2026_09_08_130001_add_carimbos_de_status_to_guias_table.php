<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cache desnormalizado do ultimo evento de cada tipo.
 *
 * Nao e redundancia com `guia_status_historico`, e sim diferenca de custo de
 * leitura: o card do dashboard faz polling de 30s e precisa de coluna indexada;
 * o grafico, quando existir, varre o historico. Os dois sao escritos pela mesma
 * transacao em GuiaService::registrarTransicao, entao nao divergem.
 *
 * `updated_at` nao serviria: qualquer edicao da guia a move, e a pergunta e
 * "quando foi negada", nao "quando foi mexida".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guias', function (Blueprint $table) {
            $table->timestamp('negada_em')->nullable()->after('data_finalizacao');
            $table->timestamp('aprovada_em')->nullable()->after('negada_em');

            // O card conta "negadas hoje / na semana" por este campo.
            $table->index(['tenant_id', 'negada_em']);
        });
    }

    public function down(): void
    {
        Schema::table('guias', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'negada_em']);
            $table->dropColumn(['negada_em', 'aprovada_em']);
        });
    }
};
