<?php

namespace App\Http\Controllers;

use App\Http\Requests\MutateGuiaStatusRequest;
use App\Http\Requests\StoreGuiaRequest;
use App\Http\Requests\UpdateGuiaRequest;
use App\Http\Resources\GuiaResource;
use App\Models\ConfiguracaoGlobal;
use App\Models\Guia;
use App\Services\Automation\CapturarSenhaValidadeUnimedService;
use App\Services\Automation\ConferirGuiaFinalizadaUnimedService;
use App\Services\Automation\ConsultarStatusUnimedService;
use App\Services\Automation\FinalizarGuiaPreVoo;
use App\Services\Automation\FinalizarGuiaUnimedService;
use App\Services\GuiaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class GuiaController extends Controller
{
    public function __construct(
        private readonly GuiaService $service
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        return GuiaResource::collection(
            $this->service->listar($request->only([
                'status',
                'convenio_id',
                'paciente_id',
                'profissional_id',
                'paciente_nome',
                'numero_guia',
                'busca',
                'validade_senha_vencendo_em_dias',
                'alerta_negacao_pendente',
                'alerta_restricao_pendente',
                'mostrar_a_definir',
                'mostrar_historico',
                'disponivel_para_lancamento',
                // Nomes usados pelas linhas do card do dashboard. São contrato
                // com quem cola o link no chat, então dizem o que a pessoa quer
                // ("pendente", "senha vencendo") e não como o filtro funciona por
                // dentro; a tradução para os filtros internos é logo abaixo.
                'pendente',
                'senha_vencendo',
                'sessoes_em_conflito',
                'finalizada_na_operadora',
            ]), $request->integer('per_page') ?: ConfiguracaoGlobal::itensPorPagina())
        );
    }

    public function store(StoreGuiaRequest $request): JsonResponse
    {
        return (new GuiaResource($this->service->criar($request->validated())))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Guia $guia): GuiaResource
    {
        return new GuiaResource($this->service->buscar($guia->id));
    }

    public function update(UpdateGuiaRequest $request, Guia $guia): GuiaResource
    {
        return new GuiaResource($this->service->atualizar($guia, $request->validated()));
    }

    public function finalizar(MutateGuiaStatusRequest $request, Guia $guia): GuiaResource
    {
        return new GuiaResource($this->service->finalizar($guia, $request->validated()));
    }

    public function aprovar(Guia $guia): GuiaResource
    {
        return new GuiaResource($this->service->aprovar($guia));
    }

    public function negar(MutateGuiaStatusRequest $request, Guia $guia): GuiaResource
    {
        return new GuiaResource($this->service->negar($guia, $request->input('observacoes')));
    }

    public function ocultarAlertaNegacao(Guia $guia): GuiaResource
    {
        return new GuiaResource($this->service->ocultarAlertaNegacao($guia));
    }

    public function ocultarAlertaRestricao(Guia $guia): GuiaResource
    {
        return new GuiaResource($this->service->ocultarAlertaRestricao($guia));
    }

    public function ocultarAlertaAntecipacao(Guia $guia): GuiaResource
    {
        return new GuiaResource($this->service->ocultarAlertaAntecipacao($guia));
    }

    public function consultarUnimed(Guia $guia, ConsultarStatusUnimedService $consultarStatus): JsonResponse
    {
        $execucao = $consultarStatus->enviar($guia);

        return response()->json([
            'data' => [
                'id' => $execucao->id,
                'status' => $execucao->status,
                'operacao' => $execucao->operacao,
                'guia_id' => $execucao->guia_id,
                'queued_at' => $execucao->queued_at?->toISOString(),
            ],
        ], 202);
    }

    public function buscarSenhaValidadeUnimed(Guia $guia, CapturarSenhaValidadeUnimedService $capturarSenhaValidade): JsonResponse
    {
        $execucao = $capturarSenhaValidade->enviar($guia);

        return response()->json([
            'data' => [
                'id' => $execucao->id,
                'status' => $execucao->status,
                'operacao' => $execucao->operacao,
                'guia_id' => $execucao->guia_id,
                'queued_at' => $execucao->queued_at?->toISOString(),
            ],
        ], 202);
    }

    /**
     * Botão manual pra guias presas no bug de 22/09/2026: senha e validade já
     * capturadas, mas sessões nunca vieram. Reusa a mesma tela de execução do
     * SP/SADT via CapturarSenhaValidadeUnimedService::enviarRecuperacaoSessoes.
     */
    public function recuperarSessoesUnimed(Guia $guia, CapturarSenhaValidadeUnimedService $capturarSenhaValidade): JsonResponse
    {
        $execucao = $capturarSenhaValidade->enviarRecuperacaoSessoes($guia);

        return response()->json([
            'data' => [
                'id' => $execucao->id,
                'status' => $execucao->status,
                'operacao' => $execucao->operacao,
                'guia_id' => $execucao->guia_id,
                'queued_at' => $execucao->queued_at?->toISOString(),
            ],
        ], 202);
    }

    /**
     * Pergunta ao portal se esta guia já está entre os exames finalizados.
     *
     * Não altera nada lá: é uma consulta. O que ela produz aqui é a marca
     * "finalizada na operadora", que convive com o status em vez de substituí-lo.
     */
    public function conferirFinalizadaUnimed(Guia $guia, ConferirGuiaFinalizadaUnimedService $conferir): JsonResponse
    {
        return $this->respostaDaExecucao($conferir->enviar($guia));
    }

    /**
     * A mesma conferência, para as guias Unimed ainda não conferidas.
     *
     * `incluir_ja_conferidas` refaz todas: é a saída para um lote que correu
     * errado (filtro do portal não limpo, tela diferente da esperada), que sem
     * isso custaria uma conferência avulsa por guia para desfazer.
     */
    public function conferirFinalizadasUnimedEmLote(Request $request, ConferirGuiaFinalizadaUnimedService $conferir): JsonResponse
    {
        $dados = $request->validate([
            'incluir_ja_conferidas' => ['sometimes', 'boolean'],
        ]);

        return $this->respostaDaExecucao(
            $conferir->enviarLote(
                (int) $request->user()->tenant_id,
                incluirJaConferidas: (bool) ($dados['incluir_ja_conferidas'] ?? false),
            )
        );
    }

    private function respostaDaExecucao(\App\Models\AutomacaoExecucao $execucao): JsonResponse
    {
        return response()->json([
            'data' => [
                'id' => $execucao->id,
                'status' => $execucao->status,
                'operacao' => $execucao->operacao,
                'guia_id' => $execucao->guia_id,
                'queued_at' => $execucao->queued_at?->toISOString(),
                'total_guias' => count($execucao->payload['guias'] ?? []),
                // O lote cobre UM convênio (a credencial é por convênio); a
                // tela avisa quando sobram guias de outro.
                'convenio' => $execucao->payload['convenio_id'] ?? null
                    ? \App\Models\Convenio::query()->find($execucao->payload['convenio_id'])?->nome
                    : null,
                'restantes_de_outros_convenios' => (int) ($execucao->payload['restantes_de_outros_convenios'] ?? 0),
            ],
        ], 202);
    }

    /**
     * O que a tela precisa saber antes de oferecer a finalização.
     *
     * Só leitura: é daqui que saem os diálogos de decisão (finalizar com menos
     * sessões, cortar no autorizado, finalizar sem folha) e a lista de
     * conflitos que impedem o envio.
     */
    public function preVooFinalizarUnimed(Guia $guia, FinalizarGuiaPreVoo $preVoo): JsonResponse
    {
        return response()->json(['data' => $preVoo->avaliar($guia)]);
    }

    public function finalizarUnimed(Request $request, Guia $guia, FinalizarGuiaUnimedService $finalizarGuia): JsonResponse
    {
        $confirmacoes = $request->validate([
            FinalizarGuiaPreVoo::DECISAO_MENOS_SESSOES => ['sometimes', 'boolean'],
            FinalizarGuiaPreVoo::DECISAO_LIMITAR_AO_AUTORIZADO => ['sometimes', 'boolean'],
            FinalizarGuiaPreVoo::DECISAO_SEM_ANEXO => ['sometimes', 'boolean'],
        ]);

        $execucao = $finalizarGuia->enviar($guia, array_map(
            static fn ($valor) => (bool) $valor,
            $confirmacoes,
        ));

        return response()->json([
            'data' => [
                'id' => $execucao->id,
                'status' => $execucao->status,
                'operacao' => $execucao->operacao,
                'guia_id' => $execucao->guia_id,
                'queued_at' => $execucao->queued_at?->toISOString(),
                'simulado' => (bool) ($execucao->payload['simular'] ?? false),
            ],
        ], 202);
    }
}
