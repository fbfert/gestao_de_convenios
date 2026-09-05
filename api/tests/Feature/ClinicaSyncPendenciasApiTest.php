<?php

namespace Tests\Feature;

use App\Models\ClinicaPacientePendente;
use App\Models\Convenio;
use App\Models\Paciente;
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

    public function test_duplicados_encontra_pacientes_com_nome_parecido(): void
    {
        $this->autenticar();
        $convenioId = $this->convenioId();

        Paciente::query()->create([
            'tenant_id' => $this->tenantId(),
            'nome' => 'Abner dos Santos Beiger',
            'carteirinha' => '11111111111',
            'convenio_id' => $convenioId,
            'ativo' => true,
        ]);
        Paciente::query()->create([
            'tenant_id' => $this->tenantId(),
            'nome' => 'Abner Santos Beiger',
            'carteirinha' => '22222222222',
            'convenio_id' => $convenioId,
            'ativo' => true,
        ]);

        $resposta = $this->getJson('/api/configuracoes/clinica-sync/duplicados')->assertOk();

        $nomes = collect($resposta->json())->map(fn ($par) => [$par['paciente_a']['nome'], $par['paciente_b']['nome']]);
        $this->assertTrue($nomes->contains(['Abner dos Santos Beiger', 'Abner Santos Beiger']));
    }
}
