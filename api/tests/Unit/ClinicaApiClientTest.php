<?php

namespace Tests\Unit;

use App\Models\ClinicaConexaoConfig;
use App\Models\Tenant;
use App\Services\ClinicaSync\ClinicaApiClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Fecha a lacuna que deixou passar o bug de PATCH vs PUT (405 em todo
 * envio de atualização pro clinica, ver clinica_sync_execucoes #4078):
 * nenhum teste verificava o verbo HTTP de verdade, só o retorno mockado.
 */
class ClinicaApiClientTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private function client(): ClinicaApiClient
    {
        $tenantId = Tenant::query()->where('slug', 'clinica-exemplo')->firstOrFail()->id;

        $config = ClinicaConexaoConfig::query()->create([
            'tenant_id' => $tenantId,
            'base_url' => 'https://clinica.test',
            'token' => 'token-teste',
            'ativo' => true,
        ]);

        return new ClinicaApiClient($config);
    }

    public function test_atualizar_paciente_manda_put_nao_patch(): void
    {
        Http::fake(['*' => Http::response(['id' => 1, 'updated_at' => now()->toJSON()], 200)]);

        $this->client()->atualizarPaciente(1, ['nome' => 'X'], now()->toJSON());

        Http::assertSent(fn ($request) => $request->method() === 'PUT' && str_contains($request->url(), '/pacientes/1'));
    }

    public function test_atualizar_profissional_manda_put_nao_patch(): void
    {
        Http::fake(['*' => Http::response(['id' => 1, 'updated_at' => now()->toJSON()], 200)]);

        $this->client()->atualizarProfissional(1, ['nome' => 'X'], now()->toJSON());

        Http::assertSent(fn ($request) => $request->method() === 'PUT' && str_contains($request->url(), '/profissionais/1'));
    }

    public function test_criar_paciente_manda_post(): void
    {
        Http::fake(['*' => Http::response(['id' => 1], 201)]);

        $this->client()->criarPaciente(['nome' => 'X']);

        Http::assertSent(fn ($request) => $request->method() === 'POST' && str_contains($request->url(), '/pacientes'));
    }

    public function test_criar_profissional_manda_post(): void
    {
        Http::fake(['*' => Http::response(['id' => 1], 201)]);

        $this->client()->criarProfissional(['nome' => 'X']);

        Http::assertSent(fn ($request) => $request->method() === 'POST' && str_contains($request->url(), '/profissionais'));
    }
}
