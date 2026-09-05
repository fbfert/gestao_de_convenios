<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePacienteArquivoRequest;
use App\Http\Resources\PacienteArquivoResource;
use App\Models\Paciente;
use App\Models\PacienteArquivo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PacienteArquivoController extends Controller
{
    public function index(Paciente $paciente): AnonymousResourceCollection
    {
        return PacienteArquivoResource::collection(
            $paciente->arquivos()->with('vinculos')->orderByDesc('id')->get()
        );
    }

    public function store(StorePacienteArquivoRequest $request, Paciente $paciente): JsonResponse
    {
        $arquivoUpload = $request->file('arquivo');
        $tenantId = (int) $paciente->tenant_id;
        $extensao = $arquivoUpload->getClientOriginalExtension() ?: $arquivoUpload->guessExtension();
        $nomeArmazenado = Str::uuid()->toString().($extensao ? '.'.$extensao : '');
        $path = $arquivoUpload->storeAs("pacientes/{$tenantId}/{$paciente->id}", $nomeArmazenado, 'local');

        $arquivo = $paciente->arquivos()->create([
            'tenant_id' => $tenantId,
            'tipo' => $request->validated('tipo'),
            'nome_original' => $arquivoUpload->getClientOriginalName(),
            'mime' => $arquivoUpload->getClientMimeType(),
            'path' => $path,
        ]);

        return (new PacienteArquivoResource($arquivo->load('vinculos')))
            ->response()
            ->setStatusCode(201);
    }

    public function download(Paciente $paciente, PacienteArquivo $arquivo): BinaryFileResponse
    {
        $this->garantirVinculo($paciente, $arquivo);
        abort_unless(Storage::disk('local')->exists($arquivo->path), 404);

        return response()->file(Storage::disk('local')->path($arquivo->path), [
            'Content-Type' => $arquivo->mime ?? 'application/octet-stream',
        ]);
    }

    public function destroy(Paciente $paciente, PacienteArquivo $arquivo): JsonResponse
    {
        $this->garantirVinculo($paciente, $arquivo);

        $vinculos = $arquivo->vinculos()->get();

        if ($vinculos->isNotEmpty()) {
            $travado = $vinculos->contains(fn ($vinculo) => $vinculo->estaTravado());

            $mensagem = $travado
                ? 'Este arquivo está vinculado a uma solicitação com Guia gerada e não pode ser excluído.'
                : 'Remova o vínculo deste arquivo com as solicitações antes de excluí-lo da pasta.';

            return response()->json([
                'message' => $mensagem,
                'errors' => ['arquivo' => [$mensagem]],
            ], 422);
        }

        $path = $arquivo->path;
        $arquivo->delete();

        if (filled($path) && Storage::disk('local')->exists($path)) {
            Storage::disk('local')->delete($path);
        }

        return response()->json(null, 204);
    }

    private function garantirVinculo(Paciente $paciente, PacienteArquivo $arquivo): void
    {
        abort_unless($arquivo->paciente_id === $paciente->id, 404);
    }
}
