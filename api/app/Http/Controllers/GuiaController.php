<?php

namespace App\Http\Controllers;

use App\Http\Requests\MutateGuiaStatusRequest;
use App\Http\Requests\StoreGuiaRequest;
use App\Http\Resources\GuiaResource;
use App\Models\Guia;
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
                'validade_senha_vencendo_em_dias',
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

    public function finalizar(MutateGuiaStatusRequest $request, Guia $guia): GuiaResource
    {
        return new GuiaResource($this->service->finalizar($guia, $request->all()));
    }

    public function negar(MutateGuiaStatusRequest $request, Guia $guia): GuiaResource
    {
        return new GuiaResource($this->service->negar($guia, $request->input('observacoes')));
    }
}
