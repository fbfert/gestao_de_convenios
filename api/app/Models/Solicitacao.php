<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Solicitacao extends Model
{
    use Auditable, BelongsToTenant, HasFactory;

    protected $table = 'solicitacoes';

    protected $fillable = [
        'tenant_id', 'paciente_id', 'profissional_id', 'especialidade_id',
        'convenio_id', 'medico_id', 'cid', 'status', 'solicitado_em', 'observacoes',
        'pedido_medico_path', 'pedido_medico_nome_original', 'pedido_medico_mime',
        'pedido_medico_ai_result',
    ];

    protected $casts = [
        'medico_id' => 'integer',
        'solicitado_em' => 'date',
        'pedido_medico_ai_result' => 'array',
    ];

    public function paciente()
    {
        return $this->belongsTo(Paciente::class);
    }

    public function profissional()
    {
        return $this->belongsTo(Profissional::class);
    }

    public function especialidade()
    {
        return $this->belongsTo(Especialidade::class);
    }

    public function convenio()
    {
        return $this->belongsTo(Convenio::class);
    }

    public function medico()
    {
        return $this->belongsTo(Medico::class);
    }

    public function guia()
    {
        return $this->hasOne(Guia::class);
    }

    public function itens()
    {
        return $this->hasMany(SolicitacaoItem::class);
    }

    public function documentos()
    {
        return $this->hasMany(SolicitacaoDocumento::class);
    }
}
