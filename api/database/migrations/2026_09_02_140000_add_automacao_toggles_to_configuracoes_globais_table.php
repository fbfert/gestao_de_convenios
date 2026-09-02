<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Liga/desliga por automação, pedido depois que ficou claro que nenhuma das
 * automações de fundo (reconsulta Unimed, sincronização com a clínica,
 * expurgos, verificação diária de guias) podia ser pausada pela tela — só
 * editando código. A sincronização com a clínica também ganha intervalo
 * próprio (as demais já tinham prazo configurável: retenção de auditoria/
 * carteirinha, horas de reconsulta, janela de verificação incerta).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configuracoes_globais', function (Blueprint $table) {
            $table->boolean('automacao_reconsulta_status_ativo')->default(true)->after('unimed_recheck_horas_falha');
            $table->boolean('automacao_captura_senha_validade_ativo')->default(true)->after('automacao_reconsulta_status_ativo');
            $table->boolean('automacao_verificacao_incerta_ativo')->default(true)->after('unimed_verificacao_incerta_horario_fim');
            $table->boolean('automacao_sincronizacao_clinica_ativo')->default(true)->after('automacao_verificacao_incerta_ativo');
            $table->unsignedSmallInteger('automacao_sincronizacao_clinica_intervalo_minutos')->default(5)->after('automacao_sincronizacao_clinica_ativo');
            $table->boolean('automacao_expurgo_auditoria_ativo')->default(true)->after('automacao_sincronizacao_clinica_intervalo_minutos');
            $table->boolean('automacao_expurgo_carteirinhas_ativo')->default(true)->after('automacao_expurgo_auditoria_ativo');
            $table->boolean('automacao_verificacao_guias_diaria_ativo')->default(true)->after('automacao_expurgo_carteirinhas_ativo');
        });
    }

    public function down(): void
    {
        Schema::table('configuracoes_globais', function (Blueprint $table) {
            $table->dropColumn([
                'automacao_reconsulta_status_ativo',
                'automacao_captura_senha_validade_ativo',
                'automacao_verificacao_incerta_ativo',
                'automacao_sincronizacao_clinica_ativo',
                'automacao_sincronizacao_clinica_intervalo_minutos',
                'automacao_expurgo_auditoria_ativo',
                'automacao_expurgo_carteirinhas_ativo',
                'automacao_verificacao_guias_diaria_ativo',
            ]);
        });
    }
};
