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
use App\Models\Profissional;
use App\Services\AnaliticoUnimedImportService;
use App\Services\LancamentoService;
use App\Services\RegistroSessoesAiService;
use App\Services\Sessoes\FolhasDeRegistroService;
use App\Support\Auditoria;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class LancamentoController extends Controller
{
    public function __construct(
        private readonly LancamentoService $service,
        private readonly AnaliticoUnimedImportService $analiticoImportService,
        private readonly FolhasDeRegistroService $folhas,
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
                    'senha' => $grupo['guia']->senha,
                    'validade_senha' => $grupo['guia']->validade_senha?->toDateString(),
                    'paciente_nome' => $grupo['guia']->paciente?->nome,
                    'medico_nome' => $grupo['guia']->solicitacaoItem?->solicitacao?->medico?->nome,
                    // Decide qual finalização a tela oferece: guia de convênio
                    // com automação é finalizada NA OPERADORA, não à mão.
                    'connector_driver' => $grupo['guia']->convenio?->connector_driver,
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
     *
     * Não recebe guia, de propósito. A folha é que diz de qual guia ela é — o
     * cabeçalho lido traz `guia_numero` —, e exigir a guia antes obrigaria o
     * operador a procurar à mão exatamente o que a IA leria em seguida. O
     * parâmetro existia na rota antiga sem nunca chegar ao serviço: era route
     * binding e nada mais.
     */
    public function lerRegistroSessoes(
        LerRegistroSessoesRequest $request,
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

    /**
     * Confere a grade contra as regras de agenda sem gravar.
     *
     * A grade chama isto enquanto o operador digita, para marcar a linha em
     * conflito na hora. A conferência de verdade continua acontecendo na
     * confirmação — esta é só a que dá retorno cedo, e usa exatamente o mesmo
     * avaliador, para não existirem duas versões da regra.
     */
    public function conferirAgenda(Request $request, Guia $guia): JsonResponse
    {
        $dados = $request->validate([
            // Recortado pela clínica: `exists` cru distinguiria "não existe" de
            // "não é sua", e a diferença enumera a base da vizinha (ver o trait
            // ExisteNaClinica).
            'profissional_id' => [
                'nullable',
                'integer',
                Rule::exists('profissionais', 'id')->where(
                    fn ($query) => $query->where('tenant_id', $request->user()?->tenant_id)
                ),
            ],
            'sessoes' => ['array'],
            'sessoes.*.data_sessao' => ['nullable', 'date'],
            'sessoes.*.hora_inicio' => ['nullable', 'date_format:H:i'],
        ]);

        $resultado = $this->service->conferirAgenda(
            $guia,
            isset($dados['profissional_id']) ? (int) $dados['profissional_id'] : null,
            $dados['sessoes'] ?? [],
        );

        return response()->json(['data' => $resultado->toArray()]);
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

        // UMA folha basta para confirmar, ainda que a guia tenha sido
        // preenchida em mais de uma via: as outras podem ser anexadas depois,
        // antes de finalizar na operadora.
        $folhas = $this->folhasEnviadas($request);

        if ($this->folhas->regiaoExigeFolha($numeroCartao) && $folhas === []) {
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
         * As folhas de registro entram na pasta do paciente.
         *
         * Antes a folha era exigida pela regional 0220, conferida e
         * descartada: nada a gravava, então o comprovante da remessa se
         * perdia assim que a requisição terminava. Guardar depois de
         * confirmar, e não antes, evita deixar arquivo órfão quando a
         * confirmação falha.
         */
        if ($folhas !== []) {
            $this->folhas->anexar($guia, $folhas);
        }

        /*
         * Lançou apesar de a folha contradizer a guia.
         *
         * Registrado depois de gravar, e contra a GUIA: é a cota dela que foi
         * consumida, e é olhando o histórico dela que alguém vai perguntar,
         * meses depois, por que essas sessões estão aqui. O evento responde
         * isso com quem decidiu, o que não fechava e o motivo dado.
         *
         * Sem tabela nova: uma decisão pontual é exatamente o que a trilha de
         * auditoria guarda.
         */
        if (filled($dados['divergencia'] ?? null)) {
            Auditoria::registrar(
                acao: 'lancamento_divergencia_confirmada',
                entidade: 'guias',
                entidadeId: $guia->id,
                payload: [
                    'divergencia' => $dados['divergencia'],
                    'justificativa' => $dados['divergencia_justificativa'],
                    'sessoes_gravadas' => count($resultado['registros']),
                ],
                comOrigem: true,
            );
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
     * As folhas do envio, aceitas tanto como arquivo único quanto como lista.
     *
     * Uma guia de dez sessões costuma vir em duas vias impressas, preenchidas
     * em partes — daí a lista. O arquivo único continua valendo porque é o que
     * as telas antigas mandam, e quebrar isso não traria nada.
     *
     * @return array<int, UploadedFile>
     */
    private function folhasEnviadas(Request $request): array
    {
        $enviado = $request->file('pdf_registro_sessoes');

        if ($enviado === null) {
            return [];
        }

        return array_values(array_filter(
            is_array($enviado) ? $enviado : [$enviado],
            fn ($arquivo) => $arquivo instanceof UploadedFile,
        ));
    }
}
