<?php

namespace App\Http\Controllers;

use App\Http\Requests\MutateSolicitacaoStatusRequest;
use App\Http\Requests\StoreSolicitacaoRequest;
use App\Http\Resources\SolicitacaoResource;
use App\Models\Solicitacao;
use App\Services\SolicitacaoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SolicitacaoController extends Controller
{
    public function __construct(
        private readonly SolicitacaoService $service
    ) {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        return SolicitacaoResource::collection(
            $this->service->listar($request->only(['status', 'convenio_id', 'medico_id']), (int) $request->integer('per_page', 15))
        );
    }

    public function store(StoreSolicitacaoRequest $request): JsonResponse
    {
        return (new SolicitacaoResource($this->service->criar($request->validated())->load(['paciente', 'profissional', 'especialidade', 'convenio', 'medico', 'guia'])))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Solicitacao $solicitacao): SolicitacaoResource
    {
        return new SolicitacaoResource($solicitacao->load(['paciente', 'profissional', 'especialidade', 'convenio', 'medico', 'guia.paciente', 'guia.convenio', 'guia.profissional', 'guia.especialidade', 'guia.antecipacoes', 'guia.conciliacoes']));
    }

    public function aprovar(MutateSolicitacaoStatusRequest $request, Solicitacao $solicitacao): SolicitacaoResource
    {
        return new SolicitacaoResource($this->service->aprovar($solicitacao)->load(['paciente', 'profissional', 'especialidade', 'convenio', 'medico', 'guia']));
    }

    public function negar(MutateSolicitacaoStatusRequest $request, Solicitacao $solicitacao): SolicitacaoResource
    {
        return new SolicitacaoResource($this->service->negar($solicitacao)->load(['paciente', 'profissional', 'especialidade', 'convenio', 'medico', 'guia']));
    }
}
