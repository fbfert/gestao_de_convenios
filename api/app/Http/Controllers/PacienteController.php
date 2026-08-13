<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePacienteRequest;
use App\Http\Requests\UpdatePacienteRequest;
use App\Http\Resources\PacienteResource;
use App\Http\Requests\AnalyzeCarteirinhaRequest;
use App\Models\ConfiguracaoGlobal;
use App\Models\Paciente;
use App\Models\PacienteDocumento;
use App\Models\PacienteTelefone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use App\Services\CarteirinhaAiService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PacienteController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $busca = trim((string) $request->string('busca'));
        $convenioId = $request->integer('convenio_id');

        return PacienteResource::collection(
            Paciente::query()
                ->with(['convenio', 'telefones'])
                ->when($convenioId, fn ($query) => $query->where('convenio_id', $convenioId))
                ->when($busca !== '', function ($query) use ($busca) {
                    $digitos = preg_replace('/\D+/', '', $busca);

                    $query->where(function ($nested) use ($busca, $digitos) {
                        $nested->where('nome', 'like', "%{$busca}%")
                            ->orWhere('carteirinha', 'like', "%{$busca}%");

                        // Busca por CPF e telefone digitados com ou sem
                        // mascara: os dois sao guardados so com digitos.
                        if ($digitos !== '') {
                            $nested->orWhere('cpf', 'like', "%{$digitos}%")
                                ->orWhereHas('telefones', fn ($t) => $t->where('numero', 'like', "%{$digitos}%"));
                        }
                    });
                })
                ->orderBy('nome')
                ->get()
        );
    }

    public function show(Paciente $paciente): PacienteResource
    {
        $paciente->load(['convenio', 'telefones']);

        return new PacienteResource($paciente);
    }

    public function store(StorePacienteRequest $request): JsonResponse
    {
        $dados = $this->normalizarCarteirinha($request->validated(), $request->user()->tenant_id);
        $telefones = $dados['telefones'] ?? null;
        unset($dados['telefones']);

        $paciente = DB::transaction(function () use ($dados, $telefones, $request) {
            $paciente = Paciente::query()->create([
                ...$dados,
                'tenant_id' => $request->user()->tenant_id,
                'ativo' => $request->boolean('ativo', true),
            ]);

            $this->sincronizarTelefones($paciente, $telefones);
            $this->adotarDocumento($paciente, $request->input('carteirinha_documento_id'));

            return $paciente;
        });

        $paciente->load(['convenio', 'telefones']);

        return (new PacienteResource($paciente))->response()->setStatusCode(201);
    }

    public function update(UpdatePacienteRequest $request, Paciente $paciente): PacienteResource
    {
        $dados = $this->normalizarCarteirinha($request->validated(), $request->user()->tenant_id, $paciente);
        $telefones = $dados['telefones'] ?? null;
        unset($dados['telefones']);

        DB::transaction(function () use ($paciente, $dados, $telefones, $request) {
            $paciente->fill($dados);
            $paciente->save();

            $this->sincronizarTelefones($paciente, $telefones);
            $this->adotarDocumento($paciente, $request->input('carteirinha_documento_id'));
        });

        $paciente->load(['convenio', 'telefones']);

        return new PacienteResource($paciente);
    }

    /**
     * Lê a carteirinha e devolve o que extraiu, sem gravar cadastro nenhum.
     *
     * A imagem é guardada já com data de expiração e sem dono: se o operador
     * desistir do cadastro, ela desaparece no expurgo diário sem deixar
     * documento pessoal órfão no servidor.
     */
    public function lerCarteirinha(AnalyzeCarteirinhaRequest $request, CarteirinhaAiService $carteirinhaAi): JsonResponse
    {
        $tenantId = (int) $request->user()->tenant_id;
        $arquivo = $request->file('arquivo');
        $nome = Str::uuid()->toString().'.'.$arquivo->getClientOriginalExtension();
        $path = $arquivo->storeAs("carteirinhas/{$tenantId}", $nome, 'local');

        $resultado = $carteirinhaAi->analisar($tenantId, $arquivo, $path);

        $dias = ConfiguracaoGlobal::doTenant($tenantId)->carteirinha_retencao_dias ?: 30;

        $documento = PacienteDocumento::query()->create([
            'tenant_id' => $tenantId,
            'paciente_id' => null,
            'tipo' => 'carteirinha',
            'path' => $path,
            'mime' => $arquivo->getMimeType(),
            'nome_original' => $arquivo->getClientOriginalName(),
            'expira_em' => now()->addDays($dias),
        ]);

        return response()->json([
            'data' => [
                'documento_id' => $documento->id,
                'expira_em' => $documento->expira_em->toDateString(),
                ...$resultado,
            ],
        ]);
    }

    /**
     * Regrava a lista inteira de telefones.
     *
     * Substituir em bloco, e nao casar item a item, porque a tela manda a
     * lista completa: um telefone removido la precisa sumir aqui, e nao ha id
     * estavel para reaproveitar. `null` significa "o request nao falou de
     * telefone", e ai nada e tocado — importante no PATCH parcial.
     */
    private function sincronizarTelefones(Paciente $paciente, ?array $telefones): void
    {
        if ($telefones === null) {
            return;
        }

        $paciente->telefones()->delete();

        $temPrincipal = false;

        foreach (array_values($telefones) as $ordem => $telefone) {
            // So o primeiro marcado vale: dois principais deixariam a tela sem
            // saber qual numero mostrar na listagem.
            $principal = ! $temPrincipal && (bool) ($telefone['principal'] ?? false);
            $temPrincipal = $temPrincipal || $principal;

            PacienteTelefone::query()->create([
                'tenant_id' => $paciente->tenant_id,
                'paciente_id' => $paciente->id,
                'numero' => $telefone['numero'],
                'rotulo' => $telefone['rotulo'] ?? 'celular',
                'contato_nome' => $telefone['contato_nome'] ?? null,
                'principal' => $principal,
                'ordem' => $ordem,
            ]);
        }

        // Ninguem marcou: o primeiro da lista vira principal, para a listagem
        // sempre ter um numero para mostrar.
        if (! $temPrincipal) {
            $paciente->telefones()->orderBy('ordem')->first()?->update(['principal' => true]);
        }
    }

    /**
     * Vincula ao paciente a imagem lida antes de ele existir.
     *
     * O documento nasce solto na leitura; se o cadastro nunca for gravado, ele
     * expira sozinho pelo expurgo diario.
     */
    private function adotarDocumento(Paciente $paciente, mixed $documentoId): void
    {
        if (! $documentoId) {
            return;
        }

        PacienteDocumento::query()
            ->whereKey((int) $documentoId)
            ->whereNull('paciente_id')
            ->update(['paciente_id' => $paciente->id]);
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
