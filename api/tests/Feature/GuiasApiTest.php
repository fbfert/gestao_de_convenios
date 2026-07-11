<?php

namespace Tests\Feature;

use App\Models\Convenio;
use App\Models\Especialidade;
use App\Models\Guia;
use App\Models\Paciente;
use App\Models\Profissional;
use App\Models\Solicitacao;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GuiasApiTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_cria_lista_mostra_finaliza_e_nega_guias(): void
    {
        $this->autenticar();

        $payload = $this->payloadGuia('Unimed');
        $create = $this->postJson('/api/guias', $payload);

        $create->assertCreated()
            ->assertJsonPath('data.status', 'under_review')
            ->assertJsonPath('data.numero_guia', $payload['numero_guia']);

        $id = $create->json('data.id');

        $this->getJson('/api/guias?status=under_review&convenio_id='.$payload['convenio_id'].'&paciente_id='.$payload['paciente_id'])
            ->assertOk()
            ->assertJsonPath('data.0.id', $id);

        $this->getJson("/api/guias/{$id}")
            ->assertOk()
            ->assertJsonPath('data.id', $id)
            ->assertJsonPath('data.status', 'under_review');

        $this->patchJson("/api/guias/{$id}/finalizar", [
            'senha' => 'ABC123',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'finalized')
            ->assertJsonPath('data.senha', 'ABC123');

        $this->getJson('/api/guias?status=finalized&validade_senha_vencendo_em_dias=30')
            ->assertOk()
            ->assertJsonPath('data.0.id', $id);

        $this->patchJson("/api/guias/{$id}/negar", [])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Transição inválida de guia: finalized -> denied.');
    }

    public function test_finaliza_via_http_sem_validade_senha_calculando_data_automaticamente(): void
    {
        $this->autenticar();

        $guia = $this->postJson('/api/guias', $this->payloadGuia('Unimed'))
            ->assertCreated()
            ->json('data.id');

        $response = $this->patchJson("/api/guias/{$guia}/finalizar", [
            'senha' => 'ABC123',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'finalized')
            ->assertJsonPath('data.senha', 'ABC123')
            ->assertJsonPath('data.validade_senha', today()->copy()->addDays(30)->toDateString());
    }

    public function test_filtro_de_validade_senha_vencendo_em_dias_funciona(): void
    {
        $this->autenticar();

        $guiaCurta = $this->postJson('/api/guias', $this->payloadGuia('Unimed'))
            ->assertCreated()
            ->json('data.id');

        $guiaLonga = $this->postJson('/api/guias', $this->payloadGuia('SC Saúde'))
            ->assertCreated()
            ->json('data.id');

        $this->patchJson("/api/guias/{$guiaCurta}/finalizar", [
            'senha' => 'CURTA123',
            'validade_senha' => today()->copy()->addDays(3)->toDateString(),
        ])->assertOk();

        $this->patchJson("/api/guias/{$guiaLonga}/finalizar", [
            'senha' => 'LONGA123',
            'validade_senha' => today()->copy()->addDays(30)->toDateString(),
        ])->assertOk();

        $response = $this->getJson('/api/guias?status=finalized&validade_senha_vencendo_em_dias=7');

        $response->assertOk()
            ->assertJsonPath('data.0.id', $guiaCurta)
            ->assertJsonMissing(['id' => $guiaLonga]);
    }

    public function test_criacao_valida_campos_obrigatorios(): void
    {
        $this->autenticar();

        $this->postJson('/api/guias', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'convenio_id',
                'paciente_id',
                'profissional_id',
                'especialidade_id',
                'numero_guia',
                'tipo_terapia',
                'data_solicitacao',
            ]);
    }

    public function test_usuario_de_um_tenant_nao_enxerga_guia_de_outro_tenant_via_http(): void
    {
        $guiaOutroTenant = $this->criarGuiaDeOutroTenant();

        $this->autenticar();

        $this->getJson('/api/guias/'.$guiaOutroTenant->id)
            ->assertNotFound();
    }

    private function autenticar(): void
    {
        $user = User::query()->where('email', 'admin@clinica-exemplo.test')->firstOrFail();
        Sanctum::actingAs($user);
    }

    private function payloadGuia(string $convenioNome): array
    {
        $convenio = Convenio::query()->where('nome', $convenioNome)->firstOrFail();
        $especialidade = Especialidade::query()->where('nome', 'Fisioterapia')->firstOrFail();
        $profissional = Profissional::query()->where('especialidade_id', $especialidade->id)->firstOrFail();

        return [
            'solicitacao_id' => null,
            'convenio_id' => $convenio->id,
            'paciente_id' => $this->pacienteIdPorConvenio($convenio->id),
            'profissional_id' => $profissional->id,
            'especialidade_id' => $especialidade->id,
            'numero_guia' => 'GUIA-API-'.uniqid(),
            'tipo_terapia' => 'especializada',
            'data_solicitacao' => today()->toDateString(),
        ];
    }

    private function pacienteIdPorConvenio(int $convenioId): int
    {
        return Paciente::query()->where('convenio_id', $convenioId)->firstOrFail()->id;
    }

    private function criarGuiaDeOutroTenant(): Guia
    {
        $tenant = Tenant::query()->create([
            'nome' => 'Clínica Externa Guías',
            'slug' => 'clinica-externa-guias',
            'cnpj' => '77.777.777/0001-77',
            'ativo' => true,
        ]);

        $especialidade = Especialidade::query()->create([
            'tenant_id' => $tenant->id,
            'nome' => 'Fisioterapia Externa',
            'ativo' => true,
        ]);

        $profissional = Profissional::query()->create([
            'tenant_id' => $tenant->id,
            'especialidade_id' => $especialidade->id,
            'nome' => 'Dra. Guia Externa',
            'conselho_registro' => 'CREFITO 888888-F',
            'ativo' => true,
        ]);

        $convenio = Convenio::query()->create([
            'tenant_id' => $tenant->id,
            'nome' => 'Convênio Externo Guia',
            'connector_type' => 'manual',
            'connector_config' => null,
            'ativo' => true,
        ]);

        $paciente = Paciente::query()->create([
            'tenant_id' => $tenant->id,
            'nome' => 'Paciente Externo Guia',
            'cpf' => '12345678908',
            'carteirinha' => 'EXT-G-0001',
            'convenio_id' => $convenio->id,
            'telefone' => '(11) 90000-0001',
            'clinica_agil_id' => null,
            'ativo' => true,
        ]);

        return Guia::query()->create([
            'tenant_id' => $tenant->id,
            'solicitacao_id' => null,
            'convenio_id' => $convenio->id,
            'paciente_id' => $paciente->id,
            'profissional_id' => $profissional->id,
            'especialidade_id' => $especialidade->id,
            'numero_guia' => 'GUIA-EXTERNO-001',
            'tipo_terapia' => 'especializada',
            'status' => 'under_review',
            'data_solicitacao' => today(),
            'data_finalizacao' => null,
            'senha' => null,
            'validade_senha' => null,
            'observacoes' => null,
        ]);
    }
}
