<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configuracoes_globais', function (Blueprint $table) {
            // Guia "uncertain" pos-submit (sem numero de guia conhecido) e
            // verificada por paciente em "Exames em aberto" — job roda a cada
            // 30 min (EnfileirarConsultasUnimedDueJob), mas so age dentro
            // desta janela e respeitando este intervalo minimo por item.
            $table->unsignedSmallInteger('unimed_verificacao_incerta_intervalo_minutos')->default(60)->after('unimed_recheck_horas_falha');
            $table->time('unimed_verificacao_incerta_horario_inicio')->default('02:00:00')->after('unimed_verificacao_incerta_intervalo_minutos');
            $table->time('unimed_verificacao_incerta_horario_fim')->default('12:50:00')->after('unimed_verificacao_incerta_horario_inicio');
        });
    }

    public function down(): void
    {
        Schema::table('configuracoes_globais', function (Blueprint $table) {
            $table->dropColumn([
                'unimed_verificacao_incerta_intervalo_minutos',
                'unimed_verificacao_incerta_horario_inicio',
                'unimed_verificacao_incerta_horario_fim',
            ]);
        });
    }
};
