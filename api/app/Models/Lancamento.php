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
        'tenant_id', 'guia_id', 'profissional_id',
        'data_sessao', 'hora_inicio', 'hora_fim', 'acompanhante',
        'resumo_atividades', 'transcricao_bruta', 'status', 'observacoes',
    ];

    protected $casts = [
        'data_sessao' => 'date',
    ];

    public function guia()
    {
        return $this->belongsTo(Guia::class);
    }

    public function profissional()
    {
        return $this->belongsTo(Profissional::class);
    }
}
