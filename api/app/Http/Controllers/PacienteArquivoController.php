<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePacienteArquivoRequest;
use App\Http\Resources\PacienteArquivoResource;
use App\Models\Paciente;
use App\Models\PacienteArquivo;
use App\Models\SolicitacaoDocumento;
use App\Services\Sessoes\FolhasDeRegistroService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PacienteArquivoController extends Controller
{
    public function index(Paciente $paciente, FolhasDeRegistroService $folhas): AnonymousResourceCollection
    {
        $arquivos = $paciente->arquivos()->with('vinculos')->orderByDesc('id')->get();

        // Folha de registro diz de que guia é: um paciente com várias guias
        // teria várias "registro-sessoes.pdf" indistinguíveis na pasta.
        $guias = $folhas->guiasDasFolhas($arquivos);
        $arquivos->each(fn (PacienteArquivo $arquivo) => $arquivo->setRelation('guiaDaFolha', $guias[$arquivo->id] ?? null));

        return PacienteArquivoResource::collection($arquivos);
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
            // Do arquivo gravado, e nao de `getClientMimeType()`, que e o header
            // multipart escolhido por quem envia: o valor volta cru como
            // Content-Type do download.
            'mime' => Storage::disk('local')->mimeType($path) ?: 'application/octet-stream',
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
            // `nosniff` porque o Content-Type vem do banco, e `attachment` para o
            // anexo nunca renderizar como pagina: o front abre o download com
            // `URL.createObjectURL`, e a URL `blob:` herda a origem do SPA — onde
            // o token de sessao vive no localStorage.
            'X-Content-Type-Options' => 'nosniff',
            'Content-Disposition' => 'attachment; filename="'.basename((string) $arquivo->nome_original).'"',
        ]);
    }

    /**
     * O que dá pra reaproveitar de um pedido médico já na pasta: médico, CIDs
     * e especialidades da solicitação MAIS RECENTE que usou este arquivo —
     * não existe esse dado no PacienteArquivo em si (ele é só o arquivo),
     * então a única fonte é olhar pra trás no que já foi feito com ele. Se o
     * arquivo nunca foi usado em nenhuma solicitação, devolve tudo nulo/vazio
     * — quem chama decide o que fazer (deixar a pessoa preencher à mão).
     */
    public function contexto(Paciente $paciente, PacienteArquivo $arquivo): JsonResponse
    {
        $this->garantirVinculo($paciente, $arquivo);

        $ultimoVinculo = SolicitacaoDocumento::query()
            ->where('paciente_arquivo_id', $arquivo->id)
            ->whereNull('solicitacao_item_id')
            ->with([
                'solicitacao.medico',
                'solicitacao.cidCadastros',
                'solicitacao.itens.especialidade',
                'solicitacao.itens.profissional',
            ])
            ->orderByDesc('solicitacao_id')
            ->first();

        $solicitacao = $ultimoVinculo?->solicitacao;

        return response()->json(['data' => [
            'solicitacao_id' => $solicitacao?->id,
            'medico' => $solicitacao?->medico ? [
                'id' => $solicitacao->medico->id,
                'nome' => $solicitacao->medico->nome,
                'crm' => $solicitacao->medico->crm,
                'crm_uf' => $solicitacao->medico->crm_uf,
            ] : null,
            'cid_ids' => $solicitacao?->cidCadastros->pluck('id')->all() ?? [],
            'cids' => $solicitacao?->cidCadastros->map(fn ($cid) => [
                'id' => $cid->id,
                'codigo' => $cid->codigo,
                'descricao' => $cid->descricao,
            ])->all() ?? [],
            // Par especialidade+profissional de cada item da solicitação de
            // origem — não só o nome da especialidade — pra tela poder
            // pré-preencher os itens de verdade, não só sugerir o texto.
            'itens' => $solicitacao?->itens
                ->filter(fn ($item) => $item->especialidade && $item->profissional)
                ->unique(fn ($item) => "{$item->especialidade_id}-{$item->profissional_id}")
                ->values()
                ->map(fn ($item) => [
                    'especialidade_id' => $item->especialidade->id,
                    'especialidade_nome' => $item->especialidade->nome,
                    'profissional_id' => $item->profissional->id,
                    'profissional_nome' => $item->profissional->nome,
                ])
                ->all() ?? [],
        ]]);
    }

    public function destroy(Paciente $paciente, PacienteArquivo $arquivo, FolhasDeRegistroService $folhas): JsonResponse
    {
        $this->garantirVinculo($paciente, $arquivo);
        $folhas->garantirRemovivelPelaPasta($arquivo);

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
