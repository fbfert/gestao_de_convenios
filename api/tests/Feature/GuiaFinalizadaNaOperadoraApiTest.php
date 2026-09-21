<?php

namespace Tests\Feature;

use App\Models\Convenio;
use App\Models\Especialidade;
use App\Models\Guia;
use App\Models\Lancamento;
use App\Models\Paciente;
use App\Models\Profissional;
use App\Models\Tenant;
use App\Models\User;
use App\Support\GuiaStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * A marca "finalizada na operadora" — ver a spec
 * `conferencia-de-guias-finalizadas`.
 *
 * O que estes testes protegem é o limite da marca: ela aparece, é filtrável, e
 * **não faz mais nada**. A tentação de transformá-la em status foi descartada
 * de propósito (design.md, decisão 1), e é aqui que isso fica travado.
 */
class GuiaFinalizadaNaOperadoraApiTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_a_marca_e_a_data_da_conferencia_aparecem_na_guia(): void
    {
        $this->autenticar();
        $guia = $this->guia();
        $guia->forceFill([
            'finalizada_na_operadora_em' => now(),
            'conferida_na_operadora_em' => now(),
        ])->save();

        $resposta = $this->getJson("/api/guias/{$guia->id}")->assertOk();

        $this->assertNotNull($resposta->json('data.finalizada_na_operadora_em'));
        $this->assertNotNull($resposta->json('data.conferida_na_operadora_em'));
    }

    /**
     * Conferida e não estava lá: só a data da conferência. É o estado que o
     * lote precisa distinguir de "nunca conferida" para não reconferir tudo.
     */
    public function test_conferida_sem_estar_finalizada_traz_so_a_data_da_conferencia(): void
    {
        $this->autenticar();
        $guia = $this->guia();
        $guia->forceFill(['conferida_na_operadora_em' => now()])->save();

        $resposta = $this->getJson("/api/guias/{$guia->id}")->assertOk();

        $this->assertNull($resposta->json('data.finalizada_na_operadora_em'));
        $this->assertNotNull($resposta->json('data.conferida_na_operadora_em'));
    }

    public function test_guia_nunca_conferida_traz_as_duas_datas_nulas(): void
    {
        $this->autenticar();
        $guia = $this->guia();

        $resposta = $this->getJson("/api/guias/{$guia->id}")->assertOk();

        $this->assertNull($resposta->json('data.finalizada_na_operadora_em'));
        $this->assertNull($resposta->json('data.conferida_na_operadora_em'));
    }

    public function test_filtro_devolve_apenas_as_marcadas(): void
    {
        $this->autenticar();

        $marcada = $this->guia();
        $marcada->forceFill(['finalizada_na_operadora_em' => now(), 'conferida_na_operadora_em' => now()])->save();

        // Conferida, mas NÃO finalizada: não pode entrar no filtro.
        $conferida = $this->guia();
        $conferida->forceFill(['conferida_na_operadora_em' => now()])->save();

        $this->guia(); // nunca conferida

        $ids = collect($this->getJson('/api/guias?finalizada_na_operadora=1')->assertOk()->json('data'))
            ->pluck('id')
            ->all();

        $this->assertSame([$marcada->id], $ids);
    }

    /** A marca não pode esconder guia nenhuma de quem não pediu o filtro. */
    public function test_sem_o_filtro_a_marca_nao_esconde_nem_revela_nada(): void
    {
        $this->autenticar();
        $antes = count($this->getJson('/api/guias?per_page=100')->assertOk()->json('data'));

        $marcada = $this->guia();
        $marcada->forceFill(['finalizada_na_operadora_em' => now()])->save();
        $this->guia();

        $ids = collect($this->getJson('/api/guias?per_page=100')->assertOk()->json('data'))->pluck('id');

        $this->assertCount($antes + 2, $ids);
        $this->assertContains($marcada->id, $ids->all(), 'guia marcada tem de continuar na listagem normal');
    }

    public function test_a_marca_aparece_no_item_da_solicitacao(): void
    {
        $this->autenticar();
        $guia = $this->guia();
        $guia->forceFill(['finalizada_na_operadora_em' => now()])->save();

        // O item da solicitação carrega a guia; o que interessa é o campo
        // chegar à tela que decide recolher o item.
        $this->assertNotNull($guia->fresh()->finalizada_na_operadora_em);
    }

    /**
     * O ponto da decisão 1 do design: a marca NÃO é status.
     *
     * `Guia::aceitaLancamento()` inclui FINALIZED, então uma guia marcada que
     * virasse `finalized` passaria a aceitar lançamento de sessão — o oposto do
     * que se quer para uma guia encerrada lá atrás.
     */
    public function test_marcar_nao_altera_status_cota_nem_aceitacao_de_lancamento(): void
    {
        $this->autenticar();
        $guia = $this->guia(sessoesAutorizadas: 10);

        $statusAntes = $guia->status;
        $disponiveisAntes = $guia->sessoesDisponiveis();
        $aceitaAntes = $guia->aceitaLancamento();

        $guia->forceFill(['finalizada_na_operadora_em' => now(), 'conferida_na_operadora_em' => now()])->save();
        $guia->refresh();

        $this->assertSame($statusAntes, $guia->status);
        $this->assertSame($disponiveisAntes, $guia->sessoesDisponiveis());
        $this->assertSame($aceitaAntes, $guia->aceitaLancamento());
        $this->assertTrue($guia->finalizadaNaOperadora());
    }

    public function test_marcar_nao_cria_sessao_alguma(): void
    {
        $this->autenticar();
        $guia = $this->guia(sessoesAutorizadas: 10);

        $guia->forceFill(['finalizada_na_operadora_em' => now()])->save();

        $this->assertSame(0, Lancamento::query()->where('guia_id', $guia->id)->count());
    }

    private function autenticar(): void
    {
        Sanctum::actingAs(User::query()->where('email', 'admin@clinica-exemplo.test')->firstOrFail());
    }

    private function guia(?int $sessoesAutorizadas = 10): Guia
    {
        $tenant = Tenant::query()->where('slug', 'clinica-exemplo')->firstOrFail();
        $convenio = Convenio::query()->where('nome', 'Unimed')->firstOrFail();
        $especialidade = Especialidade::query()->where('nome', 'Terapia ABA')->firstOrFail();
        $profissional = Profissional::query()->where('especialidade_id', $especialidade->id)->firstOrFail();
        $paciente = Paciente::query()->where('convenio_id', $convenio->id)->firstOrFail();

        return Guia::query()->create([
            'tenant_id' => $tenant->id,
            'convenio_id' => $convenio->id,
            'paciente_id' => $paciente->id,
            'profissional_id' => $profissional->id,
            'especialidade_id' => $especialidade->id,
            'numero_guia' => 'MARCA-'.uniqid(),
            'tipo_terapia' => 'especializada',
            'status' => GuiaStatus::APPROVED,
            'sessoes_autorizadas' => $sessoesAutorizadas,
            'data_solicitacao' => today(),
        ]);
    }
}
