<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreLancamentoRequest;
use App\Http\Resources\LancamentoResource;
use App\Models\Antecipacao;
use App\Models\Profissional;
use App\Services\LancamentoService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class LancamentoController extends Controller
{
    public function __construct(
        private readonly LancamentoService $service
    ) {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        return LancamentoResource::collection(
            $this->service->listar($request->only(['profissional_id', 'data_sessao']), (int) $request->integer('per_page', 15))
        );
    }

    public function store(StoreLancamentoRequest $request, Antecipacao $antecipacao): JsonResponse
    {
        $dados = $request->validated();

        return (new LancamentoResource(
            $this->service->registrar(
                $antecipacao,
                $this->resolverProfissional($dados['profissional_id']),
                Carbon::parse($dados['data_sessao'])
            )
        ))->response()->setStatusCode(201);
    }

    private function resolverProfissional(int $profissionalId): Profissional
    {
        return Profissional::query()->findOrFail($profissionalId);
    }
}
