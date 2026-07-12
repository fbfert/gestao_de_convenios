<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMedicoRequest;
use App\Http\Requests\UpdateMedicoRequest;
use App\Http\Resources\MedicoResource;
use App\Models\Medico;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MedicoController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $busca = trim((string) $request->string('busca'));

        return MedicoResource::collection(
            Medico::query()
                ->when($busca !== '', function ($query) use ($busca) {
                    $query->where(function ($nested) use ($busca) {
                        $nested->where('nome', 'like', "%{$busca}%")
                            ->orWhere('crm', 'like', "%{$busca}%")
                            ->orWhere('especialidade_medica', 'like', "%{$busca}%");
                    });
                })
                ->orderBy('nome')
                ->get()
        );
    }

    public function store(StoreMedicoRequest $request): JsonResponse
    {
        $medico = Medico::query()->create([
            ...$request->validated(),
            'tenant_id' => $request->user()->tenant_id,
            'ativo' => $request->boolean('ativo', true),
        ]);

        return (new MedicoResource($medico))->response()->setStatusCode(201);
    }

    public function update(UpdateMedicoRequest $request, Medico $medico): MedicoResource
    {
        $medico->fill($request->validated());
        $medico->save();

        return new MedicoResource($medico);
    }
}
