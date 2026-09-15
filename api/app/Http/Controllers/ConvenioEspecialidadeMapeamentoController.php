<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreConvenioEspecialidadeMapeamentoRequest;
use App\Http\Resources\ConvenioEspecialidadeMapeamentoResource;
use App\Models\ConfiguracaoGlobal;
use App\Models\Convenio;
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
            'quantidade_padrao' => $request->integer('quantidade_padrao') ?: ConfiguracaoGlobal::doTenant((int) $request->user()->tenant_id)->sessoes_padrao,
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
        return $this->gravar($request, $especialidadeMapeamento);
    }

    /**
     * Mesma edição, na rota aninhada `.../{convenio}/mapeamentos/especialidades/{id}`.
     *
     * Método próprio, e não o `update` acima, por causa de como o Laravel
     * resolve dependências: com dois parâmetros na rota e só um model na
     * assinatura, ele injeta o PRIMEIRO da URL — o `{convenio}` chegava no lugar
     * do mapeamento e a chamada estourava com TypeError. Declarar os dois
     * resolve, e ainda deixa o convênio da URL conferido contra o do registro.
     */
    public function updateDoConvenio(
        StoreConvenioEspecialidadeMapeamentoRequest $request,
        Convenio $convenio,
        ConvenioEspecialidadeMapeamento $especialidadeMapeamento
    ): ConvenioEspecialidadeMapeamentoResource {
        abort_if((int) $especialidadeMapeamento->convenio_id !== (int) $convenio->id, 404);

        return $this->gravar($request, $especialidadeMapeamento);
    }

    private function gravar(
        StoreConvenioEspecialidadeMapeamentoRequest $request,
        ConvenioEspecialidadeMapeamento $especialidadeMapeamento
    ): ConvenioEspecialidadeMapeamentoResource {
        $especialidadeMapeamento->fill($request->validated());
        $especialidadeMapeamento->quantidade_padrao = $request->integer('quantidade_padrao') ?: ConfiguracaoGlobal::doTenant((int) $request->user()->tenant_id)->sessoes_padrao;
        $especialidadeMapeamento->usa_descricao_generica = $request->boolean('usa_descricao_generica');
        $especialidadeMapeamento->ativo = $request->boolean('ativo', true);
        $especialidadeMapeamento->save();

        return new ConvenioEspecialidadeMapeamentoResource($especialidadeMapeamento->load(['convenio', 'especialidade']));
    }
}
