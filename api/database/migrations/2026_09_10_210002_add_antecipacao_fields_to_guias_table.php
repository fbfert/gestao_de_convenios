<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `antecipacao_data_alvo`: data manual que sobrescreve a regra automática
 * (dias + referência) para esta guia específica — ver Guia::antecipacaoDataAlvo().
 *
 * `alerta_antecipacao_ocultado_em`: mesmo mecanismo de dispensa que
 * `alerta_negacao_ocultado_em` já usa para guia.negada — a pessoa ocultou e o
 * avaliador para de devolver esta guia (ver App\Services\Alertas\Regras\AntecipacaoDevida).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guias', function (Blueprint $table) {
            $table->date('antecipacao_data_alvo')->nullable()->after('validade_senha');
            $table->timestamp('alerta_antecipacao_ocultado_em')->nullable()->after('alerta_negacao_ocultado_em');
        });
    }

    public function down(): void
    {
        Schema::table('guias', function (Blueprint $table) {
            $table->dropColumn(['antecipacao_data_alvo', 'alerta_antecipacao_ocultado_em']);
        });
    }
};
