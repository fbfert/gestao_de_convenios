<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreConvenioProfissionalMapeamentoRequest;
use App\Http\Resources\ConvenioProfissionalMapeamentoResource;
use App\Models\Convenio;
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
        return $this->gravar($request, $profissionalMapeamento);
    }

    /**
     * Mesma edição, na rota aninhada `.../{convenio}/mapeamentos/profissionais/{id}`.
     *
     * Método próprio, e não o `update` acima, por causa de como o Laravel
     * resolve dependências: com dois parâmetros na rota e só um model na
     * assinatura, ele injeta o PRIMEIRO da URL — o `{convenio}` chegava no lugar
     * do mapeamento e a chamada estourava com TypeError. Declarar os dois
     * resolve, e ainda deixa o convênio da URL conferido contra o do registro.
     */
    public function updateDoConvenio(
        StoreConvenioProfissionalMapeamentoRequest $request,
        Convenio $convenio,
        ConvenioProfissionalMapeamento $profissionalMapeamento
    ): ConvenioProfissionalMapeamentoResource {
        abort_if((int) $profissionalMapeamento->convenio_id !== (int) $convenio->id, 404);

        return $this->gravar($request, $profissionalMapeamento);
    }

    private function gravar(
        StoreConvenioProfissionalMapeamentoRequest $request,
        ConvenioProfissionalMapeamento $profissionalMapeamento
    ): ConvenioProfissionalMapeamentoResource {
        $profissionalMapeamento->fill($request->validated());
        $profissionalMapeamento->ativo = $request->boolean('ativo', true);
        $profissionalMapeamento->save();

        return new ConvenioProfissionalMapeamentoResource($profissionalMapeamento->load(['convenio', 'profissional']));
    }
}
