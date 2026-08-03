<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreConvenioEspecialidadeMapeamentoRequest;
use App\Http\Resources\ConvenioEspecialidadeMapeamentoResource;
use App\Models\ConvenioEspecialidadeMapeamento;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ConvenioEspecialidadeMapeamentoController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        return ConvenioEspecialidadeMapeamentoResource::collection(
            ConvenioEspecialidadeMapeamento::query()
                ->with(['convenio', 'especialidade'])
                ->when($request->integer('convenio_id'), fn ($query, $convenioId) => $query->where('convenio_id', $convenioId))
                ->when($request->integer('especialidade_id'), fn ($query, $especialidadeId) => $query->where('especialidade_id', $especialidadeId))
                ->when($request->has('ativo'), fn ($query) => $query->where('ativo', $request->boolean('ativo')))
                ->orderBy('id')
                ->get()
        );
    }

    public function store(StoreConvenioEspecialidadeMapeamentoRequest $request): JsonResponse
    {
        $mapeamento = ConvenioEspecialidadeMapeamento::query()->create([
            ...$request->validated(),
            'tenant_id' => $request->user()->tenant_id,
            'quantidade_padrao' => $request->integer('quantidade_padrao') ?: 10,
            'usa_descricao_generica' => $request->boolean('usa_descricao_generica'),
            'ativo' => $request->boolean('ativo', true),
        ]);

        return (new ConvenioEspecialidadeMapeamentoResource($mapeamento->load(['convenio', 'especialidade'])))
            ->response()
            ->setStatusCode(201);
    }

    public function update(
        StoreConvenioEspecialidadeMapeamentoRequest $request,
        ConvenioEspecialidadeMapeamento $especialidadeMapeamento
    ): ConvenioEspecialidadeMapeamentoResource {
        $especialidadeMapeamento->fill($request->validated());
        $especialidadeMapeamento->quantidade_padrao = $request->integer('quantidade_padrao') ?: 10;
        $especialidadeMapeamento->usa_descricao_generica = $request->boolean('usa_descricao_generica');
        $especialidadeMapeamento->ativo = $request->boolean('ativo', true);
        $especialidadeMapeamento->save();

        return new ConvenioEspecialidadeMapeamentoResource($especialidadeMapeamento->load(['convenio', 'especialidade']));
    }
}
