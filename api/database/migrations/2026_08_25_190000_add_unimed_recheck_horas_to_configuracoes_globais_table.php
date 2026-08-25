<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configuracoes_globais', function (Blueprint $table) {
            // Quando a consulta de status Unimed falha por erro tecnico (timeout
            // de automacao etc.), o proximo agendamento usa este prazo curto em
            // vez do prazo normal — antes ficava preso a +24h mesmo em falha.
            $table->unsignedSmallInteger('unimed_recheck_horas_sucesso')->default(24)->after('carteirinha_retencao_dias');
            $table->unsignedSmallInteger('unimed_recheck_horas_falha')->default(2)->after('unimed_recheck_horas_sucesso');
        });
    }

    public function down(): void
    {
        Schema::table('configuracoes_globais', function (Blueprint $table) {
            $table->dropColumn(['unimed_recheck_horas_sucesso', 'unimed_recheck_horas_falha']);
        });
    }
};
