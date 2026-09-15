<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateUnimedSettingsRequest;
use App\Http\Resources\UnimedSettingsResource;
use App\Models\Convenio;
use App\Repositories\ConvenioCredencialRepository;
use App\Services\Automation\UnimedWorkerClient;
use App\Support\Auditoria;
use App\Support\ConvenioDriverCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * DEPRECIADO em 15/09/2026. Sai no change de limpeza de
 * `credenciais-por-convenio`, depois de um ciclo em produção.
 *
 * Continua respondendo de propósito. `POST /configuracoes/unimed/reativar` é
 * usado em operação — foi o caminho da reativação manual no incidente de 14/09
 * — e, enquanto estas rotas responderem e `unimed_rda_credentials` existir,
 * reverter o change é voltar o deploy do front.
 *
 * A credencial já vive na estrutura nova: tudo aqui passa pelo
 * `ConvenioCredencialRepository`, e `unimed_rda_credentials` deixou de ser
 * lida. O formato da RESPOSTA é que continua o antigo, porque a tela antiga
 * ainda o consome.
 *
 * O que este controller NÃO delega é a escrita de `convenios.connector_driver`.
 * Ele é o único lugar do sistema que liga e desliga a automação de um convênio,
 * e o controller novo não a faz de propósito — credencial não liga automação.
 * Delegar essa parte faria a capacidade sumir sem aviso, então ela fica aqui
 * até a tela nova ganhar um controle próprio para isso.
 */
class UnimedSettingsController extends Controller
{
    private const DRIVER = ConvenioDriverCatalog::UNIMED_RDA;

    public function __construct(private readonly ConvenioCredencialRepository $credenciais) {}

    public function show(): UnimedSettingsResource
    {
        return $this->resource((int) request()->user()->tenant_id);
    }

    public function update(UpdateUnimedSettingsRequest $request): UnimedSettingsResource
    {
        $tenantId = (int) $request->user()->tenant_id;
        $payload = $request->validated();

        DB::transaction(function () use ($payload, $request, $tenantId) {
            $convenioId = $payload['convenio_id'] ?? null;

            // A escrita do interruptor, preservada como estava: desliga de quem
            // tinha e liga no escolhido.
            Convenio::query()
                ->where('tenant_id', $tenantId)
                ->where('connector_driver', self::DRIVER)
                ->update(['connector_driver' => null]);

            if ($convenioId) {
                Convenio::query()
                    ->where('tenant_id', $tenantId)
                    ->whereKey($convenioId)
                    ->update([
                        'connector_type' => 'scraping',
                        'connector_driver' => self::DRIVER,
                    ]);
            }

            // Sem convênio escolhido não há a que amarrar a credencial: a tela
            // antiga permite salvar só o interruptor, e nesse caso não há o que
            // gravar na estrutura nova.
            if (! $convenioId) {
                return;
            }

            // O `salvar()` já preserva senha em branco, pela mesma regra do
            // catálogo que a tela nova usa.
            $credencial = Auditoria::semRegistroAutomatico(fn () => $this->credenciais->salvar(
                $tenantId,
                (int) $convenioId,
                self::DRIVER,
                $payload['credential'],
            ));

            Auditoria::registrar(
                acao: 'unimed_rda_settings.updated',
                entidade: 'convenio_credenciais',
                entidadeId: (int) $credencial->id,
                payload: array_filter([
                    'convenio_id' => $convenioId,
                    'login' => $credencial->campo('login'),
                    'base_url' => $credencial->campo('base_url'),
                    'ativo' => $credencial->ativo,
                    // A senha nunca entra, nem a antiga nem a nova: fica
                    // registrado que mudou, e quem mudou vem do autor.
                    'campos_ocultos' => filled($payload['credential']['password'] ?? null) ? ['password'] : null,
                ], fn ($valor) => $valor !== null),
                tenantId: $tenantId,
                userId: $request->user()?->id,
            );
        });

        return $this->resource($tenantId);
    }

    public function health(UnimedWorkerClient $worker): JsonResponse
    {
        try {
            return response()->json([
                'data' => ['status' => 'available', 'worker' => $worker->health()],
            ]);
        } catch (Throwable) {
            return response()->json([
                'data' => ['status' => 'unavailable', 'worker' => null],
            ]);
        }
    }

    public function reativar(): UnimedSettingsResource
    {
        $tenantId = (int) request()->user()->tenant_id;
        $credencial = $this->credenciais->paraConvenio($tenantId, $this->convenioDoDriver($tenantId));

        abort_if($credencial === null, 404);

        // Mesma razão do update: o evento explícito diz "automação reativada",
        // que é o que o operador procura na trilha; o diff diria apenas
        // `ativo: false -> true`.
        Auditoria::semRegistroAutomatico(fn () => $credencial->forceFill([
            'ativo' => true,
            'automation_paused_at' => null,
            'automation_paused_reason' => null,
        ])->save());

        Auditoria::registrar(
            acao: 'unimed_rda.automation_reactivated',
            entidade: 'convenio_credenciais',
            entidadeId: (int) $credencial->id,
            payload: ['reativado' => true],
            tenantId: $tenantId,
        );

        return $this->resource($tenantId);
    }

    private function convenioDoDriver(int $tenantId): ?int
    {
        return Convenio::query()
            ->where('tenant_id', $tenantId)
            ->where('connector_driver', self::DRIVER)
            ->value('id');
    }

    /**
     * Devolve a credencial nova no formato antigo.
     *
     * A tela velha espera `login`, `base_url`, `nome_contratado` e
     * `senha_configurada` soltos; o modelo novo os guarda no JSON do driver.
     * A tradução fica aqui, e some junto com o controller.
     */
    private function resource(int $tenantId): UnimedSettingsResource
    {
        $convenios = Convenio::query()
            ->where('tenant_id', $tenantId)
            ->orderBy('nome')
            ->get();

        $convenioId = $convenios->firstWhere('connector_driver', self::DRIVER)?->id;
        $credencial = $this->credenciais->paraConvenio($tenantId, $convenioId);

        return new UnimedSettingsResource([
            'credential' => $credencial ? (object) [
                'id' => $credencial->id,
                'login' => $credencial->campo('login'),
                'base_url' => $credencial->campo('base_url'),
                'nome_contratado' => $credencial->campo('nome_contratado'),
                'ativo' => $credencial->ativo,
                'password' => $credencial->campo('password'),
                'automation_paused_at' => $credencial->automation_paused_at,
                'automation_paused_reason' => $credencial->automation_paused_reason,
            ] : null,
            'convenio_id' => $convenioId,
            'convenios' => $convenios,
        ]);
    }
}
