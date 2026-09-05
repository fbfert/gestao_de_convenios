<?php

namespace Tests\Feature;

use App\Models\Convenio;
use App\Models\Paciente;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PacientesDuplicadosApiTest extends TestCase
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

    private function criarPaciente(array $overrides = []): Paciente
    {
        return Paciente::query()->create(array_merge([
            'tenant_id' => $this->tenantId(),
            'nome' => 'Abner dos Santos Beiger',
            'convenio_id' => $this->convenioId(),
            'carteirinha' => '00011122233',
            'ativo' => true,
        ], $overrides));
    }

    public function test_lista_duplicados(): void
    {
        $this->autenticar();
        $this->criarPaciente();
        $this->criarPaciente(['nome' => 'Abner Santos Beiger', 'carteirinha' => '00099988877']);

        $resposta = $this->getJson('/api/pacientes/duplicados')->assertOk();
        $this->assertNotEmpty($resposta->json());
    }

    public function test_preview_retorna_contagens(): void
    {
        $this->autenticar();
        $vencedor = $this->criarPaciente();
        $perdedor = $this->criarPaciente(['nome' => 'Abner Santos Beiger']);

        $this->postJson('/api/pacientes/duplicados/preview', ['vencedor_id' => $vencedor->id, 'perdedor_id' => $perdedor->id])
            ->assertOk()
            ->assertJsonStructure(['solicitacoes', 'guias', 'antecipacoes', 'telefones', 'documentos', 'arquivos', 'conflito_clinica_id']);
    }

    public function test_mesclar_via_api(): void
    {
        $this->autenticar();
        $vencedor = $this->criarPaciente();
        $perdedor = $this->criarPaciente(['nome' => 'Abner Santos Beiger']);

        $this->postJson('/api/pacientes/duplicados/mesclar', ['vencedor_id' => $vencedor->id, 'perdedor_id' => $perdedor->id])
            ->assertOk()
            ->assertJsonPath('data.id', $vencedor->id);

        $this->assertFalse($perdedor->fresh()->ativo);
        $this->assertSame($vencedor->id, $perdedor->fresh()->mesclado_em_id);
    }

    public function test_mesclar_paciente_ja_mesclado_falha(): void
    {
        $this->autenticar();
        $vencedor = $this->criarPaciente();
        $perdedor = $this->criarPaciente(['nome' => 'Abner Santos Beiger']);
        $terceiro = $this->criarPaciente(['nome' => 'Abner S. Beiger']);

        $this->postJson('/api/pacientes/duplicados/mesclar', ['vencedor_id' => $vencedor->id, 'perdedor_id' => $perdedor->id])
            ->assertOk();

        $this->postJson('/api/pacientes/duplicados/mesclar', ['vencedor_id' => $terceiro->id, 'perdedor_id' => $perdedor->id])
            ->assertStatus(422);
    }

    /** Caso real: a duplicata as vezes ja foi "resolvida" desativando um dos dois lados, sem migrar historico. */
    public function test_duplicados_inclui_pacientes_inativos(): void
    {
        $this->autenticar();

        $this->criarPaciente(['carteirinha' => '33333333333']);
        $this->criarPaciente(['carteirinha' => 'SYNC-CLINICA-102', 'clinica_id' => 102, 'ativo' => false]);

        $resposta = $this->getJson('/api/pacientes/duplicados')->assertOk();

        $par = collect($resposta->json())->firstWhere('similaridade', 100.0);
        $this->assertNotNull($par);
        $ativos = [$par['paciente_a']['ativo'], $par['paciente_b']['ativo']];
        sort($ativos);
        $this->assertSame([false, true], $ativos);
    }

    /** Um par já mesclado não deve voltar a aparecer no relatório. */
    public function test_duplicados_exclui_par_ja_mesclado(): void
    {
        $this->autenticar();
        $vencedor = $this->criarPaciente();
        $perdedor = $this->criarPaciente(['nome' => 'Abner Santos Beiger']);

        $this->postJson('/api/pacientes/duplicados/mesclar', ['vencedor_id' => $vencedor->id, 'perdedor_id' => $perdedor->id])
            ->assertOk();

        $resposta = $this->getJson('/api/pacientes/duplicados')->assertOk();
        $ids = collect($resposta->json())->flatMap(fn ($par) => [$par['paciente_a']['id'], $par['paciente_b']['id']]);
        $this->assertFalse($ids->contains($perdedor->id));
    }
}
