<?php

namespace Tests\Feature;

use App\Models\Convenio;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ConveniosApiTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_atualiza_descricao_do_convenio(): void
    {
        $this->autenticar();

        $convenio = Convenio::query()->where('nome', 'Unimed')->firstOrFail();

        $this->patchJson("/api/convenios/{$convenio->id}", [
            'nome' => 'Unimed',
            'descricao' => 'Descrição atualizada pelo teste',
            'connector_type' => 'manual',
            'ativo' => true,
        ])
            ->assertOk()
            ->assertJsonPath('data.id', $convenio->id)
            ->assertJsonPath('data.descricao', 'Descrição atualizada pelo teste');

        $this->assertDatabaseHas('convenios', [
            'id' => $convenio->id,
            'descricao' => 'Descrição atualizada pelo teste',
        ]);
    }

    /**
     * A listagem só devolve `ativo = true`, e a tela de edição hidratava o
     * formulário a partir dela: abrir a edição de um convênio inativo mostrava
     * "não encontrado", e reativá-lo passa justamente por editá-lo.
     */
    public function test_busca_convenio_inativo_pelo_id(): void
    {
        $this->autenticar();

        $convenio = Convenio::query()->where('nome', 'Unimed')->firstOrFail();
        $convenio->update(['ativo' => false]);

        // Continua fora da listagem...
        $this->assertNotContains(
            $convenio->id,
            $this->getJson('/api/convenios')->assertOk()->json('data.*.id'),
        );

        // ...mas é alcançável pelo id, que é o que a tela de edição precisa.
        $this->getJson("/api/convenios/{$convenio->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $convenio->id)
            ->assertJsonPath('data.ativo', false);
    }

    public function test_convenio_de_outro_tenant_retorna_404(): void
    {
        $this->autenticar();

        $outroTenant = Tenant::query()->create([
            'nome' => 'Clínica Externa Convênios',
            'slug' => 'clinica-externa-convenios',
            'cnpj' => '66.666.666/0001-66',
            'ativo' => true,
        ]);

        $convenio = Convenio::query()->create([
            'tenant_id' => $outroTenant->id,
            'nome' => 'Convênio de Fora',
            'connector_type' => 'manual',
            'ativo' => true,
        ]);

        $this->getJson("/api/convenios/{$convenio->id}")->assertNotFound();
    }

    public function test_aceita_dias_apos_a_guia_criada_como_override_de_antecipacao(): void
    {
        $this->autenticar();

        $convenio = Convenio::query()->where('nome', 'Unimed')->firstOrFail();

        $this->patchJson("/api/convenios/{$convenio->id}", [
            'nome' => 'Unimed',
            'connector_type' => 'manual',
            'ativo' => true,
            'antecipacao_dias' => 35,
            'antecipacao_referencia' => 'data_solicitacao',
        ])
            ->assertOk()
            ->assertJsonPath('data.antecipacao_dias', 35)
            ->assertJsonPath('data.antecipacao_referencia', 'data_solicitacao');
    }

    public function test_recusa_referencia_de_antecipacao_desconhecida(): void
    {
        $this->autenticar();

        $convenio = Convenio::query()->where('nome', 'Unimed')->firstOrFail();

        $this->patchJson("/api/convenios/{$convenio->id}", [
            'nome' => 'Unimed',
            'connector_type' => 'manual',
            'ativo' => true,
            'antecipacao_referencia' => 'outra_coisa',
        ])->assertJsonValidationErrors('antecipacao_referencia');
    }

    private function autenticar(): void
    {
        $user = User::query()->where('email', 'admin@clinica-exemplo.test')->firstOrFail();
        Sanctum::actingAs($user);
        TenantContext::set((int) $user->tenant_id);
    }
}
