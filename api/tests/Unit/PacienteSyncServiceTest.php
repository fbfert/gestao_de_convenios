<?php

namespace Tests\Unit;

use App\Models\ClinicaPacientePendente;
use App\Models\Convenio;
use App\Models\Paciente;
use App\Models\Tenant;
use App\Services\ClinicaSync\ClinicaApiClient;
use App\Services\ClinicaSync\ClinicaPacientePendenteService;
use App\Services\ClinicaSync\PacienteSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Mockery;
use Tests\TestCase;

class PacienteSyncServiceTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private function tenantId(): int
    {
        return Tenant::query()->where('slug', 'clinica-exemplo')->firstOrFail()->id;
    }

    private function convenioParticularId(): int
    {
        return Convenio::query()->firstOrCreate(
            ['tenant_id' => $this->tenantId(), 'nome' => 'Particular'],
            ['connector_type' => 'manual', 'connector_config' => null, 'ativo' => true],
        )->id;
    }

    private function remoto(int $id, string $nome, array $overrides = []): array
    {
        return array_merge([
            'id' => $id,
            'nome' => $nome,
            'cpf' => null,
            'nascimento' => '1990-01-01',
            'ativo' => true,
            'contatos_json' => [],
            'updated_at' => '2026-09-05T10:00:00-03:00',
        ], $overrides);
    }

    private function apiFake(array $remoto): ClinicaApiClient
    {
        $api = Mockery::mock(ClinicaApiClient::class);
        $api->shouldReceive('listarPacientesPagina')->once()->with(1)
            ->andReturn(['data' => [['id' => $remoto['id']]], 'meta' => ['last_page' => 1]]);
        $api->shouldReceive('buscarPaciente')->with($remoto['id'])->andReturn($remoto);

        return $api;
    }

    public function test_match_exato_por_cpf_nao_gera_pendencia(): void
    {
        $tenantId = $this->tenantId();
        $convenioId = $this->convenioParticularId();

        $existente = Paciente::query()->create([
            'tenant_id' => $tenantId,
            'nome' => 'Abner dos Santos Beiger',
            'cpf' => '11144477735',
            'carteirinha' => '99988877766',
            'convenio_id' => $convenioId,
            'ativo' => true,
        ]);

        $remoto = $this->remoto(501, 'Abner dos Santos Beiger', ['cpf' => '111.444.777-35']);
        $service = new PacienteSyncService($this->apiFake($remoto), $tenantId);

        $resultado = $service->executar();

        $this->assertSame(0, $resultado['pull']['criados']);
        $this->assertSame(1, $resultado['pull']['atualizados']);
        $this->assertSame(0, ClinicaPacientePendente::query()->count());
        $this->assertSame('99988877766', $existente->fresh()->carteirinha);
        $this->assertSame(501, $existente->fresh()->clinica_id);
    }

    public function test_nome_parecido_sem_cpf_gera_pendencia_em_vez_de_duplicar(): void
    {
        $tenantId = $this->tenantId();
        $convenioId = $this->convenioParticularId();

        $existente = Paciente::query()->create([
            'tenant_id' => $tenantId,
            'nome' => 'Abner dos Santos Beiger',
            'cpf' => null,
            'carteirinha' => '99988877766',
            'convenio_id' => $convenioId,
            'ativo' => true,
        ]);

        $totalAntes = Paciente::query()->where('tenant_id', $tenantId)->count();

        $remoto = $this->remoto(502, 'Abner Santos Beiger');
        $service = new PacienteSyncService($this->apiFake($remoto), $tenantId);

        $resultado = $service->executar();

        $this->assertSame(0, $resultado['pull']['criados']);
        $this->assertNotEmpty($resultado['pull']['pendentes']);
        $this->assertSame(1, ClinicaPacientePendente::query()->count());

        $pendencia = ClinicaPacientePendente::query()->first();
        $this->assertSame('pendente', $pendencia->status);
        $this->assertSame($existente->id, $pendencia->candidato_paciente_id);
        $this->assertGreaterThanOrEqual(90, $pendencia->similaridade);

        // não criou paciente novo nem tocou a carteirinha do existente
        $this->assertSame($totalAntes, Paciente::query()->where('tenant_id', $tenantId)->count());
        $this->assertSame('99988877766', $existente->fresh()->carteirinha);
        $this->assertNull($existente->fresh()->clinica_id);
    }

    public function test_nome_bem_diferente_cria_paciente_novo_normalmente(): void
    {
        $tenantId = $this->tenantId();

        Paciente::query()->create([
            'tenant_id' => $tenantId,
            'nome' => 'Abner dos Santos Beiger',
            'carteirinha' => '99988877766',
            'convenio_id' => $this->convenioParticularId(),
            'ativo' => true,
        ]);

        $remoto = $this->remoto(503, 'Maria da Conceicao Oliveira');
        $service = new PacienteSyncService($this->apiFake($remoto), $tenantId);

        $resultado = $service->executar();

        $this->assertSame(1, $resultado['pull']['criados']);
        $this->assertSame(0, ClinicaPacientePendente::query()->count());
        $this->assertDatabaseHas('pacientes', [
            'tenant_id' => $tenantId,
            'nome' => 'Maria da Conceicao Oliveira',
            'carteirinha' => 'SYNC-CLINICA-503',
        ]);
    }

    public function test_pendencia_existente_nao_e_duplicada_em_nova_rodada(): void
    {
        $tenantId = $this->tenantId();

        $existente = Paciente::query()->create([
            'tenant_id' => $tenantId,
            'nome' => 'Abner dos Santos Beiger',
            'carteirinha' => '99988877766',
            'convenio_id' => $this->convenioParticularId(),
            'ativo' => true,
        ]);

        $remoto = $this->remoto(504, 'Abner Santos Beiger');
        (new PacienteSyncService($this->apiFake($remoto), $tenantId))->executar();

        $remoto2 = $this->remoto(504, 'Abner Santos Beiger', ['updated_at' => '2026-09-05T11:00:00-03:00']);
        $resultado = (new PacienteSyncService($this->apiFake($remoto2), $tenantId))->executar();

        $this->assertSame(1, ClinicaPacientePendente::query()->count());
        $this->assertNotEmpty($resultado['pull']['pendentes']);
        $this->assertNull($existente->fresh()->clinica_id);
    }

    public function test_confirmar_preenche_so_campos_vazios_e_nunca_toca_carteirinha(): void
    {
        $tenantId = $this->tenantId();

        $existente = Paciente::query()->create([
            'tenant_id' => $tenantId,
            'nome' => 'Abner dos Santos Beiger',
            'cpf' => null,
            'telefone' => null,
            'carteirinha' => '99988877766',
            'convenio_id' => $this->convenioParticularId(),
            'ativo' => true,
        ]);

        $remoto = $this->remoto(505, 'Abner Santos Beiger', [
            'cpf' => '111.444.777-35',
            'contatos_json' => [['tipo' => 'telefone', 'valor' => '11999990000']],
        ]);
        (new PacienteSyncService($this->apiFake($remoto), $tenantId))->executar();

        $pendencia = ClinicaPacientePendente::query()->firstOrFail();

        $service = new ClinicaPacientePendenteService();
        $service->confirmar($pendencia, $existente->id);

        $existente->refresh();
        $this->assertSame('11144477735', $existente->cpf);
        $this->assertSame('11999990000', $existente->telefone);
        $this->assertSame('99988877766', $existente->carteirinha); // nunca sobrescrita
        $this->assertSame('Abner dos Santos Beiger', $existente->nome); // nome não tocado
        $this->assertSame(505, $existente->clinica_id);
        $this->assertSame('confirmado', $pendencia->fresh()->status);
    }

    public function test_confirmar_nao_sobrescreve_cpf_ja_preenchido(): void
    {
        $tenantId = $this->tenantId();

        $existente = Paciente::query()->create([
            'tenant_id' => $tenantId,
            'nome' => 'Abner dos Santos Beiger',
            'cpf' => '22233344455',
            'carteirinha' => '99988877766',
            'convenio_id' => $this->convenioParticularId(),
            'ativo' => true,
        ]);

        $remoto = $this->remoto(506, 'Abner Santos Beiger', ['cpf' => '111.444.777-35']);
        (new PacienteSyncService($this->apiFake($remoto), $tenantId))->executar();

        $pendencia = ClinicaPacientePendente::query()->firstOrFail();
        (new ClinicaPacientePendenteService())->confirmar($pendencia, $existente->id);

        $this->assertSame('22233344455', $existente->fresh()->cpf);
    }

    public function test_rejeitar_deixa_proxima_sincronizacao_criar_paciente_novo(): void
    {
        $tenantId = $this->tenantId();

        Paciente::query()->create([
            'tenant_id' => $tenantId,
            'nome' => 'Abner dos Santos Beiger',
            'carteirinha' => '99988877766',
            'convenio_id' => $this->convenioParticularId(),
            'ativo' => true,
        ]);

        $remoto = $this->remoto(507, 'Abner Santos Beiger');
        (new PacienteSyncService($this->apiFake($remoto), $tenantId))->executar();

        $pendencia = ClinicaPacientePendente::query()->firstOrFail();
        (new ClinicaPacientePendenteService())->rejeitar($pendencia);

        $remoto2 = $this->remoto(507, 'Abner Santos Beiger', ['updated_at' => '2026-09-05T12:00:00-03:00']);
        $resultado = (new PacienteSyncService($this->apiFake($remoto2), $tenantId))->executar();

        $this->assertSame(1, $resultado['pull']['criados']);
        $this->assertDatabaseHas('pacientes', ['tenant_id' => $tenantId, 'clinica_id' => 507]);
    }

    public function test_confirmar_pendencia_ja_resolvida_lanca_excecao(): void
    {
        $tenantId = $this->tenantId();

        $existente = Paciente::query()->create([
            'tenant_id' => $tenantId,
            'nome' => 'Abner dos Santos Beiger',
            'carteirinha' => '99988877766',
            'convenio_id' => $this->convenioParticularId(),
            'ativo' => true,
        ]);

        $remoto = $this->remoto(508, 'Abner Santos Beiger');
        (new PacienteSyncService($this->apiFake($remoto), $tenantId))->executar();

        $pendencia = ClinicaPacientePendente::query()->firstOrFail();
        $service = new ClinicaPacientePendenteService();
        $service->rejeitar($pendencia);

        $this->expectException(InvalidArgumentException::class);
        $service->confirmar($pendencia->fresh(), $existente->id);
    }
}
