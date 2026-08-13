<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Lancamento extends Model
{
    use Auditable, BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id', 'antecipacao_id', 'profissional_id',
        'data_sessao', 'hora_inicio', 'hora_fim', 'acompanhante',
        'resumo_atividades', 'transcricao_bruta', 'status', 'observacoes',
    ];

    protected $casts = [
        'data_sessao' => 'date',
    ];

    public function antecipacao()
    {
        return $this->belongsTo(Antecipacao::class);
    }

    public function profissional()
    {
        return $this->belongsTo(Profissional::class);
    }
}
