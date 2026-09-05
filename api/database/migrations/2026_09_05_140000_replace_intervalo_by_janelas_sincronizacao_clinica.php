<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Substitui o intervalo único e fixo da sincronização com o clinica por 3
 * janelas de horário, cada uma com seu próprio intervalo — o volume de
 * mudanças de cadastro varia muito entre horário comercial, fim de tarde e
 * madrugada. Ver SincronizarClinicaJob::intervaloAtual().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configuracoes_globais', function (Blueprint $table) {
            $table->dropColumn('automacao_sincronizacao_clinica_intervalo_minutos');

            $table->time('automacao_sincronizacao_clinica_diurno_horario_inicio')->default('08:00:00');
            $table->time('automacao_sincronizacao_clinica_diurno_horario_fim')->default('18:00:00');
            $table->unsignedSmallInteger('automacao_sincronizacao_clinica_diurno_intervalo_minutos')->default(10);

            $table->time('automacao_sincronizacao_clinica_noturno_horario_inicio')->default('18:00:00');
            $table->time('automacao_sincronizacao_clinica_noturno_horario_fim')->default('22:00:00');
            $table->unsignedSmallInteger('automacao_sincronizacao_clinica_noturno_intervalo_minutos')->default(30);

            $table->time('automacao_sincronizacao_clinica_madrugada_horario_inicio')->default('22:00:00');
            $table->time('automacao_sincronizacao_clinica_madrugada_horario_fim')->default('07:59:00');
            $table->unsignedSmallInteger('automacao_sincronizacao_clinica_madrugada_intervalo_minutos')->default(60);
        });
    }

    public function down(): void
    {
        Schema::table('configuracoes_globais', function (Blueprint $table) {
            $table->dropColumn([
                'automacao_sincronizacao_clinica_diurno_horario_inicio',
                'automacao_sincronizacao_clinica_diurno_horario_fim',
                'automacao_sincronizacao_clinica_diurno_intervalo_minutos',
                'automacao_sincronizacao_clinica_noturno_horario_inicio',
                'automacao_sincronizacao_clinica_noturno_horario_fim',
                'automacao_sincronizacao_clinica_noturno_intervalo_minutos',
                'automacao_sincronizacao_clinica_madrugada_horario_inicio',
                'automacao_sincronizacao_clinica_madrugada_horario_fim',
                'automacao_sincronizacao_clinica_madrugada_intervalo_minutos',
            ]);

            $table->unsignedSmallInteger('automacao_sincronizacao_clinica_intervalo_minutos')->default(5);
        });
    }
};
