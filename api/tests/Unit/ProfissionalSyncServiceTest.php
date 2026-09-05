<?php

namespace Tests\Unit;

use App\Models\ClinicaPushPendencia;
use App\Models\Especialidade;
use App\Models\Profissional;
use App\Models\Tenant;
use App\Services\ClinicaSync\ClinicaApiClient;
use App\Services\ClinicaSync\ClinicaPushPendenteService;
use App\Services\ClinicaSync\ProfissionalSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Mockery;
use Tests\TestCase;

class ProfissionalSyncServiceTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private function tenantId(): int
    {
        return Tenant::query()->where('slug', 'clinica-exemplo')->firstOrFail()->id;
    }

    private function especialidadeId(): int
    {
        return Especialidade::query()->where('tenant_id', $this->tenantId())->firstOrFail()->id;
    }

    private function criarProfissional(array $overrides = []): Profissional
    {
        // Isola do lote seedado (Dra. Marina Tavares e outros) — senão eles
        // também entram em pendentesDePush do push() e contaminam as contagens.
        Profissional::where('tenant_id', $this->tenantId())->update(['sincronizado_em' => now()]);

        return Profissional::query()->create(array_merge([
            'tenant_id' => $this->tenantId(),
            'nome' => 'Marina Tavares',
            'especialidade_id' => $this->especialidadeId(),
            'ativo' => true,
        ], $overrides));
    }

    /**
     * Remoto SEM especialidades: pull não consegue mapear (paraGescon devolve
     * null) e nunca vincula esse clinica_id a nada — fica "solto" no clinica,
     * exatamente o cenário real que o dedup do push precisa achar.
     */
    private function remotoSemEspecialidade(int $id, string $nome): array
    {
        return ['id' => $id, 'nome' => $nome, 'ativo' => true, 'especialidades' => [], 'updated_at' => '2026-09-05T10:00:00-03:00'];
    }

    private function apiFake(array $remotos): ClinicaApiClient
    {
        $api = Mockery::mock(ClinicaApiClient::class);
        $api->shouldReceive('listarProfissionais')->andReturn($remotos);
        $api->shouldReceive('listarCbos')->andReturn([]);

        return $api;
    }

    public function test_nome_parecido_no_clinica_gera_pendencia_de_push_em_vez_de_criar(): void
    {
        $tenantId = $this->tenantId();
        $local = $this->criarProfissional();

        $api = $this->apiFake([$this->remotoSemEspecialidade(900, 'Marinna Tavares')]);
        $api->shouldNotReceive('criarProfissional');

        $resultado = (new ProfissionalSyncService($api, $tenantId))->executar();

        $this->assertSame(0, $resultado['push']['criados']);
        $this->assertNotEmpty($resultado['push']['pendentes']);
        $this->assertSame(1, ClinicaPushPendencia::query()->count());

        $pendencia = ClinicaPushPendencia::query()->firstOrFail();
        $this->assertSame('profissional', $pendencia->tipo);
        $this->assertSame($local->id, $pendencia->local_id);
        $this->assertSame(900, $pendencia->candidatos_json[0]['clinica_id']);
        $this->assertNull($local->fresh()->clinica_id);
    }

    public function test_nome_bem_diferente_no_clinica_cria_profissional_normalmente(): void
    {
        $tenantId = $this->tenantId();
        $this->criarProfissional();

        $api = $this->apiFake([$this->remotoSemEspecialidade(901, 'Completely Different Person')]);
        $api->shouldReceive('criarProfissional')->once()->andReturn(['id' => 950]);

        $resultado = (new ProfissionalSyncService($api, $tenantId))->executar();

        $this->assertSame(1, $resultado['push']['criados']);
        $this->assertSame(0, ClinicaPushPendencia::query()->count());
    }

    public function test_pendencia_de_push_existente_nao_e_duplicada_em_nova_rodada(): void
    {
        $tenantId = $this->tenantId();
        $this->criarProfissional();

        $api = $this->apiFake([$this->remotoSemEspecialidade(902, 'Marinna Tavares')]);
        $api->shouldNotReceive('criarProfissional');

        (new ProfissionalSyncService($api, $tenantId))->executar();
        (new ProfissionalSyncService($api, $tenantId))->executar();

        $this->assertSame(1, ClinicaPushPendencia::query()->count());
    }

    public function test_confirmar_pendencia_de_push_vincula_clinica_id_sem_criar(): void
    {
        $tenantId = $this->tenantId();
        $local = $this->criarProfissional();

        $api = $this->apiFake([$this->remotoSemEspecialidade(903, 'Marinna Tavares')]);
        $api->shouldNotReceive('criarProfissional');
        (new ProfissionalSyncService($api, $tenantId))->executar();

        $pendencia = ClinicaPushPendencia::query()->firstOrFail();
        (new ClinicaPushPendenteService())->confirmar($pendencia, 903);

        $this->assertSame(903, $local->fresh()->clinica_id);
        $this->assertSame('confirmado', $pendencia->fresh()->status);
    }

    public function test_rejeitar_pendencia_de_push_libera_criacao_na_proxima_rodada(): void
    {
        $tenantId = $this->tenantId();
        $this->criarProfissional();

        $api = $this->apiFake([$this->remotoSemEspecialidade(904, 'Marinna Tavares')]);
        (new ProfissionalSyncService($api, $tenantId))->executar();

        $pendencia = ClinicaPushPendencia::query()->firstOrFail();
        (new ClinicaPushPendenteService())->rejeitar($pendencia);

        $api2 = $this->apiFake([$this->remotoSemEspecialidade(904, 'Marinna Tavares')]);
        $api2->shouldReceive('criarProfissional')->once()->andReturn(['id' => 960]);

        $resultado = (new ProfissionalSyncService($api2, $tenantId))->executar();

        $this->assertSame(1, $resultado['push']['criados']);
    }

    public function test_confirmar_com_clinica_id_fora_dos_candidatos_lanca_excecao(): void
    {
        $tenantId = $this->tenantId();
        $this->criarProfissional();

        $api = $this->apiFake([$this->remotoSemEspecialidade(905, 'Marinna Tavares')]);
        (new ProfissionalSyncService($api, $tenantId))->executar();

        $pendencia = ClinicaPushPendencia::query()->firstOrFail();

        $this->expectException(InvalidArgumentException::class);
        (new ClinicaPushPendenteService())->confirmar($pendencia, 999999);
    }
}
