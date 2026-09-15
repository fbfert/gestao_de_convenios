<?php

namespace App\Http\Controllers;

use App\Http\Requests\ImportAnaliticoUnimedRequest;
use App\Http\Requests\ImportLancamentosTranscricaoRequest;
use App\Http\Requests\LerRegistroSessoesRequest;
use App\Http\Requests\StoreLancamentoRequest;
use App\Http\Requests\UpdateLancamentoRequest;
use App\Http\Resources\LancamentoResource;
use App\Models\ConfiguracaoGlobal;
use App\Models\Guia;
use App\Models\Lancamento;
use App\Models\PacienteArquivo;
use App\Models\Profissional;
use App\Services\AnaliticoUnimedImportService;
use App\Services\LancamentoService;
use App\Services\RegistroSessoesAiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LancamentoController extends Controller
{
    public function __construct(
        private readonly LancamentoService $service,
        private readonly AnaliticoUnimedImportService $analiticoImportService
    ) {}

    /**
     * Agrupada por Guia (uma guia = um grupo com todas as sessões batendo os
     * filtros dentro) — a paginação é por GUIA, não por lançamento, então o
     * shape de resposta não é o `AnonymousResourceCollection` padrão.
     */
    public function index(Request $request): JsonResponse
    {
        $pagina = $this->service->listarAgrupadoPorGuia(
            $request->only(['profissional_id', 'data_sessao', 'busca']),
            $request->integer('per_page') ?: ConfiguracaoGlobal::itensPorPagina(),
        );

        return response()->json([
            'data' => collect($pagina->items())->map(fn ($grupo) => [
                'guia_id' => $grupo['guia_id'],
                'guia' => $grupo['guia'] ? [
                    'id' => $grupo['guia']->id,
                    'numero_guia' => $grupo['guia']->numero_guia,
                    'status' => $grupo['guia']->status,
                    'paciente_nome' => $grupo['guia']->paciente?->nome,
                    'medico_nome' => $grupo['guia']->solicitacaoItem?->solicitacao?->medico?->nome,
                ] : null,
                'lancamentos' => LancamentoResource::collection($grupo['lancamentos'])->resolve(),
            ])->values(),
            'meta' => [
                'current_page' => $pagina->currentPage(),
                'last_page' => $pagina->lastPage(),
                'total' => $pagina->total(),
            ],
        ]);
    }

    public function show(Lancamento $lancamento): LancamentoResource
    {
        return new LancamentoResource($this->service->buscar($lancamento->id));
    }

    public function store(StoreLancamentoRequest $request, Guia $guia): JsonResponse
    {
        $dados = $request->validated();

        return (new LancamentoResource(
            $this->service->registrarSessao(
                $guia,
                $this->resolverProfissional($dados['profissional_id']),
                $dados
            )
        ))->response()->setStatusCode(201);
    }

    public function update(UpdateLancamentoRequest $request, Lancamento $lancamento): LancamentoResource
    {
        return new LancamentoResource($this->service->atualizar($lancamento, $request->validated()));
    }

    public function destroy(Lancamento $lancamento): JsonResponse
    {
        $this->service->remover($lancamento);

        return response()->json(null, 204);
    }

    /**
     * Lê o registro de sessões escaneado e devolve a mesma pré-visualização da
     * transcrição colada — cabeçalho e sessões —, sem gravar nada.
     *
     * A confirmação continua sendo a rota de sempre: o operador revisa datas e
     * horários na tabela antes de qualquer lançamento existir.
     */
    public function lerRegistroSessoes(
        LerRegistroSessoesRequest $request,
        Guia $guia,
        RegistroSessoesAiService $registroAi,
    ): JsonResponse {
        $tenantId = (int) $request->user()->tenant_id;
        $arquivo = $request->file('arquivo');
        $nome = Str::uuid()->toString().'.'.$arquivo->getClientOriginalExtension();
        $path = $arquivo->storeAs("registros-sessoes/{$tenantId}", $nome, 'local');

        $resultado = $registroAi->analisar($tenantId, $arquivo, $path);

        return response()->json([
            'data' => [
                'confirmacao_pendente' => true,
                'cabecalho' => $resultado['cabecalho'],
                'sessoes' => $resultado['sessoes'],
                'registros' => [],
            ],
        ]);
    }

    public function importarTranscricao(ImportLancamentosTranscricaoRequest $request, Guia $guia): JsonResponse
    {
        $dados = $request->validated();
        $profissional = $this->resolverProfissional($dados['profissional_id']);

        if (! $request->boolean('confirmar_envio')) {
            // Este ramo só existe para o "colar texto → Analisar" da grade: os
            // outros dois caminhos (leitura por IA, preenchimento manual) já
            // chegam aqui com confirmar_envio=true, sem passar por preview.
            $resultado = filled($dados['transcricao'] ?? null)
                ? $this->service->previsualizarTranscricao($dados['transcricao'])
                : ['cabecalho' => [], 'sessoes' => []];

            return response()->json([
                'data' => [
                    'confirmacao_pendente' => true,
                    'cabecalho' => $resultado['cabecalho'],
                    'sessoes' => $resultado['sessoes'],
                    'registros' => [],
                ],
            ]);
        }

        // numero_cartao vem explícito do payload quando a leitura foi por
        // imagem/PDF ou a grade foi preenchida manualmente (não há
        // transcrição para reprocessar nesses casos). Quando não vier
        // explícito mas houver transcrição colada, cai no comportamento de
        // sempre: deriva o número do cartão reprocessando o texto.
        $numeroCartao = $dados['numero_cartao'] ?? null;
        if (blank($numeroCartao) && filled($dados['transcricao'] ?? null)) {
            $numeroCartao = $this->service->previsualizarTranscricao($dados['transcricao'])['cabecalho']['numero_cartao'] ?? null;
        }

        if ($this->regiaoExigePdf($numeroCartao) && ! $request->hasFile('pdf_registro_sessoes')) {
            throw ValidationException::withMessages([
                'pdf_registro_sessoes' => 'O PDF do registro de sessões é obrigatório para a regional 0220.',
            ]);
        }

        $resultado = $this->service->confirmarTranscricao(
            $guia,
            $profissional,
            $dados['transcricao'] ?? null,
            $dados['sessoes'] ?? []
        );

        /*
         * A folha de registro entra na pasta do paciente.
         *
         * Antes ela era exigida pela regional 0220, conferida e descartada:
         * nada a gravava, então o comprovante da remessa se perdia assim que a
         * requisição terminava. Guardar depois de confirmar, e não antes, evita
         * deixar arquivo órfão quando a confirmação falha.
         */
        if ($request->hasFile('pdf_registro_sessoes')) {
            $this->guardarRegistroDeSessoes($request->file('pdf_registro_sessoes'), $guia);
        }

        return response()->json([
            'data' => [
                'confirmacao_pendente' => false,
                'cabecalho' => $resultado['cabecalho'],
                'sessoes' => $resultado['sessoes'],
                'registros' => LancamentoResource::collection(collect($resultado['registros']))->resolve(),
            ],
        ], 201);
    }

    public function importarAnalitico(ImportAnaliticoUnimedRequest $request): JsonResponse
    {
        return response()->json([
            'data' => $this->analiticoImportService->previsualizar($request->file('arquivo')),
        ]);
    }

    private function resolverProfissional(int $profissionalId): Profissional
    {
        return Profissional::query()->findOrFail($profissionalId);
    }

    /**
     * Guarda a folha de registro como arquivo do paciente da guia.
     *
     * Mesma pasta e mesmo padrão de nome dos outros anexos (UUID, fora do
     * docroot). O `metadata` amarra o arquivo à guia que originou a remessa —
     * é o que permite, na pasta do paciente, dizer de qual guia cada folha veio.
     */
    private function guardarRegistroDeSessoes(UploadedFile $arquivo, Guia $guia): void
    {
        $path = $arquivo->storeAs(
            "pacientes/{$guia->paciente_id}/registro-sessoes",
            Str::uuid()->toString().'.'.$arquivo->getClientOriginalExtension(),
            'local',
        );

        PacienteArquivo::query()->create([
            'tenant_id' => $guia->tenant_id,
            'paciente_id' => $guia->paciente_id,
            'tipo' => 'registro_sessoes',
            'nome_original' => basename($arquivo->getClientOriginalName()),
            // Do arquivo gravado, nunca do header multipart: o valor volta cru
            // no Content-Type do download.
            'mime' => Storage::disk('local')->mimeType($path) ?: 'application/octet-stream',
            'path' => $path,
            'metadata' => ['guia_id' => $guia->id, 'numero_guia' => $guia->numero_guia],
        ]);
    }

    private function regiaoExigePdf(?string $numeroCartao): bool
    {
        if ($numeroCartao === null || $numeroCartao === '') {
            return false;
        }

        $somenteDigitos = preg_replace('/\D+/', '', $numeroCartao) ?? '';

        return str_starts_with($somenteDigitos, '0220');
    }
}
