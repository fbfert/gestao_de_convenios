<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class ConfiguracaoGlobal extends Model
{
    use Auditable, BelongsToTenant;

    protected $table = 'configuracoes_globais';

    protected $fillable = [
        'tenant_id',
        'sessao_minutos',
        'senha_alerta_dias',
        'sessoes_padrao',
        'itens_por_pagina',
        'auditoria_retencao_meses',
        'carteirinha_retencao_dias',
        'unimed_recheck_horas_sucesso',
        'unimed_recheck_horas_falha',
        'unimed_verificacao_incerta_intervalo_minutos',
        'unimed_verificacao_incerta_horario_inicio',
        'unimed_verificacao_incerta_horario_fim',
        'automacao_reconsulta_status_ativo',
        'automacao_captura_senha_validade_ativo',
        'unimed_captura_senha_validade_intervalo_horas',
        'automacao_verificacao_incerta_ativo',
        'automacao_sincronizacao_clinica_ativo',
        'automacao_sincronizacao_clinica_diurno_horario_inicio',
        'automacao_sincronizacao_clinica_diurno_horario_fim',
        'automacao_sincronizacao_clinica_diurno_intervalo_minutos',
        'automacao_sincronizacao_clinica_noturno_horario_inicio',
        'automacao_sincronizacao_clinica_noturno_horario_fim',
        'automacao_sincronizacao_clinica_noturno_intervalo_minutos',
        'automacao_sincronizacao_clinica_madrugada_horario_inicio',
        'automacao_sincronizacao_clinica_madrugada_horario_fim',
        'automacao_sincronizacao_clinica_madrugada_intervalo_minutos',
        'automacao_expurgo_auditoria_ativo',
        'automacao_expurgo_carteirinhas_ativo',
        'automacao_verificacao_guias_diaria_ativo',
    ];

    protected $casts = [
        'sessao_minutos' => 'integer',
        'senha_alerta_dias' => 'integer',
        'sessoes_padrao' => 'integer',
        'itens_por_pagina' => 'integer',
        'auditoria_retencao_meses' => 'integer',
        'carteirinha_retencao_dias' => 'integer',
        'unimed_recheck_horas_sucesso' => 'integer',
        'unimed_recheck_horas_falha' => 'integer',
        'unimed_verificacao_incerta_intervalo_minutos' => 'integer',
        'automacao_reconsulta_status_ativo' => 'boolean',
        'automacao_captura_senha_validade_ativo' => 'boolean',
        'unimed_captura_senha_validade_intervalo_horas' => 'integer',
        'automacao_verificacao_incerta_ativo' => 'boolean',
        'automacao_sincronizacao_clinica_ativo' => 'boolean',
        'automacao_sincronizacao_clinica_diurno_intervalo_minutos' => 'integer',
        'automacao_sincronizacao_clinica_noturno_intervalo_minutos' => 'integer',
        'automacao_sincronizacao_clinica_madrugada_intervalo_minutos' => 'integer',
        'automacao_expurgo_auditoria_ativo' => 'boolean',
        'automacao_expurgo_carteirinhas_ativo' => 'boolean',
        'automacao_verificacao_guias_diaria_ativo' => 'boolean',
    ];

    /**
     * Configuração do tenant, criando a linha com os padrões se ainda não
     * existir. Nunca devolve null: quem lê não precisa tratar o caso de um
     * tenant que nunca abriu a tela.
     *
     * Quando cria, o `INSERT` só leva `tenant_id` — os demais campos ficam a
     * cargo dos defaults da coluna no banco, que a instância recém-criada em
     * memória não enxerga sozinha (Eloquent não busca de volta o que o banco
     * preencheu). Sem o `fresh()`, o primeiro tenant a nunca ter aberto a
     * tela de configurações leria todo campo com default (inclusive os
     * liga/desliga de automação) como `null`, e a automação seria pulada por
     * engano — só passa despercebido quando algo já criou a linha antes.
     */
    public static function doTenant(int $tenantId): self
    {
        $configuracao = static::query()->firstOrCreate(['tenant_id' => $tenantId]);

        return $configuracao->wasRecentlyCreated ? $configuracao->fresh() : $configuracao;
    }
}
