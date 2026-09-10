<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Override por convênio do padrão global de antecipação — nulo (o padrão de
 * cadastro) significa "usa o valor de configuracoes_globais", e não zero.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('convenios', function (Blueprint $table) {
            $table->unsignedSmallInteger('antecipacao_dias')->nullable()->after('carteirinha_blocos');
            $table->enum('antecipacao_referencia', ['validade_senha', 'data_finalizacao'])
                ->nullable()
                ->after('antecipacao_dias');
        });
    }

    public function down(): void
    {
        Schema::table('convenios', function (Blueprint $table) {
            $table->dropColumn(['antecipacao_dias', 'antecipacao_referencia']);
        });
    }
};
