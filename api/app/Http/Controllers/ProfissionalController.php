<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProfissionalRequest;
use App\Http\Requests\UpdateProfissionalRequest;
use App\Http\Resources\ProfissionalResource;
use App\Models\Profissional;
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

        return ProfissionalResource::collection(
            Profissional::query()
                ->with(['especialidade', 'especialidades'])
                ->when(! $incluirInativos, fn ($query) => $query->where('ativo', true))
                // Filtra pela ligacao, nao pela coluna: quem atua na
                // especialidade tem que aparecer mesmo que ela nao seja a
                // principal dele.
                ->when($especialidadeId, fn ($query) => $query->whereHas(
                    'especialidades',
                    fn ($nested) => $nested->where('especialidades.id', $especialidadeId),
                ))
                ->when($busca !== '', function ($query) use ($busca) {
                    $query->where(function ($nested) use ($busca) {
                        $nested->where('nome', 'like', "%{$busca}%")
                            ->orWhere('conselho_registro', 'like', "%{$busca}%")
                            ->orWhereHas('especialidade', function ($especialidadeQuery) use ($busca) {
                                $especialidadeQuery->where('nome', 'like', "%{$busca}%");
                            });
                    });
                })
                ->orderBy('nome')
                ->get()
        );
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
