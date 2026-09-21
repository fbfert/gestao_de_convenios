<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Modo simulação da finalização de guia na Unimed.
 *
 * Ligado por padrão, e não desligado: a primeira execução em produção será
 * contra uma guia real de um paciente real, porque a Unimed não tem ambiente
 * de homologação. "Gravar e Finalizar" encerra a guia na operadora e não tem
 * volta — o custo de desligar a flag depois de conferir é um clique, o custo
 * do contrário é uma guia finalizada errada lá dentro.
 *
 * Ver openspec/changes/automacao-unimed-finalizar-guia/design.md, decisão 6.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configuracoes_globais', function (Blueprint $table) {
            $table->boolean('automacao_finalizar_guia_simulacao_ativo')
                ->default(true)
                ->after('automacao_verificacao_incerta_ativo');
        });
    }

    public function down(): void
    {
        Schema::table('configuracoes_globais', function (Blueprint $table) {
            $table->dropColumn('automacao_finalizar_guia_simulacao_ativo');
        });
    }
};
