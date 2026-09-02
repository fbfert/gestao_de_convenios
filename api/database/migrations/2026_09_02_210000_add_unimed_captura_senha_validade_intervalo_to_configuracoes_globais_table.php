<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Busca de senha/validade Unimed deixa de seguir o ciclo de 30 min da
 * reconsulta de status (EnfileirarConsultasUnimedDueJob roda a cada 30 min,
 * mas a partir de agora só reprocessa uma guia depois deste intervalo
 * próprio) — ganha prazo configurável entre 1h/6h/12h/24h, igual ao padrão
 * já usado pela reconsulta de status (unimed_recheck_horas_*).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configuracoes_globais', function (Blueprint $table) {
            $table->unsignedSmallInteger('unimed_captura_senha_validade_intervalo_horas')
                ->default(6)
                ->after('automacao_captura_senha_validade_ativo');
        });

        Schema::table('guias', function (Blueprint $table) {
            $table->timestamp('unimed_senha_validade_next_check_at')->nullable()->after('unimed_next_check_at');
        });
    }

    public function down(): void
    {
        Schema::table('guias', function (Blueprint $table) {
            $table->dropColumn('unimed_senha_validade_next_check_at');
        });

        Schema::table('configuracoes_globais', function (Blueprint $table) {
            $table->dropColumn('unimed_captura_senha_validade_intervalo_horas');
        });
    }
};
