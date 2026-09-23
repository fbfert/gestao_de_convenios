<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\BelongsToTenant;
use App\Scopes\TenantScope;
use App\Support\GuiaStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class Guia extends Model
{
    use Auditable, BelongsToTenant, HasFactory;

    /** Nome placeholder gravado em especialidade/profissional quando o dado real ainda não foi definido. */
    public const NOME_A_DEFINIR = 'A DEFINIR';

    /**
     * Contador de permissão para escrever `status`.
     *
     * Contador e não booleano: `registrarTransicao` roda dentro de transação e
     * pode ser chamado de um fluxo que já está com a permissão aberta — um
     * booleano seria fechado pelo `finally` do interno e deixaria o externo sem
     * permissão pelo resto da operação.
     */
    private static int $transicoesPermitidas = 0;

    /**
     * Abre a permissão de escrita de status durante o callback.
     *
     * Uso exclusivo de GuiaService::registrarTransicao. Está aqui, e não lá, só
     * porque o contador precisa viver junto do observer que o consulta.
     */
    public static function permitindoTransicao(callable $callback): mixed
    {
        self::$transicoesPermitidas++;

        try {
            return $callback();
        } finally {
            self::$transicoesPermitidas--;
        }
    }

    /**
     * A trava, e o registro automático da criação.
     *
     * A trava vale para MUDANÇA de status de guia já existente, que é onde mora
     * o risco: uma transição feita por fora não deixa rastro nenhum e o buraco
     * na série só aparece meses depois. Reprova pelo EFEITO (`isDirty`), e não
     * pelo formato do código — uma varredura estática pegaria o padrão conhecido
     * e deixaria passar um jeito novo de escrever.
     *
     * Criação NÃO é recusada, é registrada. O motivo é que na criação não há
     * status anterior a perder: a primeira linha do histórico é derivável do
     * próprio registro que está nascendo, então recusar só obrigaria todo
     * seeder, fábrica e teste a passar pelo serviço sem ganhar fidelidade
     * nenhuma. Quando a criação vem de `registrarTransicao`, o contador está
     * aberto e este observer se cala — é lá que a linha nasce, com origem e
     * usuário de verdade.
     *
     * Furo conhecido e registrado no design.md: `Guia::query()->update([...])`
     * não dispara eventos de model e escapa daqui. Hoje não existe nenhuma
     * ocorrência dessas no repositório.
     */
    protected static function booted(): void
    {
        static::updating(function (Guia $guia) {
            if ($guia->isDirty('status') && self::$transicoesPermitidas === 0) {
                throw new \RuntimeException(
                    'Status de guia só pode ser alterado por GuiaService::registrarTransicao(). '
                    .'Ver openspec/changes/guia-status-historico/design.md.'
                );
            }
        });

        static::created(function (Guia $guia) {
            if (self::$transicoesPermitidas > 0 || blank($guia->status)) {
                return; // registrarTransicao grava a linha rica logo em seguida
            }

            GuiaStatusHistorico::query()->create([
                'tenant_id' => $guia->tenant_id,
                'guia_id' => $guia->id,
                'de' => null,
                'para' => $guia->status,
                'ocorrido_em' => $guia->created_at ?? now(),
                'user_id' => null,
                'origem' => GuiaStatusHistorico::ORIGEM_MANUAL,
                'motivo' => null,
            ]);
        });
    }

    protected $fillable = [
        'tenant_id', 'solicitacao_id', 'solicitacao_item_id', 'convenio_id', 'paciente_id',
        'automacao_execucao_id', 'profissional_id', 'especialidade_id', 'numero_guia', 'tipo_terapia',
        'status', 'unimed_status', 'unimed_last_checked_at', 'unimed_next_check_at',
        'unimed_senha_validade_next_check_at',
        'sessoes_solicitadas', 'sessoes_autorizadas', 'protocolo_operadora',
        'data_solicitacao', 'data_finalizacao', 'senha', 'validade_senha', 'observacoes',
        'alerta_negacao_ocultado_em', 'alerta_restricao_ocultado_em', 'alerta_antecipacao_ocultado_em',
        'antecipacao_data_alvo',
        // A operadora já tinha esta guia por finalizada antes de a automação
        // existir. Marca própria, que NÃO mexe no status — ver
        // ConferirGuiaFinalizadaUnimedService.
        'finalizada_na_operadora_em', 'conferida_na_operadora_em',
        // Carimbos escritos so por GuiaService::registrarTransicao, junto com o
        // historico. Preenchiveis para o backfill conseguir gravar.
        'negada_em', 'aprovada_em',
    ];

    protected $casts = [
        'data_solicitacao' => 'date',
        'data_finalizacao' => 'date',
        'validade_senha' => 'date',
        'antecipacao_data_alvo' => 'date',
        'unimed_last_checked_at' => 'datetime',
        'unimed_next_check_at' => 'datetime',
        'unimed_senha_validade_next_check_at' => 'datetime',
        'sessoes_solicitadas' => 'integer',
        'sessoes_autorizadas' => 'integer',
        'alerta_negacao_ocultado_em' => 'datetime',
        'alerta_restricao_ocultado_em' => 'datetime',
        'alerta_antecipacao_ocultado_em' => 'datetime',
        'negada_em' => 'datetime',
        'aprovada_em' => 'datetime',
        'finalizada_na_operadora_em' => 'datetime',
        'conferida_na_operadora_em' => 'datetime',
    ];

    /**
     * A operadora deu esta guia por finalizada.
     *
     * Não confundir com `status === FINALIZED`: aquilo é o ciclo da guia neste
     * sistema, com as sessões lançadas aqui; isto é o que o portal respondeu
     * sobre uma guia que talvez nunca tenha passado por esse ciclo. Uma guia
     * pode ter uma das duas coisas, as duas, ou nenhuma.
     */
    public function finalizadaNaOperadora(): bool
    {
        return $this->finalizada_na_operadora_em !== null;
    }

    public function statusHistorico()
    {
        return $this->hasMany(GuiaStatusHistorico::class)->orderBy('ocorrido_em');
    }

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

    public function lancamentos()
    {
        return $this->hasMany(Lancamento::class);
    }

    /**
     * Quantas sessões ainda cabem nesta guia — cota ao vivo, sem balde
     * separado (substituiu o antigo Antecipacao::qtd_autorizada/qtd_utilizada).
     * `sessoes_autorizadas` é o que a operadora realmente liberou; na
     * ausência dele (convênio manual, ainda não capturado) cai para
     * `sessoes_solicitadas`.
     */
    public function sessoesDisponiveis(): int
    {
        $total = $this->sessoes_autorizadas ?? $this->sessoes_solicitadas ?? 0;

        return max(0, $total - $this->lancamentos()->count());
    }

    /** Guia em condição de receber lançamento de sessão — já aprovada, sem esperar Finalizar. */
    public function aceitaLancamento(): bool
    {
        return in_array($this->status, [GuiaStatus::APPROVED, GuiaStatus::FINALIZED], true);
    }

    /**
     * Data a partir da qual vale a pena avisar que é hora de gerar o próximo
     * ciclo (ver App\Services\Alertas\Regras\AntecipacaoDevida). Três níveis
     * de override, do mais para o menos específico:
     *
     *   1. `antecipacao_data_alvo` — data manual desta guia, sobrescreve tudo.
     *   2. `convenio.antecipacao_dias`/`antecipacao_referencia` — override do convênio.
     *   3. `configuracoes_globais.antecipacao_dias`/`antecipacao_referencia` — padrão do tenant.
     *
     * `validade_senha` e `data_finalizacao` são vencimentos: conta-se pra
     * trás (antecedência antes de vencer). `data_solicitacao` ("guia
     * criada") é um início: conta-se pra frente (dias depois de emitida).
     *
     * Nulo quando a data de referência escolhida ainda não está preenchida
     * nesta guia — nesse caso não dá pra calcular, e o avaliador
     * simplesmente pula a guia.
     */
    public function antecipacaoDataAlvo(): ?Carbon
    {
        if ($this->antecipacao_data_alvo) {
            return $this->antecipacao_data_alvo;
        }

        $convenio = $this->relationLoaded('convenio') ? $this->convenio : $this->convenio()->first();

        $dias = $convenio?->antecipacao_dias
            ?? ConfiguracaoGlobal::doTenant($this->tenant_id)->antecipacao_dias;
        $referencia = $convenio?->antecipacao_referencia
            ?? ConfiguracaoGlobal::doTenant($this->tenant_id)->antecipacao_referencia;

        $dataBase = match ($referencia) {
            'data_finalizacao' => $this->data_finalizacao,
            'data_solicitacao' => $this->data_solicitacao,
            default => $this->validade_senha,
        };

        if (! $dataBase) {
            return null;
        }

        return $referencia === 'data_solicitacao'
            ? $dataBase->copy()->addDays($dias)
            : $dataBase->copy()->subDays($dias);
    }

    /**
     * Guias com antecipação devida hoje ou já atrasada — a mesma lista que
     * alimenta o alerta App\Services\Alertas\Regras\AntecipacaoDevida, e
     * também a fila "Elegíveis" de App\Services\AntecipacaoService. Um único
     * lugar pras duas leituras não divergirem.
     */
    public static function elegiveisParaAntecipacao(int $tenantId)
    {
        $hoje = today();

        return static::query()
            ->withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tenantId)
            ->whereIn('status', [GuiaStatus::APPROVED, GuiaStatus::FINALIZED])
            ->whereNull('alerta_antecipacao_ocultado_em')
            ->naoHistorica()
            ->with([
                'paciente',
                'convenio',
                'solicitacao.medico',
                'solicitacao.cidCadastros',
                'solicitacao.itens.especialidade',
                'solicitacao.itens.profissional',
            ])
            ->get()
            ->filter(function (self $guia) use ($hoje) {
                $dataAlvo = $guia->antecipacaoDataAlvo();

                return $dataAlvo !== null && ! $hoje->lt($dataAlvo);
            })
            ->values();
    }

    /**
     * Mesma elegibilidade de elegiveisParaAntecipacao(), só a contagem — usada
     * pelo card de Guias do dashboard, que não precisa dos dados de exibição
     * (paciente/solicitação/itens). Sem esses `with()` pesados, só a relation
     * mínima que antecipacaoDataAlvo() consulta.
     */
    public static function countElegiveisParaAntecipacao(int $tenantId): int
    {
        return static::elegiveisParaAntecipacaoLeve($tenantId)->count();
    }

    /**
     * Solicitações de origem das guias elegíveis — o que a fila "Elegíveis"
     * agrupa. Mesma leitura leve de countElegiveisParaAntecipacao(), para o
     * bloco do dashboard contar o que a tela de Antecipações mostra.
     *
     * @return \Illuminate\Support\Collection<int, int>
     */
    public static function solicitacaoIdsElegiveisParaAntecipacao(int $tenantId)
    {
        return static::elegiveisParaAntecipacaoLeve($tenantId)
            ->pluck('solicitacao_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
    }

    private static function elegiveisParaAntecipacaoLeve(int $tenantId)
    {
        $hoje = today();

        return static::query()
            ->withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tenantId)
            ->whereIn('status', [GuiaStatus::APPROVED, GuiaStatus::FINALIZED])
            ->whereNull('alerta_antecipacao_ocultado_em')
            ->naoHistorica()
            ->select(['id', 'tenant_id', 'solicitacao_id', 'convenio_id', 'antecipacao_data_alvo', 'data_finalizacao', 'data_solicitacao', 'validade_senha'])
            ->with('convenio:id,antecipacao_dias,antecipacao_referencia')
            ->get()
            ->filter(function (self $guia) use ($hoje) {
                $dataAlvo = $guia->antecipacaoDataAlvo();

                return $dataAlvo !== null && ! $hoje->lt($dataAlvo);
            });
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
