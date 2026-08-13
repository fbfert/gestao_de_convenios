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
    ];

    protected $casts = [
        'sessao_minutos' => 'integer',
        'senha_alerta_dias' => 'integer',
        'sessoes_padrao' => 'integer',
        'itens_por_pagina' => 'integer',
        'auditoria_retencao_meses' => 'integer',
        'carteirinha_retencao_dias' => 'integer',
    ];

    /**
     * Configuração do tenant, criando a linha com os padrões se ainda não
     * existir. Nunca devolve null: quem lê não precisa tratar o caso de um
     * tenant que nunca abriu a tela.
     */
    public static function doTenant(int $tenantId): self
    {
        return static::query()->firstOrCreate(['tenant_id' => $tenantId]);
    }
}
