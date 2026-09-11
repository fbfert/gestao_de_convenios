<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Terceira opção de referência: "Dias após a Guia Criada", contada a partir
 * de `guias.data_solicitacao` (a data de emissão — mesmo campo que a
 * automação Unimed preenche com DT_EMISSAO_GUIA). Diferente das outras duas
 * (vencimentos, contados pra trás), esta conta pra frente — ver
 * Guia::antecipacaoDataAlvo().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configuracoes_globais', function (Blueprint $table) {
            $table->enum('antecipacao_referencia', ['validade_senha', 'data_finalizacao', 'data_solicitacao'])
                ->default('validade_senha')
                ->change();
        });

        Schema::table('convenios', function (Blueprint $table) {
            $table->enum('antecipacao_referencia', ['validade_senha', 'data_finalizacao', 'data_solicitacao'])
                ->nullable()
                ->change();
        });
    }

    public function down(): void
    {
        Schema::table('configuracoes_globais', function (Blueprint $table) {
            $table->enum('antecipacao_referencia', ['validade_senha', 'data_finalizacao'])
                ->default('validade_senha')
                ->change();
        });

        Schema::table('convenios', function (Blueprint $table) {
            $table->enum('antecipacao_referencia', ['validade_senha', 'data_finalizacao'])
                ->nullable()
                ->change();
        });
    }
};
