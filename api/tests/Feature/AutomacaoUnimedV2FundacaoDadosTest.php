<?php

namespace Tests\Feature;

use App\Models\Convenio;
use App\Models\ConvenioEspecialidadeMapeamento;
use App\Models\ConvenioProfissionalMapeamento;
use App\Models\Especialidade;
use App\Models\Guia;
use App\Models\Paciente;
use App\Models\Profissional;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AutomacaoUnimedV2FundacaoDadosTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_cria_solicitacao_com_cid(): void
    {
        $this->autenticar();
        $paciente = Paciente::query()->firstOrFail();
        $profissional = Profissional::query()->firstOrFail();
        $especialidade = Especialidade::query()->firstOrFail();
        $convenio = Convenio::query()->firstOrFail();

        $this->postJson('/api/solicitacoes', [
            'paciente_id' => $paciente->id,
            'profissional_id' => $profissional->id,
            'especialidade_id' => $especialidade->id,
            'convenio_id' => $convenio->id,
            'medico_id' => \App\Models\Medico::query()->firstOrFail()->id,
            'cid' => 'F84.0',
            'solicitado_em' => today()->toDateString(),
            'itens' => [[
                'especialidade_id' => $especialidade->id,
                'profissional_id' => $profissional->id,
                'quantidade' => 10,
            ]],
        ])->assertCreated()->assertJsonPath('data.cid', 'F84.0');
    }

    public function test_cria_mapeamentos_e_bloqueia_duplicidade(): void
    {
        $this->autenticar();
        $convenio = Convenio::query()->where('nome', 'Unimed')->firstOrFail();
        $especialidade = Especialidade::query()->firstOrFail();
        $profissional = Profissional::query()->firstOrFail();

        $this->postJson('/api/configuracoes/unimed/mapeamentos/especialidades', [
            'convenio_id' => $convenio->id,
            'especialidade_id' => $especialidade->id,
            'codigo_procedimento' => '50000012',
            'quantidade_padrao' => 10,
            'ativo' => true,
        ])->assertCreated()->assertJsonPath('data.codigo_procedimento', '50000012');

        $this->postJson('/api/configuracoes/unimed/mapeamentos/especialidades', [
            'convenio_id' => $convenio->id,
            'especialidade_id' => $especialidade->id,
            'codigo_procedimento' => '50000013',
        ])->assertStatus(422);

        $this->postJson('/api/configuracoes/unimed/mapeamentos/profissionais', [
            'convenio_id' => $convenio->id,
            'profissional_id' => $profissional->id,
            'codigo_operadora' => '12345',
            'ativo' => true,
        ])->assertCreated()->assertJsonPath('data.codigo_operadora', '12345');

        $this->assertSame(1, ConvenioEspecialidadeMapeamento::query()->count());
        $this->assertSame(1, ConvenioProfissionalMapeamento::query()->count());
    }

    public function test_guia_sem_numero_com_campos_unimed_v2(): void
    {
        $this->autenticar();
        $guia = Guia::query()->create([
            'tenant_id' => auth()->user()->tenant_id,
            'convenio_id' => Convenio::query()->firstOrFail()->id,
            'paciente_id' => Paciente::query()->firstOrFail()->id,
            'profissional_id' => Profissional::query()->firstOrFail()->id,
            'especialidade_id' => Especialidade::query()->firstOrFail()->id,
            'numero_guia' => null,
            'tipo_terapia' => 'especializada',
            'status' => 'needs_verification',
            'data_solicitacao' => today(),
            'sessoes_solicitadas' => 10,
            'sessoes_autorizadas' => 0,
            'protocolo_operadora' => 'PROTO-1',
        ]);

        $this->getJson("/api/guias/{$guia->id}")
            ->assertOk()
            ->assertJsonPath('data.numero_guia', null)
            ->assertJsonPath('data.status', 'needs_verification')
            ->assertJsonPath('data.sessoes_solicitadas', 10)
            ->assertJsonPath('data.protocolo_operadora', 'PROTO-1');
    }

    public function test_valida_e_normaliza_carteirinha_unimed(): void
    {
        $this->autenticar();
        $convenio = Convenio::query()->where('nome', 'Unimed')->firstOrFail();
        $convenio->forceFill(['connector_driver' => 'unimed_rda'])->save();

        $this->postJson('/api/pacientes', [
            'nome' => 'Paciente Unimed Blocos',
            'carteirinha_unimed' => ['1234', '5678', '123456', '01', '9'],
            'convenio_id' => $convenio->id,
        ])->assertCreated()->assertJsonPath('data.carteirinha', '12345678123456019');

        $this->postJson('/api/pacientes', [
            'nome' => 'Paciente Unimed Inválido',
            'carteirinha' => '123',
            'convenio_id' => $convenio->id,
        ])->assertStatus(422)->assertJsonValidationErrors(['carteirinha']);
    }

    public function test_mapeamentos_respeitam_tenant(): void
    {
        $this->autenticar();
        $tenant = Tenant::query()->create([
            'nome' => 'Tenant Externo Mapeamento',
            'slug' => 'tenant-externo-mapeamento',
            'cnpj' => '66.666.666/0001-66',
            'ativo' => true,
        ]);
        $convenioExterno = Convenio::query()->create([
            'tenant_id' => $tenant->id,
            'nome' => 'Unimed Externa',
            'connector_type' => 'manual',
            'ativo' => true,
        ]);

        $this->postJson('/api/configuracoes/unimed/mapeamentos/especialidades', [
            'convenio_id' => $convenioExterno->id,
            'especialidade_id' => Especialidade::query()->firstOrFail()->id,
            'codigo_procedimento' => '50000012',
        ])->assertStatus(422)->assertJsonValidationErrors(['convenio_id']);
    }

    private function autenticar(): void
    {
        $user = User::query()->where('email', 'admin@clinica-exemplo.test')->firstOrFail();
        Sanctum::actingAs($user);
    }
}
