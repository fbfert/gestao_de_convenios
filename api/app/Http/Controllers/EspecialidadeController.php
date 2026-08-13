<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEspecialidadeRequest;
use App\Http\Requests\UpdateEspecialidadeRequest;
use App\Http\Resources\EspecialidadeResource;
use App\Models\ConvenioEspecialidadeMapeamento;
use App\Models\Especialidade;
use App\Support\OrdenaListagem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class EspecialidadeController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $busca = trim((string) $request->string('busca'));
        $incluirInativos = $request->boolean('incluir_inativos');

        if ($incluirInativos && ! $request->user()?->can('especialidades.manage')) {
            abort(403);
        }

        $convenioId = (int) $request->integer('convenio_id');

        return EspecialidadeResource::collection(
            Especialidade::query()
                ->when(! $incluirInativos, fn ($query) => $query->where('ativo', true))
                ->when($busca !== '', fn ($query) => $query->where('nome', 'like', "%{$busca}%"))
                ->when($convenioId > 0, fn ($query) => $query->with([
                    'convenioMapeamentos' => fn ($relacao) => $relacao->where('convenio_id', $convenioId),
                ]))
                // Sem convenio_id, a tela de cadastro pede todos os códigos de
                // uma vez para montar um campo por convênio.
                ->when($convenioId === 0 && $request->boolean('com_codigos'), fn ($query) => $query->with('convenioMapeamentos'))
                ->tap(fn ($query) => OrdenaListagem::aplicar(
                    $query,
                    $request->only(['ordenar_por', 'direcao']),
                    [
                        'nome' => 'nome',
                        'status' => 'ativo',
                    ],
                    padrao: 'nome',
                    direcaoPadrao: 'asc',
                    desempate: 'nome',
                ))
                ->get()
        );
    }

    public function store(StoreEspecialidadeRequest $request): JsonResponse
    {
        $dados = $request->validated();
        $codigos = $dados['codigos'] ?? null;
        unset($dados['codigos']);

        $especialidade = DB::transaction(function () use ($dados, $codigos, $request) {
            $especialidade = Especialidade::query()->create([
                ...$dados,
                'tenant_id' => $request->user()->tenant_id,
                'ativo' => $request->boolean('ativo', true),
            ]);

            $this->sincronizarCodigos($especialidade, $codigos);

            return $especialidade;
        });

        return (new EspecialidadeResource($especialidade->load('convenioMapeamentos')))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateEspecialidadeRequest $request, Especialidade $especialidade): EspecialidadeResource
    {
        $dados = $request->validated();
        $codigos = $dados['codigos'] ?? null;
        unset($dados['codigos']);

        DB::transaction(function () use ($especialidade, $dados, $codigos) {
            $especialidade->fill($dados);
            $especialidade->save();

            $this->sincronizarCodigos($especialidade, $codigos);
        });

        return new EspecialidadeResource($especialidade->load('convenioMapeamentos'));
    }

    /**
     * Grava o código da especialidade em cada convênio.
     *
     * Atualiza só o `codigo_procedimento` do mapeamento existente: a tela da
     * Unimed configura no mesmo registro quantidade padrão e descrição da
     * operadora, e sobrescrever tudo aqui apagaria aquele ajuste. Código em
     * branco significa "esta especialidade não existe neste convênio", então o
     * mapeamento é removido — a coluna não aceita nulo.
     */
    private function sincronizarCodigos(Especialidade $especialidade, ?array $codigos): void
    {
        if ($codigos === null) {
            return;
        }

        foreach ($codigos as $item) {
            $convenioId = (int) ($item['convenio_id'] ?? 0);
            $codigo = trim((string) ($item['codigo'] ?? ''));

            if ($convenioId <= 0) {
                continue;
            }

            $mapeamento = ConvenioEspecialidadeMapeamento::query()
                ->where('convenio_id', $convenioId)
                ->where('especialidade_id', $especialidade->id)
                ->first();

            if ($codigo === '') {
                $mapeamento?->delete();

                continue;
            }

            if ($mapeamento) {
                $mapeamento->update(['codigo_procedimento' => $codigo, 'ativo' => true]);

                continue;
            }

            ConvenioEspecialidadeMapeamento::query()->create([
                'tenant_id' => $especialidade->tenant_id,
                'convenio_id' => $convenioId,
                'especialidade_id' => $especialidade->id,
                'codigo_procedimento' => $codigo,
                'ativo' => true,
            ]);
        }
    }
}
