<?php

namespace App\Http\Controllers;

use App\Http\Requests\MutateGuiaStatusRequest;
use App\Http\Requests\StoreGuiaRequest;
use App\Http\Requests\UpdateGuiaRequest;
use App\Http\Resources\GuiaResource;
use App\Models\Guia;
use App\Services\Automation\CapturarSenhaValidadeUnimedService;
use App\Services\Automation\ConsultarStatusUnimedService;
use App\Services\GuiaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class GuiaController extends Controller
{
    public function __construct(
        private readonly GuiaService $service
    ) {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        return GuiaResource::collection(
            $this->service->listar($request->only([
                'status',
                'convenio_id',
                'paciente_id',
                'profissional_id',
                'paciente_nome',
                'validade_senha_vencendo_em_dias',
                'alerta_negacao_pendente',
                'mostrar_a_definir',
            ]), (int) $request->integer('per_page', 15))
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

    public function negar(MutateGuiaStatusRequest $request, Guia $guia): GuiaResource
    {
        return new GuiaResource($this->service->negar($guia, $request->input('observacoes')));
    }

    public function ocultarAlertaNegacao(Guia $guia): GuiaResource
    {
        return new GuiaResource($this->service->ocultarAlertaNegacao($guia));
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
}
