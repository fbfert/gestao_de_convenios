<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Marca de leitura de novidade, POR USUARIO.
 *
 * Sem BelongsToTenant de proposito: leitura e de pessoa, nao de clinica. Dois
 * usuarios do mesmo tenant leem em momentos diferentes, e marcar por tenant
 * esconderia a novidade de quem ainda nao viu.
 *
 * Sem `updated_at`: a marca nao e editada.
 */
class NovidadeLeitura extends Model
{
    public $timestamps = false;

    protected $table = 'novidade_leituras';

    protected $fillable = ['user_id', 'slug', 'lido_em'];

    protected $casts = ['lido_em' => 'datetime'];
}
