<?php

namespace App\Services\Automation;

use App\Exceptions\AutomationConcurrencyException;
use App\Jobs\ExecutarAutomacaoUnimedJob;
use App\Models\AutomacaoExecucao;
use App\Models\Guia;
use App\Repositories\ConvenioCredencialRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Descobre, perguntando ao portal, quais guias a clínica já finalizou na Unimed.
 *
 * Existe por causa do passivo: a automação de finalização nasceu hoje, e as
 * guias encerradas no portal à mão durante meses continuam aparecendo aqui como
 * trabalho pendente. A tela de exames finalizados responde isso guia a guia.
 *
 * O que este serviço grava é uma MARCA, não um status — ver
 * `Guia::finalizadaNaOperadora()` e a decisão 1 do design. `finalized` faria a
 * guia aceitar lançamento de sessão, que é o oposto do que se quer para uma
 * guia encerrada lá atrás sem registro nenhum neste sistema.
 */
class ConferirGuiaFinalizadaUnimedService
{
    public const OPERATION = 'conferir_guia_finalizada';

    /** Teto de guias por lote: uma sessão de portal não pode durar para sempre. */
    private const MAXIMO_POR_LOTE = 50;

    public function __construct(
        private readonly AutomacaoService $automacoes,
        private readonly ConvenioCredencialRepository $credenciais,
    ) {}

    /** Conferência de uma guia específica, a pedido do operador. */
    public function enviar(Guia $guia, bool $dispatch = true): AutomacaoExecucao
    {
        $motivos = $this->impedimentos($guia);

        if ($motivos !== []) {
            throw ValidationException::withMessages(['guia' => $motivos]);
        }

        return $this->enfileirar(
            (int) $guia->tenant_id,
            [$this->paraOWorker($guia)],
            guia: $guia,
            dispatch: $dispatch,
        );
    }

    /**
     * Conferência em lote das guias ainda não conferidas.
     *
     * Uma execução para o lote inteiro, e não uma por guia: login é o passo
     * mais lento e mais frágil do fluxo, e o passivo pode ter dezenas de guias.
     */
    /**
     * @param  bool  $incluirJaConferidas  refaz a conferência de quem já tem data
     */
    public function enviarLote(
        int $tenantId,
        bool $dispatch = true,
        bool $incluirJaConferidas = false,
    ): AutomacaoExecucao {
        $guias = $this->elegiveisParaLote($tenantId, $incluirJaConferidas);

        if ($guias->isEmpty()) {
            throw ValidationException::withMessages([
                'guias' => [
                    $incluirJaConferidas
                        ? 'Não há guia Unimed com número da operadora para conferir.'
                        : 'Não há guia para conferir: todas as guias Unimed já foram conferidas. Use "Reconferir todas" para refazer.',
                ],
            ]);
        }

        /*
         * O lote é de UM convênio.
         *
         * A credencial da Unimed é por convênio desde a change
         * `credenciais-por-convenio`, e um lote misturando convênios não teria
         * com qual login entrar. Com mais de um convênio automatizado, o
         * primeiro lote resolve um e o seguinte resolve o outro — e o operador
         * precisa saber disso, senão metade do passivo fica para trás em
         * silêncio. Daí `restantes` no payload.
         */
        $convenioId = (int) $guias->first()->convenio_id;
        $doConvenio = $guias->where('convenio_id', $convenioId)->values();
        $deOutrosConvenios = $guias->count() - $doConvenio->count();

        return $this->enfileirar(
            $tenantId,
            $doConvenio->map(fn (Guia $guia) => $this->paraOWorker($guia))->all(),
            guia: null,
            dispatch: $dispatch,
            convenioId: $convenioId,
            restantesDeOutrosConvenios: $deOutrosConvenios,
        );
    }

    /**
     * Quem entra no lote: convênio com automação Unimed, não histórica, com
     * número da operadora.
     *
     * Por padrão, só as **ainda não conferidas** — é o que resolve o passivo
     * sem repetir trabalho. Com `$incluirJaConferidas`, entram todas: é a
     * saída para um lote que correu errado, que sem isso custaria uma
     * conferência avulsa por guia para desfazer.
     *
     * Guia já `finalized` pelo fluxo normal ENTRA nos dois casos: saber que a
     * operadora concorda é informação legítima, e a marca não atrapalha nada
     * nela.
     *
     * @return Collection<int, Guia>
     */
    public function elegiveisParaLote(int $tenantId, bool $incluirJaConferidas = false): Collection
    {
        return Guia::query()
            ->where('tenant_id', $tenantId)
            ->unless($incluirJaConferidas, fn ($query) => $query->whereNull('conferida_na_operadora_em'))
            ->whereNotNull('numero_guia')
            ->naoHistorica()
            ->whereHas('convenio', fn ($query) => $query->where('connector_driver', 'unimed_rda'))
            ->orderBy('id')
            ->limit(self::MAXIMO_POR_LOTE)
            ->get()
            // O número de preenchimento do convênio manual não é número de
            // operadora: buscar por ele no portal não acharia nada.
            ->reject(fn (Guia $guia) => \App\Support\GuiaStatus::numeroEhPlaceholder($guia->numero_guia))
            ->values();
    }

    /** @return array<int, string> */
    public function impedimentos(Guia $guia): array
    {
        $guia->loadMissing('convenio');
        $motivos = [];

        if ($guia->convenio?->connector_driver !== 'unimed_rda') {
            $motivos[] = 'A guia não é de convênio com automação Unimed.';
        }

        if (blank($guia->numero_guia) || \App\Support\GuiaStatus::numeroEhPlaceholder($guia->numero_guia)) {
            $motivos[] = 'A guia precisa ter número da operadora para ser procurada no portal.';
        }

        if ($guia->ehHistorica()) {
            $motivos[] = 'A guia pertence a uma solicitação histórica e não entra em automação.';
        }

        if (! $this->credenciais->ativa((int) $guia->tenant_id, (int) $guia->convenio_id)) {
            $motivos[] = 'A credencial Unimed ativa não está configurada.';
        }

        return $motivos;
    }

    /**
     * @param  array<int, array<string, mixed>>  $guias
     */
    private function enfileirar(
        int $tenantId,
        array $guias,
        ?Guia $guia,
        bool $dispatch,
        ?int $convenioId = null,
        int $restantesDeOutrosConvenios = 0,
    ): AutomacaoExecucao {
        try {
            $parent = AutomacaoExecucao::query()
                ->where('tenant_id', $tenantId)
                ->where('operacao', self::OPERATION)
                ->when($guia !== null, fn ($query) => $query->where('guia_id', $guia->id))
                ->whereNotIn('status', AutomacaoExecucao::STATUS_ATIVOS)
                ->latest('id')
                ->first();

            $execucao = $this->automacoes->enfileirar(
                $tenantId,
                self::OPERATION,
                guia: $guia,
                // `convenio_id` no payload é o que permite achar a credencial
                // quando a execução não pertence a uma guia — é assim que
                // ConvenioCredencialRepository::convenioDaExecucao() resolve o
                // lote, que não tem `guia_id`.
                payload: array_filter([
                    'guias' => $guias,
                    'convenio_id' => $convenioId ?? $guia?->convenio_id,
                    // Guias elegíveis que ficaram de fora por serem de outro
                    // convênio. A tela precisa disso para dizer que ainda
                    // falta rodar — ver a spec.
                    'restantes_de_outros_convenios' => $restantesDeOutrosConvenios ?: null,
                ], fn ($valor) => $valor !== null),
                parent: $parent,
            );
        } catch (AutomationConcurrencyException $exception) {
            throw ValidationException::withMessages([
                'guia' => ["Já existe execução Unimed ativa para este tenant ({$exception->execucaoId})."],
            ]);
        }

        if ($dispatch) {
            ExecutarAutomacaoUnimedJob::dispatch($execucao->id);
        }

        return $execucao;
    }

    /** @return array<string, mixed> */
    private function paraOWorker(Guia $guia): array
    {
        return ['guia_id' => $guia->id, 'numero_guia' => $guia->numero_guia];
    }

    public function payloadParaWorker(AutomacaoExecucao $execucao): array
    {
        $credential = $this->credenciais->ativaParaExecucao($execucao);

        return ($execucao->payload ?? []) + [
            'credential' => [
                'login' => $credential->campo('login'),
                'password' => $credential->campo('password'),
                'base_url' => $credential->campo('base_url'),
            ],
        ];
    }

    /**
     * Grava o desfecho de cada guia.
     *
     * Três desfechos, três efeitos:
     *
     * - `finalizada` ....... grava as duas datas
     * - `nao_finalizada` ... grava só a da conferência, e RETIRA a marca se
     *                        existia: ela afirma o que o portal diz agora, não
     *                        o que disse uma vez
     * - `falhou` ........... não toca em nada. Uma guia que não pôde ser
     *                        conferida não teve resposta, e escrever "conferida
     *                        em X" seria mentir sobre isso
     */
    public function aplicarResultado(AutomacaoExecucao $execucao, array $resultado): AutomacaoExecucao
    {
        $execucao = $this->automacoes->concluir($execucao, $resultado);

        if (($resultado['status'] ?? null) !== 'succeeded') {
            return $execucao->refresh();
        }

        $agora = now();

        foreach ($resultado['results'] ?? [] as $item) {
            $guia = Guia::query()->find($item['guia_id'] ?? null);

            if (! $guia) {
                continue;
            }

            match ($item['desfecho'] ?? null) {
                'finalizada' => $guia->forceFill([
                    'finalizada_na_operadora_em' => $agora,
                    'conferida_na_operadora_em' => $agora,
                ])->save(),
                'nao_finalizada' => $guia->forceFill([
                    'finalizada_na_operadora_em' => null,
                    'conferida_na_operadora_em' => $agora,
                ])->save(),
                default => null,
            };
        }

        $this->automacoes->registrarEvento($execucao, 'conferencia_concluida', $execucao->status, [
            'resumo' => $resultado['resumo'] ?? null,
        ]);

        return $execucao->refresh();
    }
}
