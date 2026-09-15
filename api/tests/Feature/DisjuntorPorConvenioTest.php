<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\AutomacaoExecucao;
use App\Models\Convenio;
use App\Models\ConvenioCredencial;
use App\Models\Especialidade;
use App\Models\Guia;
use App\Models\Paciente;
use App\Models\Profissional;
use App\Models\User;
use App\Repositories\ConvenioCredencialRepository;
use App\Services\Automation\AutomationErrorCatalog;
use App\Services\Automation\UnimedCircuitBreakerService;
use App\Support\ConvenioDriverCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Terceiro critério de aceite, e a razão de ser desta change.
 *
 * Em 14/09/2026 um `WORKER_INTERNAL_FATAL` num único item fez o disjuntor
 * pausar a credencial do tenant inteiro — `handleResult()` recebia só o
 * `tenantId` e só existia uma credencial. A automação de todos os itens parou e
 * a credencial precisou ser reativada à mão duas vezes na mesma sessão.
 *
 * Aqui o tenant tem DOIS convênios automatizados, e a falha de um não pode
 * alcançar o outro. O par de cuidados é o que importa: pausar só o certo, e
 * continuar pausando quando deve.
 */
class DisjuntorPorConvenioTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    /**
     * A falha e disparada no SEGUNDO convenio de proposito.
     *
     * O codigo antigo fazia `where('tenant_id')->first()`, que devolve o
     * primeiro convenio do tenant. Se o teste falhasse no primeiro, o codigo
     * antigo passaria por coincidencia — e foi o que aconteceu na primeira
     * versao deste teste. Falhando no segundo, o comportamento antigo pausa a
     * credencial errada e o teste o pega.
     */
    public function test_falha_estrutural_pausa_so_o_convenio_da_execucao(): void
    {
        [$unimed, $outro] = $this->doisConveniosComCredencial();

        $this->disparar($this->execucaoDoConvenio($outro), [
            'status' => 'failed',
            'error_code' => AutomationErrorCatalog::WORKER_INTERNAL_FATAL,
        ]);

        $this->assertFalse($this->credencialDe($outro)->ativo, 'a credencial do convênio que falhou deveria pausar');
        $this->assertTrue($this->credencialDe($unimed)->ativo, 'a credencial do outro convênio não pode ser tocada');
    }

    /** O cuidado oposto: ele precisa continuar pausando quando deve. */
    public function test_falha_estrutural_continua_pausando_com_o_motivo_registrado(): void
    {
        [$unimed] = $this->doisConveniosComCredencial();

        $this->disparar($this->execucaoDoConvenio($unimed), [
            'status' => 'failed',
            'error_code' => AutomationErrorCatalog::PORTAL_STRUCTURE_CHANGED,
        ]);

        $credencial = $this->credencialDe($unimed);

        $this->assertFalse($credencial->ativo);
        $this->assertSame(AutomationErrorCatalog::PORTAL_STRUCTURE_CHANGED, $credencial->automation_paused_reason);
        $this->assertNotNull($credencial->automation_paused_at);
    }

    public function test_falha_nao_estrutural_nao_pausa_nada(): void
    {
        [$unimed, $outro] = $this->doisConveniosComCredencial();

        $this->disparar($this->execucaoDoConvenio($unimed), [
            'status' => 'failed',
            'error_code' => 'GUIA_NEGADA',
        ]);

        $this->assertTrue($this->credencialDe($unimed)->ativo);
        $this->assertTrue($this->credencialDe($outro)->ativo);
    }

    /** A trilha precisa dizer O QUE parou, agora que a pausa não é mais do tenant. */
    public function test_auditoria_da_pausa_nomeia_o_convenio(): void
    {
        [, $outro] = $this->doisConveniosComCredencial();

        $this->disparar($this->execucaoDoConvenio($outro), [
            'status' => 'failed',
            'error_code' => AutomationErrorCatalog::LOGIN_ERROR,
        ]);

        $audit = AuditLog::query()->where('acao', 'unimed_rda.automation_paused')->sole();

        $this->assertSame($outro->id, $audit->payload['convenio_id']);
        $this->assertSame('convenio_credenciais', $audit->entidade);
        $this->assertSame(AutomationErrorCatalog::LOGIN_ERROR, $audit->payload['reason']);
    }

    /**
     * Convênio sem credencial não estoura: a execução falha por outro caminho e
     * o disjuntor simplesmente não tem o que pausar.
     */
    public function test_execucao_de_convenio_sem_credencial_nao_estoura(): void
    {
        [, $outro] = $this->doisConveniosComCredencial();
        ConvenioCredencial::query()->where('convenio_id', $outro->id)->delete();

        $this->disparar($this->execucaoDoConvenio($outro), [
            'status' => 'failed',
            'error_code' => AutomationErrorCatalog::WORKER_INTERNAL_FATAL,
        ]);

        $this->assertDatabaseMissing('audit_logs', ['acao' => 'unimed_rda.automation_paused']);
    }

    /**
     * `CREDENTIAL_MISSING` fica fora dos códigos estruturais de propósito:
     * pausar uma credencial que não existe seria circular.
     */
    public function test_credencial_ausente_nao_e_codigo_estrutural(): void
    {
        $this->assertFalse(
            app(AutomationErrorCatalog::class)->isStructural(AutomationErrorCatalog::CREDENTIAL_MISSING),
        );
    }

    /** O payload do worker é montado com a credencial DAQUELE convênio. */
    public function test_payload_do_worker_usa_a_credencial_do_convenio_da_execucao(): void
    {
        [$unimed, $outro] = $this->doisConveniosComCredencial();

        $repo = app(ConvenioCredencialRepository::class);

        $this->assertSame(
            'login-outro',
            $repo->ativaParaExecucao($this->execucaoDoConvenio($outro))->campo('login'),
        );
        $this->assertSame(
            'login-unimed',
            $repo->ativaParaExecucao($this->execucaoDoConvenio($unimed))->campo('login'),
        );
    }

    private function disparar(AutomacaoExecucao $execucao, array $resultado): void
    {
        app(UnimedCircuitBreakerService::class)->handleResult($execucao, $resultado);
    }

    /** @return array{0: Convenio, 1: Convenio} */
    private function doisConveniosComCredencial(): array
    {
        $tenantId = (int) User::query()->where('email', 'admin@clinica-exemplo.test')->firstOrFail()->tenant_id;

        $unimed = Convenio::query()->where('tenant_id', $tenantId)->where('nome', 'Unimed')->firstOrFail();
        $outro = Convenio::query()->where('tenant_id', $tenantId)->where('nome', 'SC Saúde')->firstOrFail();

        foreach ([[$unimed, 'login-unimed'], [$outro, 'login-outro']] as [$convenio, $login]) {
            ConvenioCredencial::query()->updateOrCreate([
                'tenant_id' => $tenantId,
                'convenio_id' => $convenio->id,
            ], [
                'driver' => ConvenioDriverCatalog::UNIMED_RDA,
                'credenciais' => ['login' => $login, 'password' => 'senha'],
                'ativo' => true,
                'automation_paused_at' => null,
                'automation_paused_reason' => null,
            ]);
        }

        return [$unimed, $outro];
    }

    private function credencialDe(Convenio $convenio): ConvenioCredencial
    {
        return ConvenioCredencial::query()->where('convenio_id', $convenio->id)->sole();
    }

    private function execucaoDoConvenio(Convenio $convenio): AutomacaoExecucao
    {
        $paciente = Paciente::query()->where('tenant_id', $convenio->tenant_id)->firstOrFail();
        $especialidade = Especialidade::query()->where('tenant_id', $convenio->tenant_id)->firstOrFail();
        $profissional = Profissional::query()
            ->where('tenant_id', $convenio->tenant_id)
            ->where('especialidade_id', $especialidade->id)
            ->firstOrFail();

        $guia = Guia::query()->create([
            'tenant_id' => $convenio->tenant_id,
            'convenio_id' => $convenio->id,
            'paciente_id' => $paciente->id,
            'profissional_id' => $profissional->id,
            'especialidade_id' => $especialidade->id,
            'numero_guia' => 'DISJ-'.uniqid(),
            'tipo_terapia' => 'especializada',
            'status' => 'under_review',
            'data_solicitacao' => today(),
        ]);

        return AutomacaoExecucao::query()->create([
            'tenant_id' => $convenio->tenant_id,
            'guia_id' => $guia->id,
            'operacao' => 'consultar_status',
            'status' => 'running',
            'idempotency_key' => 'disj-'.uniqid(),
            'payload' => ['convenio_id' => $convenio->id],
        ]);
    }
}
