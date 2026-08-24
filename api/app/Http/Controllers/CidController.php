<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCidRequest;
use App\Http\Requests\UpdateCidRequest;
use App\Http\Resources\CidResource;
use App\Models\Cid;
use App\Support\OrdenaListagem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CidController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $busca = trim((string) $request->string('busca'));
        $incluirInativos = $request->boolean('incluir_inativos');

        return CidResource::collection(
            Cid::query()
                ->when(! $incluirInativos, fn ($query) => $query->where('ativo', true))
                ->when(
                    $busca !== '',
                    fn ($query) => $query->where(fn ($sub) => $sub
                        ->where('codigo', 'like', "%{$busca}%")
                        ->orWhere('descricao', 'like', "%{$busca}%")),
                )
                ->tap(fn ($query) => OrdenaListagem::aplicar(
                    $query,
                    $request->only(['ordenar_por', 'direcao']),
                    [
                        'codigo' => 'codigo',
                        'descricao' => 'descricao',
                        'status' => 'ativo',
                    ],
                    padrao: 'codigo',
                    direcaoPadrao: 'asc',
                    desempate: 'codigo',
                ))
                ->get()
        );
    }

    public function store(StoreCidRequest $request): JsonResponse
    {
        $cid = Cid::query()->create([
            ...$request->validated(),
            'tenant_id' => $request->user()->tenant_id,
            'ativo' => $request->boolean('ativo', true),
        ]);

        return (new CidResource($cid))->response()->setStatusCode(201);
    }

    public function update(UpdateCidRequest $request, Cid $cid): CidResource
    {
        $cid->fill($request->validated());
        $cid->save();

        return new CidResource($cid);
    }
}
