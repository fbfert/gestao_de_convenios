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
        'protocolo_importacao',
    ];

    protected $casts = [
        'medico_id' => 'integer',
        'solicitado_em' => 'date',
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

    /**
     * TODAS as guias da solicitação — uma por item, desde a multi-especialidade.
     *
     * Era um `hasOne` chamado `guia()`, e sem ordenação: devolvia uma guia
     * qualquer, variando com a ordem física das linhas. Não existe "a guia da
     * solicitação" para eleger, então a relação passou a dizer a verdade. O que
     * sobrou de uso legítimo é a pergunta "esta solicitação já tem guia?", que
     * um `hasMany` responde sem inventar uma principal.
     *
     * Para exibir, use `itens[].guia`: a guia pertence ao item, e é o item que
     * dá sentido a ela (especialidade e profissional).
     */
    public function guias()
    {
        return $this->hasMany(Guia::class);
    }

    public function itens()
    {
        return $this->hasMany(SolicitacaoItem::class);
    }

    public function documentos()
    {
        return $this->hasMany(SolicitacaoDocumento::class);
    }

    /** Antecipações que tiveram esta solicitação como origem (ver App\Models\Antecipacao). */
    public function antecipacoes()
    {
        return $this->hasMany(Antecipacao::class, 'solicitacao_origem_id');
    }
}
