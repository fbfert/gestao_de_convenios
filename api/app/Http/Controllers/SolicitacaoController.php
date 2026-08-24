<?php

namespace App\Http\Controllers;

use App\Http\Requests\AnalyzePedidoMedicoRequest;
use App\Http\Requests\MutateSolicitacaoStatusRequest;
use App\Http\Requests\StoreSolicitacaoRequest;
use App\Http\Requests\UpdateSolicitacaoRequest;
use App\Http\Requests\UpdateSolicitacaoStatusRequest;
use App\Http\Resources\SolicitacaoResource;
use App\Models\Cid;
use App\Models\Convenio;
use App\Models\Especialidade;
use App\Models\Medico;
use App\Models\Paciente;
use App\Models\Solicitacao;
use App\Models\SolicitacaoItem;
use App\Services\Automation\GerarGuiaUnimedService;
use App\Services\PedidoMedicoAiService;
use App\Services\SolicitacaoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SolicitacaoController extends Controller
{
    public function __construct(
        private readonly SolicitacaoService $service
    ) {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        return SolicitacaoResource::collection(
            $this->service->listar($request->only(['status', 'convenio_id', 'medico_id']), (int) $request->integer('per_page', 15))
        );
    }

    public function store(StoreSolicitacaoRequest $request): JsonResponse
    {
        return (new SolicitacaoResource($this->service->criar($request->validated())->load(['paciente', 'profissional', 'especialidade', 'convenio', 'medico', 'cidCadastro', 'itens.especialidade.convenioMapeamentos', 'itens.profissional', 'itens.documentos', 'itens.guia', 'itens.automacaoExecucoes', 'documentos', 'guia'])))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Solicitacao $solicitacao): SolicitacaoResource
    {
        return new SolicitacaoResource($solicitacao->load(['paciente', 'profissional', 'especialidade', 'convenio', 'medico', 'cidCadastro', 'itens.especialidade.convenioMapeamentos', 'itens.profissional', 'itens.documentos', 'itens.guia', 'itens.automacaoExecucoes', 'documentos', 'guia.paciente', 'guia.convenio', 'guia.profissional', 'guia.especialidade', 'guia.solicitacaoItem.especialidade', 'guia.solicitacaoItem.profissional', 'guia.antecipacoes', 'guia.conciliacoes']));
    }

    public function update(UpdateSolicitacaoRequest $request, Solicitacao $solicitacao): SolicitacaoResource
    {
        return new SolicitacaoResource($this->service->atualizar($solicitacao, $request->validated())->load(['paciente', 'profissional', 'especialidade', 'convenio', 'medico', 'cidCadastro', 'itens.especialidade.convenioMapeamentos', 'itens.profissional', 'itens.documentos', 'itens.guia', 'itens.automacaoExecucoes', 'documentos', 'guia']));
    }

    public function aprovar(MutateSolicitacaoStatusRequest $request, Solicitacao $solicitacao): SolicitacaoResource
    {
        return new SolicitacaoResource($this->service->aprovar($solicitacao)->load(['paciente', 'profissional', 'especialidade', 'convenio', 'medico', 'cidCadastro', 'itens.especialidade.convenioMapeamentos', 'itens.profissional', 'itens.documentos', 'itens.guia', 'itens.automacaoExecucoes', 'documentos', 'guia']));
    }

    public function negar(MutateSolicitacaoStatusRequest $request, Solicitacao $solicitacao): SolicitacaoResource
    {
        return new SolicitacaoResource($this->service->negar($solicitacao)->load(['paciente', 'profissional', 'especialidade', 'convenio', 'medico', 'cidCadastro', 'itens.especialidade.convenioMapeamentos', 'itens.profissional', 'itens.documentos', 'itens.guia', 'itens.automacaoExecucoes', 'documentos', 'guia']));
    }

    public function enviarItemUnimed(
        Request $request,
        SolicitacaoItem $solicitacaoItem,
        GerarGuiaUnimedService $gerarGuiaUnimed,
    ): JsonResponse {
        $execucao = $gerarGuiaUnimed->enviar($solicitacaoItem);

        return response()->json([
            'data' => [
                'id' => $execucao->id,
                'status' => $execucao->status,
                'operacao' => $execucao->operacao,
                'solicitacao_item_id' => $execucao->solicitacao_item_id,
                'queued_at' => $execucao->queued_at?->toISOString(),
            ],
        ], 202);
    }

    public function updateStatus(UpdateSolicitacaoStatusRequest $request, Solicitacao $solicitacao): SolicitacaoResource
    {
        return new SolicitacaoResource($this->service->alterarStatus($solicitacao, $request->validated('status'))->load(['paciente', 'profissional', 'especialidade', 'convenio', 'medico', 'cidCadastro', 'itens.especialidade.convenioMapeamentos', 'itens.profissional', 'itens.documentos', 'itens.guia', 'itens.automacaoExecucoes', 'documentos', 'guia']));
    }

    public function analisarPedidoMedico(AnalyzePedidoMedicoRequest $request, PedidoMedicoAiService $pedidoMedicoAi): JsonResponse
    {
        $tenantId = (int) $request->user()->tenant_id;
        $arquivo = $request->file('arquivo');
        $filename = Str::uuid()->toString().'.'.$arquivo->getClientOriginalExtension();
        $path = $arquivo->storeAs("pedidos-medicos/pendentes/{$tenantId}", $filename, 'local');
        $resultado = $pedidoMedicoAi->analisar($tenantId, $arquivo, $path);

        return response()->json([
            'data' => [
                'upload_id' => $path,
                'arquivo' => [
                    'nome_original' => $arquivo->getClientOriginalName(),
                    'mime' => $arquivo->getMimeType(),
                ],
                ...$resultado,
            ],
        ]);
    }

    public function pedidoMedico(Solicitacao $solicitacao)
    {
        abort_unless($solicitacao->pedido_medico_path && Storage::disk('local')->exists($solicitacao->pedido_medico_path), 404);

        return response()->file(Storage::disk('local')->path($solicitacao->pedido_medico_path), [
            'Content-Type' => $solicitacao->pedido_medico_mime ?? 'application/octet-stream',
        ]);
    }

    public function storePacienteRapido(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nome' => ['required', 'string', 'max:255'],
            'convenio_id' => ['required', 'integer', 'exists:convenios,id'],
            'carteirinha' => ['required', 'string', 'max:255'],
        ]);

        $convenio = Convenio::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->whereKey($validated['convenio_id'])
            ->firstOrFail();

        // O cadastro rápido gerava uma carteirinha fictícia, e o paciente nascia
        // inutilizável para a Unimed. Aqui vale a mesma regra do cadastro completo.
        if ($convenio->connector_driver === 'unimed_rda') {
            $digitos = preg_replace('/\D+/', '', $validated['carteirinha']);

            if (strlen($digitos) !== 17) {
                throw ValidationException::withMessages([
                    'carteirinha' => ['A carteirinha Unimed deve conter 17 dígitos nos blocos 4+4+6+2+1.'],
                ]);
            }

            $validated['carteirinha'] = $digitos;
        }

        $paciente = Paciente::query()->create([
            'tenant_id' => $request->user()->tenant_id,
            'nome' => $validated['nome'],
            'convenio_id' => $validated['convenio_id'],
            'carteirinha' => $validated['carteirinha'],
            'ativo' => true,
        ]);

        return response()->json(['data' => $paciente], 201);
    }

    public function storeEspecialidadeRapida(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nome' => ['required', 'string', 'max:255'],
        ]);

        $especialidade = Especialidade::query()->firstOrCreate([
            'tenant_id' => $request->user()->tenant_id,
            'nome' => trim($validated['nome']),
        ], [
            'ativo' => true,
        ]);

        return response()->json(['data' => $especialidade], 201);
    }

    public function storeMedicoRapido(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nome' => ['required', 'string', 'max:255'],
        ]);

        $medico = Medico::query()->create([
            'tenant_id' => $request->user()->tenant_id,
            'nome' => $validated['nome'],
            'crm' => 'PENDENTE',
            'especialidade_medica' => 'Pendente',
            'telefone' => 'Pendente',
            'ativo' => true,
        ]);

        return response()->json(['data' => $medico], 201);
    }

    public function storeCidRapido(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'codigo' => ['required', 'string', 'max:20'],
            'descricao' => ['required', 'string', 'max:255'],
        ]);

        $cid = Cid::query()->firstOrCreate([
            'tenant_id' => $request->user()->tenant_id,
            'codigo' => trim($validated['codigo']),
        ], [
            'descricao' => trim($validated['descricao']),
            'ativo' => true,
        ]);

        return response()->json(['data' => $cid], 201);
    }
}
