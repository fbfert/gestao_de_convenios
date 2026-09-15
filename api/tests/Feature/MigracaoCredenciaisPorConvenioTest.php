<?php

namespace Tests\Feature;

use App\Models\Convenio;
use App\Models\ConvenioCredencial;
use App\Models\Tenant;
use App\Support\ConvenioDriverCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * A migração de dados que copia `unimed_rda_credentials` para
 * `convenio_credenciais`.
 *
 * Existe um único tenant em produção, com a automação da Unimed rodando em
 * guias reais todos os dias. O critério é o primeiro da lista de aceite: a
 * credencial existente continua funcionando depois da migração, sem novo
 * cadastro. Por isso o teste roda a classe da migration de verdade, e não uma
 * reimplementação do que ela deveria fazer.
 */
class MigracaoCredenciaisPorConvenioTest extends TestCase
{
    use RefreshDatabase;

    private const ARQUIVO = __DIR__.'/../../database/migrations/2026_09_15_100001_migrar_credenciais_unimed_para_convenio.php';

    public function test_credencial_existente_e_copiada_com_os_valores_legiveis(): void
    {
        $tenant = $this->tenant('Clínica Migração');
        $convenio = $this->convenio($tenant, 'Unimed Migração', ConvenioDriverCatalog::UNIMED_RDA);

        $this->credencialAntiga($tenant->id, [
            'login' => 'usuario.rda',
            'password' => Crypt::encryptString('senha-do-portal'),
            'base_url' => 'https://rda.unimed.example',
            'nome_contratado' => 'Centro Neuro Kids Ltda',
        ]);

        $this->migrar();

        $credencial = ConvenioCredencial::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->sole();

        $this->assertSame($convenio->id, $credencial->convenio_id);
        $this->assertSame(ConvenioDriverCatalog::UNIMED_RDA, $credencial->driver);
        $this->assertSame('usuario.rda', $credencial->campo('login'));
        $this->assertSame('senha-do-portal', $credencial->campo('password'));
        $this->assertSame('https://rda.unimed.example', $credencial->campo('base_url'));
        $this->assertSame('Centro Neuro Kids Ltda', $credencial->campo('nome_contratado'));
        $this->assertTrue($credencial->pronta());
    }

    /** COPIA, não move: o rollback depende da tabela antiga continuar intacta. */
    public function test_tabela_antiga_permanece_intacta(): void
    {
        $tenant = $this->tenant('Clínica Intacta');
        $this->convenio($tenant, 'Unimed Intacta', ConvenioDriverCatalog::UNIMED_RDA);
        $this->credencialAntiga($tenant->id, ['login' => 'permanece']);

        $this->migrar();

        $this->assertDatabaseCount('unimed_rda_credentials', 1);
        $this->assertSame('permanece', DB::table('unimed_rda_credentials')->value('login'));
    }

    public function test_pausa_e_estado_ativo_atravessam_a_migracao(): void
    {
        $tenant = $this->tenant('Clínica Pausada');
        $this->convenio($tenant, 'Unimed Pausada', ConvenioDriverCatalog::UNIMED_RDA);

        $this->credencialAntiga($tenant->id, [
            'login' => 'pausado',
            'ativo' => false,
            'automation_paused_at' => '2026-09-14 18:30:00',
            'automation_paused_reason' => 'WORKER_INTERNAL_FATAL',
        ]);

        $this->migrar();

        $credencial = ConvenioCredencial::withoutGlobalScopes()->where('tenant_id', $tenant->id)->sole();

        $this->assertFalse($credencial->ativo);
        $this->assertSame('WORKER_INTERNAL_FATAL', $credencial->automation_paused_reason);
        $this->assertNotNull($credencial->automation_paused_at);
        $this->assertFalse($credencial->pronta());
    }

    /**
     * Tenant com credencial e sem convênio de `connector_driver = 'unimed_rda'`:
     * não migra, registra no log e a migration segue. Falhar aqui pararia o
     * deploy inteiro por causa de uma linha que já era órfã antes.
     */
    public function test_credencial_sem_convenio_correspondente_nao_falha_a_migracao(): void
    {
        $orfao = $this->tenant('Clínica Órfã');
        $this->convenio($orfao, 'Convênio Manual', null);
        $this->credencialAntiga($orfao->id, ['login' => 'sem-convenio']);

        $comConvenio = $this->tenant('Clínica Com Convênio');
        $this->convenio($comConvenio, 'Unimed OK', ConvenioDriverCatalog::UNIMED_RDA);
        $this->credencialAntiga($comConvenio->id, ['login' => 'com-convenio']);

        $this->migrar();

        $this->assertSame(
            0,
            ConvenioCredencial::withoutGlobalScopes()->where('tenant_id', $orfao->id)->count(),
        );
        $this->assertSame(
            'com-convenio',
            ConvenioCredencial::withoutGlobalScopes()->where('tenant_id', $comConvenio->id)->sole()->campo('login'),
        );
    }

    /** Segunda passagem não duplica nem estoura o unique. */
    public function test_migracao_e_idempotente(): void
    {
        $tenant = $this->tenant('Clínica Idempotente');
        $this->convenio($tenant, 'Unimed Idempotente', ConvenioDriverCatalog::UNIMED_RDA);
        $this->credencialAntiga($tenant->id, ['login' => 'uma-vez']);

        $this->migrar();
        $this->migrar();

        $this->assertSame(1, ConvenioCredencial::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count());
    }

    /** Senha ilegível não derruba o deploy: a credencial pede novo cadastro. */
    public function test_senha_indecifravel_nao_derruba_a_migracao(): void
    {
        $tenant = $this->tenant('Clínica Corrompida');
        $this->convenio($tenant, 'Unimed Corrompida', ConvenioDriverCatalog::UNIMED_RDA);
        $this->credencialAntiga($tenant->id, [
            'login' => 'legivel',
            'password' => 'isto-nao-e-um-payload-cifrado',
        ]);

        $this->migrar();

        $credencial = ConvenioCredencial::withoutGlobalScopes()->where('tenant_id', $tenant->id)->sole();

        $this->assertSame('legivel', $credencial->campo('login'));
        $this->assertNull($credencial->campo('password'));
        $this->assertFalse($credencial->pronta());
    }

    private function migrar(): void
    {
        (require self::ARQUIVO)->up();
    }

    private function tenant(string $nome): Tenant
    {
        return Tenant::query()->create([
            'nome' => $nome,
            'slug' => Str::slug($nome),
            'cnpj' => sprintf('%02d.222.222/0001-22', random_int(10, 99)),
            'ativo' => true,
        ]);
    }

    private function convenio(Tenant $tenant, string $nome, ?string $driver): Convenio
    {
        return Convenio::query()->create([
            'tenant_id' => $tenant->id,
            'nome' => $nome,
            'connector_type' => $driver ? 'scraping' : 'manual',
            'connector_driver' => $driver,
            'ativo' => true,
        ]);
    }

    /** @param array<string, mixed> $atributos */
    private function credencialAntiga(int $tenantId, array $atributos): void
    {
        DB::table('unimed_rda_credentials')->insert([
            'tenant_id' => $tenantId,
            'login' => 'login',
            'password' => null,
            'base_url' => null,
            'nome_contratado' => null,
            'ativo' => true,
            'automation_paused_at' => null,
            'automation_paused_reason' => null,
            'created_at' => now(),
            'updated_at' => now(),
            ...$atributos,
        ]);
    }
}
