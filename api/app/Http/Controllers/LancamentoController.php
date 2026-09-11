<?php

namespace App\Http\Controllers;

use App\Http\Requests\ImportAnaliticoUnimedRequest;
use App\Http\Requests\ImportLancamentosTranscricaoRequest;
use App\Http\Requests\LerRegistroSessoesRequest;
use App\Http\Requests\StoreLancamentoRequest;
use App\Http\Requests\UpdateLancamentoRequest;
use App\Http\Resources\LancamentoResource;
use App\Models\Guia;
use App\Models\Lancamento;
use App\Models\Profissional;
use App\Services\AnaliticoUnimedImportService;
use App\Services\LancamentoService;
use App\Services\RegistroSessoesAiService;
use Illuminate\Support\Str;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

class LancamentoController extends Controller
{
    public function __construct(
        private readonly LancamentoService $service,
        private readonly AnaliticoUnimedImportService $analiticoImportService
    ) {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        return LancamentoResource::collection(
            $this->service->listar($request->only(['profissional_id', 'data_sessao']), (int) $request->integer('per_page', 15))
        );
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

    private function regiaoExigePdf(?string $numeroCartao): bool
    {
        if ($numeroCartao === null || $numeroCartao === '') {
            return false;
        }

        $somenteDigitos = preg_replace('/\D+/', '', $numeroCartao) ?? '';

        return str_starts_with($somenteDigitos, '0220');
    }
}
