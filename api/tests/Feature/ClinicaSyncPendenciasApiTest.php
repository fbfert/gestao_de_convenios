<?php

namespace Tests\Feature;

use App\Models\ClinicaPacientePendente;
use App\Models\ClinicaPushPendencia;
use App\Models\Convenio;
use App\Models\Paciente;
use App\Models\Profissional;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClinicaSyncPendenciasApiTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private function autenticar(): void
    {
        $user = User::query()->where('email', 'admin@clinica-exemplo.test')->firstOrFail();
        Sanctum::actingAs($user);
    }

    private function tenantId(): int
    {
        return Tenant::query()->where('slug', 'clinica-exemplo')->firstOrFail()->id;
    }

    private function convenioId(): int
    {
        return Convenio::query()->where('tenant_id', $this->tenantId())->where('nome', 'Unimed')->firstOrFail()->id;
    }

    private function criarPendencia(Paciente $candidato, array $overrides = []): ClinicaPacientePendente
    {
        return ClinicaPacientePendente::query()->create(array_merge([
            'tenant_id' => $this->tenantId(),
            'clinica_id' => 999,
            'dados_remoto' => ['nome' => 'Abner Santos Beiger', 'cpf' => '11144477735', 'nascimento' => '1990-01-01', 'contatos_json' => []],
            'remoto_atualizado_em' => now(),
            'status' => 'pendente',
            'candidato_paciente_id' => $candidato->id,
            'similaridade' => 95,
            'candidatos_json' => [['id' => $candidato->id, 'similaridade' => 95.0]],
        ], $overrides));
    }

    public function test_lista_pendencias_com_candidatos_resolvidos(): void
    {
        $this->autenticar();

        $candidato = Paciente::query()->create([
            'tenant_id' => $this->tenantId(),
            'nome' => 'Abner dos Santos Beiger',
            'carteirinha' => '99988877766',
            'convenio_id' => $this->convenioId(),
            'ativo' => true,
        ]);

        $this->criarPendencia($candidato);

        $this->getJson('/api/configuracoes/clinica-sync/pendencias')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.nome_remoto', 'Abner Santos Beiger')
            ->assertJsonPath('0.candidatos.0.id', $candidato->id)
            ->assertJsonPath('0.candidatos.0.carteirinha', '99988877766');
    }

    public function test_confirmar_vincula_paciente_e_preenche_so_vazios(): void
    {
        $this->autenticar();

        $candidato = Paciente::query()->create([
            'tenant_id' => $this->tenantId(),
            'nome' => 'Abner dos Santos Beiger',
            'cpf' => null,
            'carteirinha' => '99988877766',
            'convenio_id' => $this->convenioId(),
            'ativo' => true,
        ]);

        $pendencia = $this->criarPendencia($candidato);

        $this->postJson("/api/configuracoes/clinica-sync/pendencias/{$pendencia->id}/confirmar", ['paciente_id' => $candidato->id])
            ->assertOk()
            ->assertJsonPath('paciente_id', $candidato->id);

        $candidato->refresh();
        $this->assertSame('11144477735', $candidato->cpf);
        $this->assertSame('99988877766', $candidato->carteirinha);
        $this->assertSame(999, $candidato->clinica_id);
        $this->assertSame('confirmado', $pendencia->fresh()->status);
    }

    public function test_confirmar_com_paciente_ja_vinculado_falha(): void
    {
        $this->autenticar();

        $candidato = Paciente::query()->create([
            'tenant_id' => $this->tenantId(),
            'nome' => 'Abner dos Santos Beiger',
            'carteirinha' => '99988877766',
            'convenio_id' => $this->convenioId(),
            'clinica_id' => 111,
            'ativo' => true,
        ]);

        $pendencia = $this->criarPendencia($candidato);

        $this->postJson("/api/configuracoes/clinica-sync/pendencias/{$pendencia->id}/confirmar", ['paciente_id' => $candidato->id])
            ->assertStatus(422);
    }

    public function test_rejeitar_marca_pendencia_como_rejeitada(): void
    {
        $this->autenticar();

        $candidato = Paciente::query()->create([
            'tenant_id' => $this->tenantId(),
            'nome' => 'Abner dos Santos Beiger',
            'carteirinha' => '99988877766',
            'convenio_id' => $this->convenioId(),
            'ativo' => true,
        ]);

        $pendencia = $this->criarPendencia($candidato);

        $this->postJson("/api/configuracoes/clinica-sync/pendencias/{$pendencia->id}/rejeitar")
            ->assertOk()
            ->assertJsonPath('status', 'rejeitado');

        $this->assertSame('rejeitado', $pendencia->fresh()->status);
    }

    private function especialidadeId(): int
    {
        return \App\Models\Especialidade::query()->where('tenant_id', $this->tenantId())->firstOrFail()->id;
    }

    public function test_lista_push_pendencias_com_nome_local_resolvido(): void
    {
        $this->autenticar();

        $paciente = Paciente::query()->create([
            'tenant_id' => $this->tenantId(),
            'nome' => 'Roberto Silva Neto Junior',
            'carteirinha' => '11122233344',
            'convenio_id' => $this->convenioId(),
            'ativo' => true,
        ]);

        ClinicaPushPendencia::query()->create([
            'tenant_id' => $this->tenantId(),
            'tipo' => 'paciente',
            'local_id' => $paciente->id,
            'candidatos_json' => [['clinica_id' => 700, 'nome' => 'Roberto Silva Neto', 'similaridade' => 95.0]],
            'status' => 'pendente',
        ]);

        $this->getJson('/api/configuracoes/clinica-sync/push-pendencias')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.tipo', 'paciente')
            ->assertJsonPath('0.nome_local', 'Roberto Silva Neto Junior')
            ->assertJsonPath('0.candidatos.0.clinica_id', 700);
    }

    public function test_confirmar_push_pendencia_vincula_clinica_id_no_profissional(): void
    {
        $this->autenticar();

        $profissional = Profissional::query()->create([
            'tenant_id' => $this->tenantId(),
            'nome' => 'Marina Tavares',
            'especialidade_id' => $this->especialidadeId(),
            'ativo' => true,
        ]);

        $pendencia = ClinicaPushPendencia::query()->create([
            'tenant_id' => $this->tenantId(),
            'tipo' => 'profissional',
            'local_id' => $profissional->id,
            'candidatos_json' => [['clinica_id' => 900, 'nome' => 'Marina Tavares Almeida', 'similaridade' => 92.0]],
            'status' => 'pendente',
        ]);

        $this->postJson("/api/configuracoes/clinica-sync/push-pendencias/{$pendencia->id}/confirmar", ['clinica_id_escolhido' => 900])
            ->assertOk()
            ->assertJsonPath('status', 'confirmado');

        $this->assertSame(900, $profissional->fresh()->clinica_id);
        $this->assertSame('confirmado', $pendencia->fresh()->status);
    }

    public function test_confirmar_push_pendencia_com_id_fora_dos_candidatos_falha(): void
    {
        $this->autenticar();

        $paciente = Paciente::query()->create([
            'tenant_id' => $this->tenantId(),
            'nome' => 'Roberto Silva Neto Junior',
            'carteirinha' => '11122233344',
            'convenio_id' => $this->convenioId(),
            'ativo' => true,
        ]);

        $pendencia = ClinicaPushPendencia::query()->create([
            'tenant_id' => $this->tenantId(),
            'tipo' => 'paciente',
            'local_id' => $paciente->id,
            'candidatos_json' => [['clinica_id' => 700, 'nome' => 'Roberto Silva Neto', 'similaridade' => 95.0]],
            'status' => 'pendente',
        ]);

        $this->postJson("/api/configuracoes/clinica-sync/push-pendencias/{$pendencia->id}/confirmar", ['clinica_id_escolhido' => 123456])
            ->assertStatus(422);
    }

    public function test_rejeitar_push_pendencia(): void
    {
        $this->autenticar();

        $paciente = Paciente::query()->create([
            'tenant_id' => $this->tenantId(),
            'nome' => 'Roberto Silva Neto Junior',
            'carteirinha' => '11122233344',
            'convenio_id' => $this->convenioId(),
            'ativo' => true,
        ]);

        $pendencia = ClinicaPushPendencia::query()->create([
            'tenant_id' => $this->tenantId(),
            'tipo' => 'paciente',
            'local_id' => $paciente->id,
            'candidatos_json' => [['clinica_id' => 700, 'nome' => 'Roberto Silva Neto', 'similaridade' => 95.0]],
            'status' => 'pendente',
        ]);

        $this->postJson("/api/configuracoes/clinica-sync/push-pendencias/{$pendencia->id}/rejeitar")
            ->assertOk()
            ->assertJsonPath('status', 'rejeitado');

        $this->assertSame('rejeitado', $pendencia->fresh()->status);
    }
}
