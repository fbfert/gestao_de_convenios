<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Guia extends Model
{
    use Auditable, BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id', 'solicitacao_id', 'solicitacao_item_id', 'convenio_id', 'paciente_id',
        'automacao_execucao_id', 'profissional_id', 'especialidade_id', 'numero_guia', 'tipo_terapia',
        'status', 'unimed_status', 'unimed_last_checked_at', 'unimed_next_check_at',
        'sessoes_solicitadas', 'sessoes_autorizadas', 'protocolo_operadora',
        'data_solicitacao', 'data_finalizacao', 'senha', 'validade_senha', 'observacoes',
    ];

    protected $casts = [
        'data_solicitacao' => 'date',
        'data_finalizacao' => 'date',
        'validade_senha' => 'date',
        'unimed_last_checked_at' => 'datetime',
        'unimed_next_check_at' => 'datetime',
        'sessoes_solicitadas' => 'integer',
        'sessoes_autorizadas' => 'integer',
    ];

    public function solicitacao()
    {
        return $this->belongsTo(Solicitacao::class);
    }

    public function solicitacaoItem()
    {
        return $this->belongsTo(SolicitacaoItem::class);
    }

    public function automacaoExecucao()
    {
        return $this->belongsTo(AutomacaoExecucao::class);
    }

    public function ultimaAutomacaoUnimed()
    {
        return $this->hasOne(AutomacaoExecucao::class)->latestOfMany();
    }

    public function convenio()
    {
        return $this->belongsTo(Convenio::class);
    }

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

    public function antecipacoes()
    {
        return $this->hasMany(Antecipacao::class);
    }

    public function conciliacoes()
    {
        return $this->hasMany(ConciliacaoFinanceira::class);
    }
}
