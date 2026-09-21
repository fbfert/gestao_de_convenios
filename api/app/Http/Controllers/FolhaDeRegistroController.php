<?php

namespace App\Http\Controllers;

use App\Models\Guia;
use App\Models\PacienteArquivo;
use App\Models\User;
use App\Services\Sessoes\FolhasDeRegistroService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * As folhas de registro de sessões de uma guia.
 *
 * Separado de LancamentoController porque o ciclo de vida é outro: a folha
 * pode chegar junto da confirmação das sessões, mas também depois — a segunda
 * via de uma guia de dez sessões costuma aparecer dias após a primeira, e até
 * a finalização na operadora ainda dá tempo de anexá-la.
 */
class FolhaDeRegistroController extends Controller
{
    public function __construct(
        private readonly FolhasDeRegistroService $folhas,
    ) {}

    public function index(Guia $guia): JsonResponse
    {
        return response()->json(['data' => $this->apresentar($guia)]);
    }

    public function store(Request $request, Guia $guia): JsonResponse
    {
        $request->validate([
            'arquivos' => ['required', 'array', 'min:1'],
            'arquivos.*' => ['file', 'mimes:pdf,jpg,jpeg,png'],
        ]);

        $this->folhas->anexar($guia, $request->file('arquivos'));

        return response()->json(['data' => $this->apresentar($guia)], 201);
    }

    public function destroy(Guia $guia, PacienteArquivo $arquivo): JsonResponse
    {
        $this->folhas->remover($guia, $arquivo);

        return response()->json(null, 204);
    }

    /**
     * Data de envio e quem enviou vêm junto: é o que permite, na guia, dizer
     * de onde cada folha veio sem abrir a pasta do paciente.
     *
     * @return array<int, array<string, mixed>>
     */
    private function apresentar(Guia $guia): array
    {
        $folhas = $this->folhas->daGuia($guia);

        $nomesDeQuemEnviou = User::query()
            ->whereIn('id', $folhas->pluck('metadata.enviado_por')->filter()->unique()->values())
            ->pluck('name', 'id');

        return $folhas->map(fn (PacienteArquivo $folha) => [
            'id' => $folha->id,
            'nome_original' => $folha->nome_original,
            'mime' => $folha->mime,
            'enviado_em' => $folha->created_at?->toIso8601String(),
            'enviado_por' => $nomesDeQuemEnviou[$folha->metadata['enviado_por'] ?? null] ?? null,
            'download_url' => "/pacientes/{$folha->paciente_id}/arquivos/{$folha->id}",
        ])->all();
    }
}
