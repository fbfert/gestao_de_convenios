<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Paciente extends Model
{
    use Auditable, BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id', 'nome', 'cpf', 'data_nascimento', 'carteirinha', 'validade_carteirinha',
        'convenio_id', 'telefone', 'clinica_agil_id', 'ativo',
        'clinica_id', 'sincronizado_em', 'clinica_status',
        'mesclado_em_id', 'mesclado_em',
    ];

    protected $casts = [
        'ativo' => 'boolean',
        'data_nascimento' => 'date',
        'validade_carteirinha' => 'date',
        'sincronizado_em' => 'datetime',
        'mesclado_em' => 'datetime',
    ];

    public function carteirinhaVencida(): bool
    {
        return $this->validade_carteirinha !== null && $this->validade_carteirinha->isPast();
    }

    /** Perdedor de uma unificação (ver PacienteMergeService) — nunca é apagado, só desativado e apontado pro vencedor. */
    public function mesclado(): bool
    {
        return $this->mesclado_em_id !== null;
    }

    public function mescladoEm()
    {
        return $this->belongsTo(Paciente::class, 'mesclado_em_id');
    }

    public function convenio()
    {
        return $this->belongsTo(Convenio::class);
    }

    public function telefones()
    {
        return $this->hasMany(PacienteTelefone::class)->orderBy('ordem')->orderBy('id');
    }

    public function documentos()
    {
        return $this->hasMany(PacienteDocumento::class);
    }

    /** Pasta do paciente: pedido médico, laudo médico, plano individualizado, etc. */
    public function arquivos()
    {
        return $this->hasMany(PacienteArquivo::class);
    }

    public function solicitacoes()
    {
        return $this->hasMany(Solicitacao::class);
    }
}
