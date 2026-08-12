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
        $dados = $this->normalizarCarteirinha($request->validated(), $request->user()->tenant_id);
        $paciente = Paciente::query()->create([
            ...$dados,
            'tenant_id' => $request->user()->tenant_id,
            'ativo' => $request->boolean('ativo', true),
        ]);

        $paciente->load('convenio');

        return (new PacienteResource($paciente))->response()->setStatusCode(201);
    }

    public function update(UpdatePacienteRequest $request, Paciente $paciente): PacienteResource
    {
        $paciente->fill($this->normalizarCarteirinha($request->validated(), $request->user()->tenant_id, $paciente));
        $paciente->save();
        $paciente->load('convenio');

        return new PacienteResource($paciente);
    }

    /**
     * Grava só os dígitos quando o convênio declara um formato de carteirinha.
     *
     * O gatilho é `convenios.carteirinha_blocos`, e não mais o
     * `connector_driver`: o formato é característica do convênio, o driver é o
     * interruptor da automação (ver migration 2026_08_12_200000). Convênio sem
     * formato continua guardando o texto como foi digitado, o que preserva as
     * carteirinhas já cadastradas.
     */
    private function normalizarCarteirinha(array $dados, int $tenantId, ?Paciente $paciente = null): array
    {
        $convenioId = $dados['convenio_id'] ?? $paciente?->convenio_id;

        if (! $convenioId || ! array_key_exists('carteirinha', $dados)) {
            return $dados;
        }

        $convenio = \App\Models\Convenio::query()
            ->where('tenant_id', $tenantId)
            ->whereKey($convenioId)
            ->first();

        if ($convenio?->blocosCarteirinha() !== null) {
            $dados['carteirinha'] = preg_replace('/\D+/', '', (string) $dados['carteirinha']);
        }

        return $dados;
    }
}
