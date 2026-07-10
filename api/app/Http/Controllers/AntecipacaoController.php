<?php

namespace App\Http\Controllers;

use App\Http\Resources\AntecipacaoResource;
use App\Models\Antecipacao;
use App\Services\AntecipacaoService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AntecipacaoController extends Controller
{
    public function __construct(
        private readonly AntecipacaoService $service
    ) {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        return AntecipacaoResource::collection(
            $this->service->listar($request->only(['status', 'paciente_id', 'convenio_id']), (int) $request->integer('per_page', 15))
        );
    }

    public function show(Antecipacao $antecipacao): AntecipacaoResource
    {
        return new AntecipacaoResource($this->service->buscar($antecipacao->id));
    }
}
