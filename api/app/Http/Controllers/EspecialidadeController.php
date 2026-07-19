<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEspecialidadeRequest;
use App\Http\Requests\UpdateEspecialidadeRequest;
use App\Http\Resources\EspecialidadeResource;
use App\Models\Especialidade;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class EspecialidadeController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $busca = trim((string) $request->string('busca'));
        $incluirInativos = $request->boolean('incluir_inativos');

        if ($incluirInativos && ! $request->user()?->can('especialidades.manage')) {
            abort(403);
        }

        return EspecialidadeResource::collection(
            Especialidade::query()
                ->when(! $incluirInativos, fn ($query) => $query->where('ativo', true))
                ->when($busca !== '', fn ($query) => $query->where('nome', 'like', "%{$busca}%"))
                ->orderBy('nome')
                ->get()
        );
    }

    public function store(StoreEspecialidadeRequest $request): JsonResponse
    {
        $especialidade = Especialidade::query()->create([
            ...$request->validated(),
            'tenant_id' => $request->user()->tenant_id,
            'ativo' => $request->boolean('ativo', true),
        ]);

        return (new EspecialidadeResource($especialidade))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateEspecialidadeRequest $request, Especialidade $especialidade): EspecialidadeResource
    {
        $especialidade->fill($request->validated());
        $especialidade->save();

        return new EspecialidadeResource($especialidade);
    }
}
