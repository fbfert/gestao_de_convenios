<?php

namespace Tests\Feature;

use App\Models\Convenio;
use App\Models\Especialidade;
use App\Models\Guia;
use App\Models\Medico;
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
        $this->assertSame($payload['medico_id'], $create->json('data.medico_id'));
        $this->assertSame('Dr. Carlos Almeida', $create->json('data.medico.nome'));

        $id = $create->json('data.id');
        $tenantId = Tenant::query()->where('slug', 'clinica-exemplo')->value('id');

        $guia = Guia::query()->create([
            'tenant_id' => $tenantId,
            'solicitacao_id' => $id,
            'convenio_id' => $payload['convenio_id'],
            'paciente_id' => $payload['paciente_id'],
            'profissional_id' => $payload['profissional_id'],
            'especialidade_id' => $payload['especialidade_id'],
            'numero_guia' => 'GUIA-SOLICITACAO-'.uniqid(),
            'tipo_terapia' => 'convencional',
            'status' => 'under_review',
            'data_solicitacao' => today(),
            'data_finalizacao' => null,
            'senha' => null,
            'validade_senha' => null,
            'observacoes' => null,
        ]);

        $this->getJson('/api/solicitacoes?status=under_review&convenio_id='.$payload['convenio_id'])
            ->assertOk()
            ->assertJsonPath('data.0.id', $id)
            ->assertJsonPath('data.0.status', 'under_review')
            ->assertJsonPath('data.0.guia.id', $guia->id)
            ->assertJsonPath('data.0.guia.numero_guia', $guia->numero_guia);

        $this->getJson("/api/solicitacoes/{$id}")
            ->assertOk()
            ->assertJsonPath('data.id', $id)
            ->assertJsonPath('data.status', 'under_review')
            ->assertJsonPath('data.medico.nome', 'Dr. Carlos Almeida')
            ->assertJsonPath('data.guia.id', $guia->id);
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
                'medico_id',
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
        $medico = Medico::query()->where('nome', 'Dr. Carlos Almeida')->firstOrFail();

        return [
            'paciente_id' => $this->pacienteIdPorConvenio($convenio->id),
            'profissional_id' => $profissional->id,
            'especialidade_id' => $especialidade->id,
            'convenio_id' => $convenio->id,
            'medico_id' => $medico->id,
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
            'medico_id' => Medico::query()->create([
                'tenant_id' => $tenant->id,
                'nome' => 'Dr. Externo',
                'crm' => 'CRM 999999',
                'especialidade_medica' => 'Clínica Geral',
                'telefone' => '(11) 90000-0000',
                'email' => 'externo@clinica-externa.test',
                'ativo' => true,
            ])->id,
            'status' => 'under_review',
            'solicitado_em' => today(),
            'observacoes' => null,
        ]);
    }
}
