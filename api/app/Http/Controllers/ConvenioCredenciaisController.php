<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateConvenioCredencialRequest;
use App\Http\Resources\ConvenioCredencialResource;
use App\Models\Convenio;
use App\Repositories\ConvenioCredencialRepository;
use App\Services\Automation\UnimedWorkerClient;
use App\Support\Auditoria;
use App\Support\ConvenioDriverCatalog;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * Credenciais de automação, uma por convênio.
 *
 * Substitui a aba "Unimed RDA" de Configurações. A diferença que importa: aqui
 * NADA liga automação. Gravar credencial não mexe em
 * `convenios.connector_driver`, que continua sendo o interruptor — com doze
 * consumidores, entre eles o `VerificarGuiasDiarioJob`, que deixa de conferir
 * manualmente as guias do convênio quando está ligado. Cadastrar o SC Saúde
 * aqui não pode acionar uma automação que não existe.
 *
 * O `{convenio}` chega resolvido pelo binding, que já confere o tenant desde
 * 14/09 (`BelongsToTenant::resolveRouteBinding`); não há checagem repetida.
 */
class ConvenioCredenciaisController extends Controller
{
    public function __construct(private readonly ConvenioCredencialRepository $credenciais) {}

    /** Todos os convênios do tenant, cada um com o estado da sua credencial. */
    public function index(): JsonResponse
    {
        $tenantId = (int) request()->user()->tenant_id;

        $convenios = Convenio::query()
            ->where('tenant_id', $tenantId)
            ->orderBy('nome')
            ->get();

        return response()->json([
            'data' => $convenios
                ->map(fn (Convenio $convenio) => new ConvenioCredencialResource([
                    'convenio' => $convenio,
                    'credencial' => $this->credenciais->paraConvenio($tenantId, $convenio->id),
                    'driver' => $this->driverDe($tenantId, $convenio),
                ]))
                ->map(fn (ConvenioCredencialResource $recurso) => $recurso->resolve())
                ->values(),
            'meta' => ['drivers' => array_map(
                fn (string $driver) => ConvenioDriverCatalog::paraResposta($driver),
                ConvenioDriverCatalog::drivers(),
            )],
        ]);
    }

    public function update(UpdateConvenioCredencialRequest $request, Convenio $convenio): ConvenioCredencialResource
    {
        $tenantId = (int) $request->user()->tenant_id;
        $dados = $request->validated();

        // O evento explícito abaixo diz qual convênio e quais campos foram
        // informados; o diff cru do model diria apenas que `credenciais` mudou,
        // já que o campo inteiro é oculto. O automático é suspenso para a mesma
        // ação não virar dois registros.
        $credencial = Auditoria::semRegistroAutomatico(fn () => $this->credenciais->salvar(
            $tenantId,
            (int) $convenio->id,
            $dados['driver'],
            (array) ($dados['credenciais'] ?? []),
        ));

        Auditoria::registrar(
            acao: 'convenio_credencial.updated',
            entidade: 'convenio_credenciais',
            entidadeId: (int) $credencial->id,
            payload: [
                'convenio_id' => $convenio->id,
                'driver' => $credencial->driver,
                'ativo' => $credencial->ativo,
                // Nenhum valor entra, nem o antigo nem o novo: fica registrado
                // que mudou, e quem mudou vem do autor.
                'campos_informados' => array_values(array_filter(
                    array_keys((array) ($dados['credenciais'] ?? [])),
                    fn (string $chave) => filled($dados['credenciais'][$chave] ?? null),
                )),
            ],
            tenantId: $tenantId,
            userId: $request->user()?->id,
        );

        return $this->recurso($tenantId, $convenio);
    }

    public function health(UnimedWorkerClient $worker, Convenio $convenio): JsonResponse
    {
        $tenantId = (int) request()->user()->tenant_id;
        $credencial = $this->credenciais->paraConvenio($tenantId, $convenio->id);

        // Só o driver que tem worker responde saúde. Para os demais — o
        // `scsaude` enquanto não há automação — a resposta é honesta: não há o
        // que consultar, e isso não é falha.
        if ($credencial?->driver !== ConvenioDriverCatalog::UNIMED_RDA) {
            return response()->json([
                'data' => ['status' => 'not_applicable', 'worker' => null],
            ]);
        }

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

    /**
     * Retoma a automação de UM convênio depois de uma pausa do disjuntor.
     *
     * O caminho da reativação manual em 14/09 — e a razão de ele existir por
     * convênio agora: naquele dia a pausa alcançava o tenant inteiro, e
     * reativar devolvia o que não tinha sido pausado por mérito próprio.
     */
    public function reativar(Convenio $convenio): ConvenioCredencialResource
    {
        $tenantId = (int) request()->user()->tenant_id;
        $credencial = $this->credenciais->paraConvenio($tenantId, $convenio->id);

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
            acao: 'convenio_credencial.automation_reactivated',
            entidade: 'convenio_credenciais',
            entidadeId: (int) $credencial->id,
            payload: ['convenio_id' => $convenio->id, 'reativado' => true],
            tenantId: $tenantId,
        );

        return $this->recurso($tenantId, $convenio);
    }

    /**
     * Driver a oferecer para o convênio.
     *
     * Ordem: o da credencial já gravada; senão o `connector_driver`, quando ele
     * é um driver conhecido do catálogo; senão nenhum, e a tela pede que se
     * escolha. Ler o `connector_driver` aqui é só palpite de formulário — não o
     * altera nem depende dele.
     */
    private function driverDe(int $tenantId, Convenio $convenio): ?string
    {
        $credencial = $this->credenciais->paraConvenio($tenantId, $convenio->id);

        if ($credencial) {
            return $credencial->driver;
        }

        return ConvenioDriverCatalog::existe($convenio->connector_driver)
            ? $convenio->connector_driver
            : null;
    }

    private function recurso(int $tenantId, Convenio $convenio): ConvenioCredencialResource
    {
        return new ConvenioCredencialResource([
            'convenio' => $convenio->refresh(),
            'credencial' => $this->credenciais->paraConvenio($tenantId, $convenio->id),
            'driver' => $this->driverDe($tenantId, $convenio),
        ]);
    }
}
