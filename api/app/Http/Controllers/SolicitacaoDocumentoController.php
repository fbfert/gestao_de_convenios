<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSolicitacaoDocumentoRequest;
use App\Http\Requests\VincularSolicitacaoDocumentoRequest;
use App\Http\Resources\SolicitacaoResource;
use App\Models\PacienteArquivo;
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
        'itens.documentos.arquivo',
        'itens.guia',
        'itens.automacaoExecucoes',
        'documentos.arquivo',
        'guia',
    ];

    public function store(
        StoreSolicitacaoDocumentoRequest $request,
        Solicitacao $solicitacao,
    ): JsonResponse {
        $tipo = (string) $request->validated('tipo');
        $itemId = $request->validated('solicitacao_item_id');

        if ($erro = $this->recusaSegundoPedidoMedico($solicitacao, $tipo)) {
            return $erro;
        }

        $arquivoUpload = $request->file('arquivo');
        $tenantId = (int) $solicitacao->tenant_id;
        $extensao = $arquivoUpload->getClientOriginalExtension() ?: $arquivoUpload->guessExtension();
        $nomeArmazenado = Str::uuid()->toString().($extensao ? '.'.$extensao : '');
        $path = $arquivoUpload->storeAs("solicitacoes/{$tenantId}/{$solicitacao->id}", $nomeArmazenado, 'local');

        $documento = DB::transaction(function () use ($solicitacao, $tipo, $itemId, $arquivoUpload, $path, $tenantId) {
            $arquivo = PacienteArquivo::query()->create([
                'tenant_id' => $tenantId,
                'paciente_id' => $solicitacao->paciente_id,
                'tipo' => $tipo,
                'nome_original' => $arquivoUpload->getClientOriginalName(),
                'mime' => $arquivoUpload->getClientMimeType(),
                'path' => $path,
            ]);

            return $solicitacao->documentos()->create([
                'tenant_id' => $tenantId,
                'solicitacao_item_id' => $itemId,
                'paciente_arquivo_id' => $arquivo->id,
            ]);
        });

        return (new SolicitacaoResource($solicitacao->fresh()->load(self::RELACOES)))
            ->additional(['meta' => ['documento_id' => $documento->id]])
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Anexa um arquivo que já existe na pasta do paciente, sem upload — só
     * cria o vínculo (o arquivo pode já estar servindo outra solicitação).
     */
    public function vincular(
        VincularSolicitacaoDocumentoRequest $request,
        Solicitacao $solicitacao,
    ): JsonResponse {
        $arquivo = PacienteArquivo::query()->findOrFail($request->validated('paciente_arquivo_id'));
        $itemId = $request->validated('solicitacao_item_id');

        if ($erro = $this->recusaSegundoPedidoMedico($solicitacao, $arquivo->tipo)) {
            return $erro;
        }

        $documento = $solicitacao->documentos()->create([
            'tenant_id' => $solicitacao->tenant_id,
            'solicitacao_item_id' => $itemId,
            'paciente_arquivo_id' => $arquivo->id,
        ]);

        return (new SolicitacaoResource($solicitacao->fresh()->load(self::RELACOES)))
            ->additional(['meta' => ['documento_id' => $documento->id]])
            ->response()
            ->setStatusCode(201);
    }

    public function download(Solicitacao $solicitacao, SolicitacaoDocumento $documento): BinaryFileResponse
    {
        $this->garantirVinculo($solicitacao, $documento);
        $documento->loadMissing('arquivo');
        abort_unless(Storage::disk('local')->exists($documento->arquivo->path), 404);

        return response()->file(Storage::disk('local')->path($documento->arquivo->path), [
            'Content-Type' => $documento->arquivo->mime ?? 'application/octet-stream',
        ]);
    }

    /** Remove só o vínculo — o arquivo continua na pasta do paciente. */
    public function destroy(Solicitacao $solicitacao, SolicitacaoDocumento $documento): JsonResponse|SolicitacaoResource
    {
        $this->garantirVinculo($solicitacao, $documento);

        if ($motivo = $this->motivoParaBloquearRemocao($solicitacao, $documento)) {
            return response()->json([
                'message' => $motivo,
                'errors' => ['documento' => [$motivo]],
            ], 422);
        }

        $documento->delete();

        return new SolicitacaoResource($solicitacao->fresh()->load(self::RELACOES));
    }

    private function recusaSegundoPedidoMedico(Solicitacao $solicitacao, string $tipo): ?JsonResponse
    {
        // O worker escolhe o Pedido Médico com firstWhere('tipo', 'pedido_medico'), então
        // um segundo arquivo do mesmo tipo tornaria o envio ambíguo.
        $jaTem = $tipo === 'pedido_medico' && $solicitacao->documentos()
            ->whereHas('arquivo', fn ($q) => $q->where('tipo', 'pedido_medico'))
            ->exists();

        if (! $jaTem) {
            return null;
        }

        return response()->json([
            'message' => 'Esta solicitação já possui um Pedido Médico. Remova o atual antes de anexar outro.',
            'errors' => ['tipo' => ['Esta solicitação já possui um Pedido Médico anexado.']],
        ], 422);
    }

    private function garantirVinculo(Solicitacao $solicitacao, SolicitacaoDocumento $documento): void
    {
        abort_unless($documento->solicitacao_id === $solicitacao->id, 404);
    }

    /**
     * Depois que a Guia existe, o vínculo faz parte do que foi enviado à
     * operadora: removê-lo apagaria a evidência do que sustentou aquela
     * autorização. Trava só este vínculo — o arquivo pode continuar servindo
     * outra solicitação.
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
