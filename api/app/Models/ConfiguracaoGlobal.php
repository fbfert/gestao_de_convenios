<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class ConfiguracaoGlobal extends Model
{
    use BelongsToTenant;

    protected $table = 'configuracoes_globais';

    protected $fillable = [
        'tenant_id',
        'sessao_minutos',
        'senha_alerta_dias',
        'sessoes_padrao',
        'itens_por_pagina',
    ];

    protected $casts = [
        'sessao_minutos' => 'integer',
        'senha_alerta_dias' => 'integer',
        'sessoes_padrao' => 'integer',
        'itens_por_pagina' => 'integer',
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
