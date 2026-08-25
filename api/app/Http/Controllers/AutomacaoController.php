<?php

namespace App\Http\Controllers;

use App\Http\Resources\AutomacaoExecucaoResource;
use App\Jobs\ExecutarAutomacaoUnimedJob;
use App\Models\AutomacaoExecucao;
use App\Services\Automation\AutomacaoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class AutomacaoController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $tenantId = (int) $request->user()->tenant_id;
        $filtros = $request->only(['status', 'operacao', 'guia_id', 'solicitacao_item_id', 'needs_attention', 'numero_guia']);
        $ultimoStatusSql = $this->subqueryUltimoStatusDaGuia();

        $query = AutomacaoExecucao::query()
            ->where('tenant_id', $tenantId)
            ->with(['guia', 'solicitacaoItem'])
            ->selectRaw("automacao_execucoes.*, ({$ultimoStatusSql}) as guia_ultima_execucao_status")
            ->when(Arr::get($filtros, 'status'), fn ($query, $status) => $query->where('status', $status))
            ->when(Arr::get($filtros, 'operacao'), fn ($query, $operacao) => $query->where('operacao', $operacao))
            ->when(Arr::get($filtros, 'guia_id'), fn ($query, $guiaId) => $query->where('guia_id', $guiaId))
            ->when(Arr::get($filtros, 'solicitacao_item_id'), fn ($query, $itemId) => $query->where('solicitacao_item_id', $itemId))
            ->when($request->boolean('needs_attention'), fn ($query) => $query
                ->whereIn('status', ['failed', 'uncertain', 'needs_attention'])
                // Exclui execuções de guias que já se recuperaram (execução mais
                // recente da guia terminou succeeded) — falha antiga não é mais
                // atenção pendente.
                ->where(function ($q) use ($ultimoStatusSql) {
                    $q->whereNull('guia_id')->orWhereRaw("({$ultimoStatusSql}) != ?", ['succeeded']);
                }))
            ->when(Arr::get($filtros, 'numero_guia'), fn ($query, $numeroGuia) => $query->whereHas(
                'guia',
                fn ($guiaQuery) => $guiaQuery->where('numero_guia', 'like', "%{$numeroGuia}%")
            ))
            ->orderByDesc('id');

        return AutomacaoExecucaoResource::collection(
            $query->paginate((int) $request->integer('per_page', 15))
        );
    }

    public function show(AutomacaoExecucao $automacaoExecucao): AutomacaoExecucaoResource
    {
        $automacaoExecucao->setAttribute(
            'guia_ultima_execucao_status',
            $automacaoExecucao->guia_id
                ? AutomacaoExecucao::query()
                    ->where('tenant_id', $automacaoExecucao->tenant_id)
                    ->where('guia_id', $automacaoExecucao->guia_id)
                    ->orderByDesc('id')
                    ->value('status')
                : null,
        );

        return new AutomacaoExecucaoResource(
            $automacaoExecucao->load(['guia', 'solicitacaoItem', 'eventos'])
        );
    }

    /**
     * Status da execução mais recente da mesma guia (correlacionado por
     * guia_id + tenant_id) — usado tanto para expor `guia_ultima_execucao_status`
     * no resource quanto para excluir falhas já superadas do filtro de atenção.
     */
    private function subqueryUltimoStatusDaGuia(): string
    {
        return 'SELECT status FROM automacao_execucoes AS ultima_exec_guia
                WHERE ultima_exec_guia.guia_id = automacao_execucoes.guia_id
                  AND ultima_exec_guia.tenant_id = automacao_execucoes.tenant_id
                ORDER BY ultima_exec_guia.id DESC
                LIMIT 1';
    }

    public function reprocessar(
        AutomacaoExecucao $automacaoExecucao,
        AutomacaoService $automacoes,
    ): JsonResponse {
        if ($automacaoExecucao->operacao === 'gerar_guia' && $automacaoExecucao->status === 'uncertain') {
            throw ValidationException::withMessages([
                'execucao' => ['Execução incerta de geração de Guia exige confirmação idempotente antes de reprocessar.'],
            ]);
        }

        if (! in_array($automacaoExecucao->status, ['failed', 'needs_attention'], true)) {
            throw ValidationException::withMessages([
                'execucao' => ['Somente execuções com falha ou atenção operacional podem ser reprocessadas.'],
            ]);
        }

        $nova = $automacoes->enfileirar(
            $automacaoExecucao->tenant_id,
            $automacaoExecucao->operacao,
            $automacaoExecucao->solicitacaoItem,
            $automacaoExecucao->guia,
            $automacaoExecucao->payload ?? [],
            $automacaoExecucao,
        );

        ExecutarAutomacaoUnimedJob::dispatch($nova->id);

        return response()->json([
            'data' => AutomacaoExecucaoResource::make($nova->load(['guia', 'solicitacaoItem']))->resolve(),
        ], 202);
    }
}
