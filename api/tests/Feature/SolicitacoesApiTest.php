<?php

namespace Tests\Feature;

use App\Models\Convenio;
use App\Models\Especialidade;
use App\Models\Paciente;
use App\Models\Profissional;
use App\Models\Tenant;
use App\Models\Solicitacao;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SolicitacoesApiTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_cria_lista_e_mostra_solicitacoes(): void
    {
        $this->autenticar();
        $payload = $this->payloadSolicitacao('Unimed');

        $create = $this->postJson('/api/solicitacoes', $payload);

        $create->assertCreated()
            ->assertJsonPath('data.status', 'under_review')
            ->assertJsonPath('data.convenio_id', $payload['convenio_id']);

        $id = $create->json('data.id');

        $this->getJson('/api/solicitacoes?status=under_review&convenio_id='.$payload['convenio_id'])
            ->assertOk()
            ->assertJsonPath('data.0.id', $id)
            ->assertJsonPath('data.0.status', 'under_review');

        $this->getJson("/api/solicitacoes/{$id}")
            ->assertOk()
            ->assertJsonPath('data.id', $id)
            ->assertJsonPath('data.status', 'under_review');
    }

    public function test_criacao_valida_campos_obrigatorios(): void
    {
        $this->autenticar();

        $this->postJson('/api/solicitacoes', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'paciente_id',
                'profissional_id',
                'especialidade_id',
                'convenio_id',
                'medico_solicitante',
                'solicitado_em',
            ]);
    }

    public function test_aprovar_negar_e_bloquear_reaprovacao(): void
    {
        $this->autenticar();

        $aprovada = $this->postJson('/api/solicitacoes', $this->payloadSolicitacao('Unimed'))
            ->assertCreated()
            ->json('data.id');

        $negada = $this->postJson('/api/solicitacoes', $this->payloadSolicitacao('SC Saúde'))
            ->assertCreated()
            ->json('data.id');

        $this->patchJson("/api/solicitacoes/{$aprovada}/aprovar")
            ->assertOk()
            ->assertJsonPath('data.status', 'approved');

        $this->patchJson("/api/solicitacoes/{$negada}/negar")
            ->assertOk()
            ->assertJsonPath('data.status', 'denied');

        $this->patchJson("/api/solicitacoes/{$aprovada}/aprovar")
            ->assertStatus(422)
            ->assertJsonPath('message', 'Transição inválida de solicitação: approved -> approved.');
    }

    public function test_usuariode_um_tenant_nao_enxerga_solicitacao_de_outro_tenant_via_http(): void
    {
        $solicitacaoOutroTenant = $this->criarSolicitacaoDeOutroTenant();

        $this->autenticar();

        $this->getJson('/api/solicitacoes/'.$solicitacaoOutroTenant->id)
            ->assertNotFound();
    }

    private function autenticar(): void
    {
        $user = User::query()->where('email', 'admin@clinica-exemplo.test')->firstOrFail();
        Sanctum::actingAs($user);
    }

    private function payloadSolicitacao(string $convenioNome): array
    {
        $convenio = Convenio::query()->where('nome', $convenioNome)->firstOrFail();
        $especialidade = Especialidade::query()->where('nome', 'Fisioterapia')->firstOrFail();
        $profissional = Profissional::query()->where('especialidade_id', $especialidade->id)->firstOrFail();

        return [
            'paciente_id' => $this->pacienteIdPorConvenio($convenio->id),
            'profissional_id' => $profissional->id,
            'especialidade_id' => $especialidade->id,
            'convenio_id' => $convenio->id,
            'medico_solicitante' => 'Dr. Carlos Almeida',
            'solicitado_em' => today()->toDateString(),
        ];
    }

    private function pacienteIdPorConvenio(int $convenioId): int
    {
        return Paciente::query()->where('convenio_id', $convenioId)->firstOrFail()->id;
    }

    private function criarSolicitacaoDeOutroTenant(): Solicitacao
    {
        $tenant = Tenant::query()->create([
            'nome' => 'Clínica Externa',
            'slug' => 'clinica-externa',
            'cnpj' => '98.765.432/0001-10',
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
            'nome' => 'Dra. Externa',
            'conselho_registro' => 'CREFITO 999999-F',
            'ativo' => true,
        ]);

        $convenio = Convenio::query()->create([
            'tenant_id' => $tenant->id,
            'nome' => 'Convênio Externo',
            'connector_type' => 'manual',
            'connector_config' => null,
            'ativo' => true,
        ]);

        $paciente = Paciente::query()->create([
            'tenant_id' => $tenant->id,
            'nome' => 'Paciente Externo',
            'cpf' => '12345678909',
            'carteirinha' => 'EXT-0001',
            'convenio_id' => $convenio->id,
            'telefone' => '(11) 90000-0000',
            'clinica_agil_id' => null,
            'ativo' => true,
        ]);

        return Solicitacao::query()->create([
            'tenant_id' => $tenant->id,
            'paciente_id' => $paciente->id,
            'profissional_id' => $profissional->id,
            'especialidade_id' => $especialidade->id,
            'convenio_id' => $convenio->id,
            'medico_solicitante' => 'Dr. Externo',
            'status' => 'under_review',
            'solicitado_em' => today(),
            'observacoes' => null,
        ]);
    }
}
