<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * "Pasta do paciente": um documento do paciente, independente de solicitação.
 * Uma solicitação se vincula a ele via SolicitacaoDocumento — o mesmo arquivo
 * pode servir a mais de uma solicitação (ver docs/pasta-do-paciente.md).
 *
 * Não confundir com PacienteDocumento (carteirinha, expira e é expurgada).
 */
class PacienteArquivo extends Model
{
    use Auditable, BelongsToTenant, HasFactory;

    /** Anexos que valem para a solicitação inteira. */
    public const TIPOS_DA_SOLICITACAO = ['pedido_medico', 'laudo_medico'];

    /** Anexos que existem por especialidade, ou seja, por item da solicitação. */
    public const TIPOS_POR_ITEM = ['plano_individualizado', 'relatorio_evolucao'];

    public const TIPOS = [
        'pedido_medico',
        'laudo_medico',
        'plano_individualizado',
        'relatorio_evolucao',
    ];

    protected $table = 'paciente_arquivos';

    protected $fillable = [
        'tenant_id',
        'paciente_id',
        'tipo',
        'nome_original',
        'mime',
        'path',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function paciente()
    {
        return $this->belongsTo(Paciente::class);
    }

    public function vinculos()
    {
        return $this->hasMany(SolicitacaoDocumento::class);
    }
}
