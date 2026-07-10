<?php

namespace App\Http\Controllers;

use App\Http\Requests\ListConciliacaoRequest;
use App\Http\Resources\ConciliacaoFinanceiraResource;
use App\Models\ConciliacaoFinanceira;
use App\Models\Guia;
use App\Services\ConciliacaoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ConciliacaoController extends Controller
{
    public function __construct(
        private readonly ConciliacaoService $service
    ) {
    }

    public function index(ListConciliacaoRequest $request): AnonymousResourceCollection
    {
        return ConciliacaoFinanceiraResource::collection(
            $this->service->listar($request->validated(), (int) $request->integer('per_page', 15))
        );
    }

    public function store(Guia $guia): JsonResponse
    {
        return (new ConciliacaoFinanceiraResource($this->service->gerarParaGuia($guia)))
            ->response()
            ->setStatusCode(201);
    }

    public function marcarConferido(ConciliacaoFinanceira $conciliacao): ConciliacaoFinanceiraResource
    {
        return new ConciliacaoFinanceiraResource($this->service->marcarConferido($conciliacao));
    }

    public function marcarPago(ConciliacaoFinanceira $conciliacao): ConciliacaoFinanceiraResource
    {
        return new ConciliacaoFinanceiraResource($this->service->marcarPago($conciliacao));
    }
}
