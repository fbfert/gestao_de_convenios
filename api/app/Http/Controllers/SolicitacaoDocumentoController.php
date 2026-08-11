<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSolicitacaoDocumentoRequest;
use App\Http\Resources\SolicitacaoResource;
use App\Models\Solicitacao;
use App\Models\SolicitacaoDocumento;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SolicitacaoDocumentoController extends Controller
{
    private const RELACOES = [
        'paciente',
        'profissional',
        'especialidade',
        'convenio',
        'medico',
        'itens.especialidade.convenioMapeamentos',
        'itens.profissional',
        'itens.documentos',
        'itens.guia',
        'itens.automacaoExecucoes',
        'documentos',
        'guia',
    ];

    public function store(
        StoreSolicitacaoDocumentoRequest $request,
        Solicitacao $solicitacao,
    ): JsonResponse {
        $tipo = (string) $request->validated('tipo');
        $itemId = $request->validated('solicitacao_item_id');

        // O worker escolhe o Pedido Médico com firstWhere('tipo', 'pedido_medico'), então
        // um segundo arquivo do mesmo tipo tornaria o envio ambíguo.
        if ($tipo === 'pedido_medico' && $solicitacao->documentos()->where('tipo', 'pedido_medico')->exists()) {
            return response()->json([
                'message' => 'Esta solicitação já possui um Pedido Médico. Remova o atual antes de anexar outro.',
                'errors' => ['tipo' => ['Esta solicitação já possui um Pedido Médico anexado.']],
            ], 422);
        }

        $arquivo = $request->file('arquivo');
        $tenantId = (int) $solicitacao->tenant_id;
        $extensao = $arquivo->getClientOriginalExtension() ?: $arquivo->guessExtension();
        $nomeArmazenado = Str::uuid()->toString().($extensao ? '.'.$extensao : '');
        $path = $arquivo->storeAs("solicitacoes/{$tenantId}/{$solicitacao->id}", $nomeArmazenado, 'local');

        $documento = DB::transaction(function () use ($solicitacao, $tipo, $itemId, $arquivo, $path, $tenantId) {
            $documento = $solicitacao->documentos()->create([
                'tenant_id' => $tenantId,
                'solicitacao_item_id' => $itemId,
                'tipo' => $tipo,
                'nome_original' => $arquivo->getClientOriginalName(),
                'mime' => $arquivo->getClientMimeType(),
                'path' => $path,
            ]);

            // Campos legados na Solicitação: continuam alimentando a tela de detalhe
            // e o botão "Abrir pedido" que já existiam antes dos anexos por item.
            if ($tipo === 'pedido_medico') {
                $solicitacao->forceFill([
                    'pedido_medico_path' => $path,
                    'pedido_medico_nome_original' => $documento->nome_original,
                    'pedido_medico_mime' => $documento->mime,
                ])->save();
            }

            return $documento;
        });

        return (new SolicitacaoResource($solicitacao->fresh()->load(self::RELACOES)))
            ->additional(['meta' => ['documento_id' => $documento->id]])
            ->response()
            ->setStatusCode(201);
    }

    public function download(Solicitacao $solicitacao, SolicitacaoDocumento $documento): BinaryFileResponse
    {
        $this->garantirVinculo($solicitacao, $documento);
        abort_unless(Storage::disk('local')->exists($documento->path), 404);

        return response()->file(Storage::disk('local')->path($documento->path), [
            'Content-Type' => $documento->mime ?? 'application/octet-stream',
        ]);
    }

    public function destroy(Solicitacao $solicitacao, SolicitacaoDocumento $documento): JsonResponse|SolicitacaoResource
    {
        $this->garantirVinculo($solicitacao, $documento);

        if ($motivo = $this->motivoParaBloquearRemocao($solicitacao, $documento)) {
            return response()->json([
                'message' => $motivo,
                'errors' => ['documento' => [$motivo]],
            ], 422);
        }

        DB::transaction(function () use ($solicitacao, $documento) {
            $path = $documento->path;
            $eraPedidoMedico = $documento->tipo === 'pedido_medico';

            $documento->delete();

            if ($eraPedidoMedico && $solicitacao->pedido_medico_path === $path) {
                $solicitacao->forceFill([
                    'pedido_medico_path' => null,
                    'pedido_medico_nome_original' => null,
                    'pedido_medico_mime' => null,
                ])->save();
            }

            if (filled($path) && Storage::disk('local')->exists($path)) {
                Storage::disk('local')->delete($path);
            }
        });

        return new SolicitacaoResource($solicitacao->fresh()->load(self::RELACOES));
    }

    private function garantirVinculo(Solicitacao $solicitacao, SolicitacaoDocumento $documento): void
    {
        abort_unless($documento->solicitacao_id === $solicitacao->id, 404);
    }

    /**
     * Depois que a Guia existe, o anexo faz parte do que foi enviado à operadora:
     * removê-lo apagaria a evidência do que sustentou aquela autorização.
     */
    private function motivoParaBloquearRemocao(
        Solicitacao $solicitacao,
        SolicitacaoDocumento $documento,
    ): ?string {
        if ($documento->solicitacao_item_id) {
            $item = $solicitacao->itens()->whereKey($documento->solicitacao_item_id)->first();

            return $item?->guia()->exists()
                ? 'Esta especialidade já tem Guia gerada. O anexo não pode mais ser removido.'
                : null;
        }

        $temGuia = $solicitacao->itens()->whereHas('guia')->exists()
            || $solicitacao->guia()->exists();

        return $temGuia
            ? 'A solicitação já tem Guia gerada. Os anexos do pedido não podem mais ser removidos.'
            : null;
    }
}
