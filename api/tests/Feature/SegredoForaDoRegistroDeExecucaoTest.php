<?php

namespace Tests\Feature;

use App\Models\AutomacaoExecucao;
use App\Models\Convenio;
use App\Models\ConvenioCredencial;
use App\Models\Guia;
use App\Models\User;
use App\Repositories\ConvenioCredencialRepository;
use App\Services\Automation\AutomationPayloadRedactor;
use App\Services\Automation\ConsultarStatusUnimedService;
use App\Support\ConvenioDriverCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Quinto critério de aceite, e a trava que fechou a exfiltração da senha do
 * portal em 14/09.
 *
 * O desenho é a separação entre `payloadPersistido()` — o que vai para
 * `automacao_execucoes.payload`, sem credencial — e `payloadParaWorker()`, que
 * monta a credencial na hora do envio e não a guarda. Era decisão de código sem
 * requisito escrito; virou cenário de spec nesta change, e este teste é o que
 * impede a implementação nova de desfazê-la sem perceber.
 */
class SegredoForaDoRegistroDeExecucaoTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private const SENHA = 'senha-do-portal-que-nao-pode-vazar';

    public function test_senha_nao_entra_no_payload_persistido_da_execucao(): void
    {
        $execucao = $this->execucaoComCredencial();

        // O payload para o worker TEM a senha — é o que o worker precisa.
        $paraWorker = app(ConsultarStatusUnimedService::class)->payloadParaWorker($execucao);
        $this->assertSame(self::SENHA, $paraWorker['credential']['password']);

        // O que ficou gravado, não. Conferido na coluna crua, e não pelo model:
        // um cast poderia esconder o vazamento.
        $gravado = DB::table('automacao_execucoes')->where('id', $execucao->id)->value('payload');

        $this->assertStringNotContainsString(self::SENHA, (string) $gravado);
        $this->assertArrayNotHasKey('credential', json_decode((string) $gravado, true) ?? []);
    }

    public function test_senha_nao_aparece_nos_eventos_da_execucao(): void
    {
        $execucao = $this->execucaoComCredencial();
        $paraWorker = app(ConsultarStatusUnimedService::class)->payloadParaWorker($execucao);

        // Simula o que o worker client registra ao enviar.
        $execucao->eventos()->create([
            'tenant_id' => $execucao->tenant_id,
            'tipo' => 'request',
            'registrado_em' => now(),
            'payload' => app(AutomationPayloadRedactor::class)->redact($paraWorker),
        ]);

        foreach (DB::table('automacao_eventos')->pluck('payload') as $payload) {
            $this->assertStringNotContainsString(self::SENHA, (string) $payload);
        }
    }

    public function test_senha_nao_aparece_na_resposta_de_automacoes(): void
    {
        $execucao = $this->execucaoComCredencial();
        $paraWorker = app(ConsultarStatusUnimedService::class)->payloadParaWorker($execucao);

        $execucao->eventos()->create([
            'tenant_id' => $execucao->tenant_id,
            'tipo' => 'request',
            'registrado_em' => now(),
            'payload' => app(AutomationPayloadRedactor::class)->redact($paraWorker),
        ]);

        Sanctum::actingAs(User::query()->where('email', 'admin@clinica-exemplo.test')->firstOrFail());

        $this->assertStringNotContainsString(
            self::SENHA,
            $this->getJson("/api/automacoes/{$execucao->id}")->assertOk()->getContent(),
        );
        $this->assertStringNotContainsString(
            self::SENHA,
            $this->getJson('/api/automacoes')->assertOk()->getContent(),
        );
    }

    /** O repositório é o único caminho até o segredo, e só na hora do envio. */
    public function test_repositorio_e_o_unico_ponto_que_devolve_o_segredo(): void
    {
        $execucao = $this->execucaoComCredencial();

        $this->assertSame(
            self::SENHA,
            app(ConvenioCredencialRepository::class)->ativaParaExecucao($execucao)->campo('password'),
        );
    }

    private function execucaoComCredencial(): AutomacaoExecucao
    {
        $user = User::query()->where('email', 'admin@clinica-exemplo.test')->firstOrFail();
        $tenantId = (int) $user->tenant_id;

        $convenio = Convenio::query()->where('tenant_id', $tenantId)->where('nome', 'Unimed')->firstOrFail();

        ConvenioCredencial::query()->updateOrCreate([
            'tenant_id' => $tenantId,
            'convenio_id' => $convenio->id,
        ], [
            'driver' => ConvenioDriverCatalog::UNIMED_RDA,
            'credenciais' => [
                'login' => 'operador',
                'password' => self::SENHA,
                'base_url' => 'https://portal.unimed.test',
            ],
            'ativo' => true,
        ]);

        $guia = Guia::query()->where('tenant_id', $tenantId)->where('convenio_id', $convenio->id)->firstOrFail();

        return AutomacaoExecucao::query()->create([
            'tenant_id' => $tenantId,
            'guia_id' => $guia->id,
            'operacao' => ConsultarStatusUnimedService::OPERATION,
            'status' => 'running',
            'idempotency_key' => 'segredo-'.uniqid(),
            // O payload persistido, como o service o monta: sem credencial.
            'payload' => ['guia_id' => $guia->id, 'numero_guia' => $guia->numero_guia],
        ]);
    }
}
