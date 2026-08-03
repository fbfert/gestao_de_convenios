<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreConvenioProfissionalMapeamentoRequest;
use App\Http\Resources\ConvenioProfissionalMapeamentoResource;
use App\Models\ConvenioProfissionalMapeamento;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ConvenioProfissionalMapeamentoController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        return ConvenioProfissionalMapeamentoResource::collection(
            ConvenioProfissionalMapeamento::query()
                ->with(['convenio', 'profissional'])
                ->when($request->integer('convenio_id'), fn ($query, $convenioId) => $query->where('convenio_id', $convenioId))
                ->when($request->integer('profissional_id'), fn ($query, $profissionalId) => $query->where('profissional_id', $profissionalId))
                ->when($request->has('ativo'), fn ($query) => $query->where('ativo', $request->boolean('ativo')))
                ->orderBy('id')
                ->get()
        );
    }

    public function store(StoreConvenioProfissionalMapeamentoRequest $request): JsonResponse
    {
        $mapeamento = ConvenioProfissionalMapeamento::query()->create([
            ...$request->validated(),
            'tenant_id' => $request->user()->tenant_id,
            'ativo' => $request->boolean('ativo', true),
        ]);

        return (new ConvenioProfissionalMapeamentoResource($mapeamento->load(['convenio', 'profissional'])))
            ->response()
            ->setStatusCode(201);
    }

    public function update(
        StoreConvenioProfissionalMapeamentoRequest $request,
        ConvenioProfissionalMapeamento $profissionalMapeamento
    ): ConvenioProfissionalMapeamentoResource {
        $profissionalMapeamento->fill($request->validated());
        $profissionalMapeamento->ativo = $request->boolean('ativo', true);
        $profissionalMapeamento->save();

        return new ConvenioProfissionalMapeamentoResource($profissionalMapeamento->load(['convenio', 'profissional']));
    }
}
