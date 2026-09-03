<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\BelongsToTenant;
use App\Support\GuiaStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Guia extends Model
{
    use Auditable, BelongsToTenant, HasFactory;

    /** Nome placeholder gravado em especialidade/profissional quando o dado real ainda não foi definido. */
    public const NOME_A_DEFINIR = 'A DEFINIR';

    protected $fillable = [
        'tenant_id', 'solicitacao_id', 'solicitacao_item_id', 'convenio_id', 'paciente_id',
        'automacao_execucao_id', 'profissional_id', 'especialidade_id', 'numero_guia', 'tipo_terapia',
        'status', 'unimed_status', 'unimed_last_checked_at', 'unimed_next_check_at',
        'unimed_senha_validade_next_check_at',
        'sessoes_solicitadas', 'sessoes_autorizadas', 'protocolo_operadora',
        'data_solicitacao', 'data_finalizacao', 'senha', 'validade_senha', 'observacoes',
        'alerta_negacao_ocultado_em',
    ];

    protected $casts = [
        'data_solicitacao' => 'date',
        'data_finalizacao' => 'date',
        'validade_senha' => 'date',
        'unimed_last_checked_at' => 'datetime',
        'unimed_next_check_at' => 'datetime',
        'unimed_senha_validade_next_check_at' => 'datetime',
        'sessoes_solicitadas' => 'integer',
        'sessoes_autorizadas' => 'integer',
        'alerta_negacao_ocultado_em' => 'datetime',
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

    /** Exclui guias com Especialidade ou Profissional ainda "A DEFINIR". */
    public function scopeComDadosDefinidos($query)
    {
        return $query
            ->whereDoesntHave('especialidade', fn ($q) => $q->whereRaw('UPPER(nome) = ?', [self::NOME_A_DEFINIR]))
            ->whereDoesntHave('profissional', fn ($q) => $q->whereRaw('UPPER(nome) = ?', [self::NOME_A_DEFINIR]));
    }

    /** Só guias com Especialidade e/ou Profissional ainda "A DEFINIR". */
    public function scopeComDadosADefinir($query)
    {
        return $query->where(function ($query) {
            $query->whereHas('especialidade', fn ($q) => $q->whereRaw('UPPER(nome) = ?', [self::NOME_A_DEFINIR]))
                ->orWhereHas('profissional', fn ($q) => $q->whereRaw('UPPER(nome) = ?', [self::NOME_A_DEFINIR]));
        });
    }

    public function temDadosADefinir(): bool
    {
        $this->loadMissing(['especialidade', 'profissional']);

        return mb_strtoupper((string) $this->especialidade?->nome) === self::NOME_A_DEFINIR
            || mb_strtoupper((string) $this->profissional?->nome) === self::NOME_A_DEFINIR;
    }

    /**
     * Exclui guias cuja Solicitação de origem é "histórico" (rastro de guia
     * migrada, reconstruído depois — ver App\Services\SolicitacaoService).
     * Independente de A DEFINIR de propósito: uma guia histórica continua
     * fora da automação mesmo depois de Especialidade/Profissional serem
     * corrigidos, porque o motivo de excluir é ela ser um registro antigo,
     * não faltar dado.
     */
    public function scopeNaoHistorica($query)
    {
        return $query->whereDoesntHave(
            'solicitacaoItem.solicitacao',
            fn ($q) => $q->where('status', 'historico'),
        );
    }

    public function ehHistorica(): bool
    {
        $this->loadMissing('solicitacaoItem.solicitacao');

        return $this->solicitacaoItem?->solicitacao?->status === 'historico';
    }

    /**
     * Exclui guias cujo próprio `status` já é uma variante "histórico_*"
     * (ex.: histórico_denied) — usado pra listagem/exibição padrão. Distinto
     * de scopeNaoHistorica(): aquele olha a Solicitação de origem (pra
     * automação); este olha o status da própria guia (pra tela), que é onde
     * o resultado real (aprovado/negado/...) fica guardado depois do
     * prefixo — ver App\Support\GuiaStatus::paraHistorico().
     */
    public function scopeSemStatusHistorico($query)
    {
        return $query->whereNotIn('status', GuiaStatus::ALL_HISTORICO);
    }

    /** Só guias com status "histórico_*". */
    public function scopeComStatusHistorico($query)
    {
        return $query->whereIn('status', GuiaStatus::ALL_HISTORICO);
    }
}
