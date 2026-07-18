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
                ->with('especialidade')
                ->when(! $incluirInativos, fn ($query) => $query->where('ativo', true))
                ->when($especialidadeId, fn ($query) => $query->where('especialidade_id', $especialidadeId))
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
        $profissional = Profissional::query()->create([
            ...$request->validated(),
            'tenant_id' => $request->user()->tenant_id,
            'ativo' => $request->boolean('ativo', true),
        ]);

        return (new ProfissionalResource($profissional->load('especialidade')))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateProfissionalRequest $request, Profissional $profissional): ProfissionalResource
    {
        abort_if($profissional->tenant_id !== $request->user()->tenant_id, 404);

        $profissional->fill($request->validated());
        $profissional->save();

        return new ProfissionalResource($profissional->load('especialidade'));
    }
}
