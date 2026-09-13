<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProfissionalRequest;
use App\Http\Requests\UpdateProfissionalRequest;
use App\Http\Resources\ProfissionalResource;
use App\Models\Profissional;
use App\Support\OrdenaListagem;
use App\Support\PaginaListagem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProfissionalController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $busca = trim((string) $request->string('busca'));
        $especialidadeId = $request->integer('especialidade_id');
        $incluirInativos = $request->boolean('incluir_inativos');

        $query = Profissional::query()
            ->with(['especialidade', 'especialidades'])
            ->when(! $incluirInativos, fn ($query) => $query->where('profissionais.ativo', true))
            // Filtra pela ligacao, nao pela coluna: quem atua na
            // especialidade tem que aparecer mesmo que ela nao seja a
            // principal dele.
            ->when($especialidadeId, fn ($query) => $query->whereHas(
                'especialidades',
                fn ($nested) => $nested->where('especialidades.id', $especialidadeId),
            ))
            ->when($busca !== '', function ($query) use ($busca) {
                $query->where(function ($nested) use ($busca) {
                    $nested->where('profissionais.nome', 'like', "%{$busca}%")
                        ->orWhere('profissionais.conselho_registro', 'like', "%{$busca}%")
                        ->orWhereHas('especialidade', function ($especialidadeQuery) use ($busca) {
                            $especialidadeQuery->where('nome', 'like', "%{$busca}%");
                        });
                });
            })
            ->tap(fn ($query) => OrdenaListagem::aplicar(
                $query->select('profissionais.*'),
                $request->only(['ordenar_por', 'direcao']),
                [
                    'nome' => 'profissionais.nome',
                    'conselho' => 'profissionais.conselho_registro',
                    'repasse' => 'profissionais.percentual_repasse',
                    'status' => 'profissionais.ativo',
                    // Pela especialidade principal, que é o que a coluna mostra.
                    // O profissional pode atuar em várias (pivot
                    // `especialidade_profissional`), e ordenar por um conjunto
                    // não teria resposta única — a principal é sempre uma só, e
                    // o próprio model garante que ela está entre as demais.
                    'especialidade' => fn ($query, $direcao) => $query
                        ->leftJoin('especialidades', 'especialidades.id', '=', 'profissionais.especialidade_id')
                        ->orderBy('especialidades.nome', $direcao),
                ],
                padrao: 'profissionais.nome',
                direcaoPadrao: 'asc',
                desempate: 'profissionais.nome',
            ));

        return ProfissionalResource::collection(PaginaListagem::aplicar($query, $request));
    }

    public function store(StoreProfissionalRequest $request): JsonResponse
    {
        $dados = $request->validated();
        $especialidadeIds = $dados['especialidade_ids'] ?? [];
        unset($dados['especialidade_ids']);

        $profissional = Profissional::query()->create([
            ...$dados,
            'tenant_id' => $request->user()->tenant_id,
            'ativo' => $request->boolean('ativo', true),
        ]);

        $profissional->sincronizarEspecialidades($especialidadeIds);

        return (new ProfissionalResource($profissional->load(['especialidade', 'especialidades'])))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateProfissionalRequest $request, Profissional $profissional): ProfissionalResource
    {
        abort_if($profissional->tenant_id !== $request->user()->tenant_id, 404);

        $dados = $request->validated();
        $especialidadeIds = $dados['especialidade_ids'] ?? null;
        unset($dados['especialidade_ids']);

        $profissional->fill($dados);
        $profissional->save();

        // `null` = o cliente nao mandou a lista; nesse caso so reafirma a
        // invariante da principal, sem apagar o que ja estava ligado.
        $profissional->sincronizarEspecialidades(
            $especialidadeIds ?? $profissional->especialidades()->pluck('especialidades.id')->all(),
        );

        return new ProfissionalResource($profissional->load(['especialidade', 'especialidades']));
    }
}
