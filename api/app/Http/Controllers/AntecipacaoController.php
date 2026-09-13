<?php

namespace App\Http\Controllers;

use App\Http\Requests\IgnorarAntecipacaoRequest;
use App\Http\Requests\StoreAntecipacaoRequest;
use App\Http\Requests\UpdateAntecipacaoRequest;
use App\Http\Resources\AntecipacaoResource;
use App\Models\Antecipacao;
use App\Models\ConfiguracaoGlobal;
use App\Services\AntecipacaoService;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AntecipacaoController extends Controller
{
    public function __construct(
        private readonly AntecipacaoService $service
    ) {}

    public function elegiveis(Request $request): JsonResponse
    {
        $tenantId = $request->user()?->tenant_id ?? TenantContext::get();

        return response()->json([
            'data' => $this->service->listarElegiveis($tenantId),
        ]);
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        return AntecipacaoResource::collection(
            $this->service->listar($request->only(['status']), $request->integer('per_page') ?: ConfiguracaoGlobal::itensPorPagina())
        );
    }

    /** Gera de fato: cria os itens/guias de renovação na solicitação de origem. */
    public function store(StoreAntecipacaoRequest $request): JsonResponse
    {
        return (new AntecipacaoResource($this->service->criar($request->validated())))
            ->response()
            ->setStatusCode(201);
    }

    public function ignorar(IgnorarAntecipacaoRequest $request): JsonResponse
    {
        return (new AntecipacaoResource($this->service->ignorar($request->validated())))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateAntecipacaoRequest $request, Antecipacao $antecipacao): AntecipacaoResource
    {
        return new AntecipacaoResource($this->service->atualizar($antecipacao, $request->validated()));
    }

    public function destroy(Antecipacao $antecipacao): JsonResponse
    {
        $this->service->remover($antecipacao);

        return response()->json(null, 204);
    }
}
