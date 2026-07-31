<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SolicitacaoItem extends Model
{
    use BelongsToTenant, HasFactory;

    protected $table = 'solicitacao_itens';

    protected $fillable = [
        'tenant_id',
        'solicitacao_id',
        'especialidade_id',
        'profissional_id',
        'quantidade',
        'status_operacional',
        'observacoes',
    ];

    protected $casts = [
        'quantidade' => 'integer',
    ];

    public function solicitacao()
    {
        return $this->belongsTo(Solicitacao::class);
    }

    public function especialidade()
    {
        return $this->belongsTo(Especialidade::class);
    }

    public function profissional()
    {
        return $this->belongsTo(Profissional::class);
    }

    public function documentos()
    {
        return $this->hasMany(SolicitacaoDocumento::class);
    }

    public function guia()
    {
        return $this->hasOne(Guia::class);
    }

    public function automacaoExecucoes()
    {
        return $this->hasMany(AutomacaoExecucao::class);
    }
}
