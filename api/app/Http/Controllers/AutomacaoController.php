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
        $filtros = $request->only(['status', 'operacao', 'guia_id', 'solicitacao_item_id', 'needs_attention']);

        $query = AutomacaoExecucao::query()
            ->where('tenant_id', $tenantId)
            ->with(['guia', 'solicitacaoItem'])
            ->when(Arr::get($filtros, 'status'), fn ($query, $status) => $query->where('status', $status))
            ->when(Arr::get($filtros, 'operacao'), fn ($query, $operacao) => $query->where('operacao', $operacao))
            ->when(Arr::get($filtros, 'guia_id'), fn ($query, $guiaId) => $query->where('guia_id', $guiaId))
            ->when(Arr::get($filtros, 'solicitacao_item_id'), fn ($query, $itemId) => $query->where('solicitacao_item_id', $itemId))
            ->when($request->boolean('needs_attention'), fn ($query) => $query->whereIn('status', ['failed', 'uncertain', 'needs_attention']))
            ->orderByDesc('id');

        return AutomacaoExecucaoResource::collection(
            $query->paginate((int) $request->integer('per_page', 15))
        );
    }

    public function show(AutomacaoExecucao $automacaoExecucao): AutomacaoExecucaoResource
    {
        return new AutomacaoExecucaoResource(
            $automacaoExecucao->load(['guia', 'solicitacaoItem', 'eventos'])
        );
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
