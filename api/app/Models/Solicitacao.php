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

    /**
     * N-pra-N desde 25/08/2026 (antes era `cid_id`, 1-pra-1) — uma
     * solicitação pode citar mais de um CID (comorbidades). Não pode se
     * chamar `cid()`: a coluna legada `cid` (texto livre, ver migration
     * antiga) já existe em `$attributes`, e o Eloquent sempre prioriza um
     * atributo hidratado sobre um método de relação de mesmo nome —
     * `$model->cid` nunca chegaria a resolver esta relação.
     */
    public function cidCadastros()
    {
        return $this->belongsToMany(Cid::class, 'cid_solicitacao');
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
