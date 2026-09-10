<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Padrão global de antecipação: daqui quantos dias (antes da referência
 * escolhida) o alerta "hora de gerar o próximo ciclo" nasce. Convênio pode
 * sobrescrever (ver convenios.antecipacao_dias) e a guia pode sobrescrever de
 * novo com uma data manual (guias.antecipacao_data_alvo).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configuracoes_globais', function (Blueprint $table) {
            $table->unsignedSmallInteger('antecipacao_dias')->default(20)->after('senha_alerta_dias');
            $table->enum('antecipacao_referencia', ['validade_senha', 'data_finalizacao'])
                ->default('validade_senha')
                ->after('antecipacao_dias');
        });
    }

    public function down(): void
    {
        Schema::table('configuracoes_globais', function (Blueprint $table) {
            $table->dropColumn(['antecipacao_dias', 'antecipacao_referencia']);
        });
    }
};
