<?php

namespace App\Http\Controllers;

use App\Http\Requests\IgnorarAntecipacaoRequest;
use App\Http\Requests\ListarAntecipacoesRequest;
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

    public function index(ListarAntecipacoesRequest $request): AnonymousResourceCollection
    {
        $filtros = $request->validated();

        return AntecipacaoResource::collection(
            $this->service->listar(
                $filtros,
                (int) ($filtros['per_page'] ?? 0) ?: ConfiguracaoGlobal::itensPorPagina()
            )
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

    /**
     * Desfaz uma dispensa. Rota própria, e não o `DELETE` do recurso: aquele
     * apagaria também uma antecipação `gerada`, sem desfazer o item nem a
     * guia criados — foi por isso que saiu, e continua fora (405).
     */
    public function desfazer(Antecipacao $antecipacao): JsonResponse
    {
        $this->service->desfazerIgnorada($antecipacao);

        return response()->json(null, 204);
    }
}
