<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePacienteRequest;
use App\Http\Requests\UpdatePacienteRequest;
use App\Http\Resources\PacienteResource;
use App\Models\Paciente;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PacienteController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $busca = trim((string) $request->string('busca'));
        $convenioId = $request->integer('convenio_id');

        return PacienteResource::collection(
            Paciente::query()
                ->with('convenio')
                ->when($convenioId, fn ($query) => $query->where('convenio_id', $convenioId))
                ->when($busca !== '', function ($query) use ($busca) {
                    $query->where(function ($nested) use ($busca) {
                        $nested->where('nome', 'like', "%{$busca}%")
                            ->orWhere('carteirinha', 'like', "%{$busca}%");
                    });
                })
                ->orderBy('nome')
                ->get()
        );
    }

    public function show(Paciente $paciente): PacienteResource
    {
        $paciente->load('convenio');

        return new PacienteResource($paciente);
    }

    public function store(StorePacienteRequest $request): JsonResponse
    {
        $paciente = Paciente::query()->create([
            ...$request->validated(),
            'tenant_id' => $request->user()->tenant_id,
            'ativo' => $request->boolean('ativo', true),
        ]);

        $paciente->load('convenio');

        return (new PacienteResource($paciente))->response()->setStatusCode(201);
    }

    public function update(UpdatePacienteRequest $request, Paciente $paciente): PacienteResource
    {
        $paciente->fill($request->validated());
        $paciente->save();
        $paciente->load('convenio');

        return new PacienteResource($paciente);
    }
}
