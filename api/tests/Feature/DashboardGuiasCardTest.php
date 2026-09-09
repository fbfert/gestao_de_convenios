<?php

namespace Tests\Feature;

use App\Models\ConfiguracaoGlobal;
use App\Models\Convenio;
use App\Models\Especialidade;
use App\Models\Guia;
use App\Models\Paciente;
use App\Models\Profissional;
use App\Models\User;
use App\Services\GuiaService;
use App\Support\GuiaStatus;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class DashboardGuiasCardTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private function autenticar(): User
    {
        $usuario = User::query()->where('email', 'admin@clinica-exemplo.test')->firstOrFail();

        Sanctum::actingAs($usuario);
        TenantContext::set((int) $usuario->tenant_id);
        app(PermissionRegistrar::class)->setPermissionsTeamId((int) $usuario->tenant_id);

        return $usuario;
    }

    private function guia(array $atributos = []): Guia
    {
        $convenio = Convenio::query()->where('nome', 'Unimed')->firstOrFail();
        $especialidade = Especialidade::query()->where('nome', 'Fisioterapia')->firstOrFail();
        $profissional = Profissional::query()->where('especialidade_id', $especialidade->id)->firstOrFail();
        $paciente = Paciente::query()->firstOrFail();

        return Guia::query()->create(array_merge([
            'tenant_id' => TenantContext::get(),
            'convenio_id' => $convenio->id,
            'paciente_id' => $paciente->id,
            'profissional_id' => $profissional->id,
            'especialidade_id' => $especialidade->id,
            'numero_guia' => 'CARD-'.uniqid(),
            'tipo_terapia' => 'especializada',
            'status' => GuiaStatus::UNDER_REVIEW,
            'data_solicitacao' => today()->toDateString(),
        ], $atributos));
    }

    /** @return array<string, array<string, mixed>> */
    private function linhas(): array
    {
        $corpo = $this->getJson('/api/dashboard')->assertOk()->json('data.guias_card');

        return collect($corpo)->keyBy('key')->all();
    }

    public function test_card_traz_as_tres_linhas(): void
    {
        $this->autenticar();

        $this->assertSame(
            ['negadas', 'em_analise', 'senha_vencendo'],
            array_keys($this->linhas()),
        );
    }

    public function test_guia_antiga_negada_hoje_conta_em_hoje(): void
    {
        $this->autenticar();

        // Criada há duas semanas: por created_at ela não contaria em lugar
        // nenhum. É a data da TRANSIÇÃO que manda.
        $guia = $this->guia();
        $guia->forceFill(['created_at' => now()->subDays(14)])->save();

        app(GuiaService::class)->registrarTransicao($guia, GuiaStatus::DENIED);

        $negadas = $this->linhas()['negadas'];

        $this->assertSame(1, $negadas['value']);
        $this->assertStringContainsString('1 hoje', $negadas['detail']);
    }

    public function test_guia_criada_hoje_e_ainda_em_analise_nao_conta_como_negada(): void
    {
        $this->autenticar();
        $this->guia();

        $this->assertSame(0, $this->linhas()['negadas']['value']);
    }

    public function test_guia_com_alerta_ocultado_sai_da_contagem(): void
    {
        $this->autenticar();
        $guia = $this->guia();
        app(GuiaService::class)->registrarTransicao($guia, GuiaStatus::DENIED);

        $this->assertSame(1, $this->linhas()['negadas']['value']);

        app(GuiaService::class)->ocultarAlertaNegacao($guia);

        // O número grande é o que ainda exige ação: quem já foi tratado sai.
        $this->assertSame(0, $this->linhas()['negadas']['value']);
    }

    public function test_mesma_guia_negada_duas_vezes_no_dia_conta_uma(): void
    {
        $this->autenticar();
        $guia = $this->guia();
        $servico = app(GuiaService::class);

        $servico->registrarTransicao($guia, GuiaStatus::DENIED);
        $servico->registrarTransicao($guia, GuiaStatus::UNDER_REVIEW);
        $servico->registrarTransicao($guia, GuiaStatus::DENIED);

        $this->assertStringContainsString('1 hoje', $this->linhas()['negadas']['detail']);
    }

    public function test_janela_de_senha_vencendo_segue_a_configuracao_do_tenant(): void
    {
        $usuario = $this->autenticar();
        $configuracao = ConfiguracaoGlobal::doTenant((int) $usuario->tenant_id);

        // Validade em 10 dias: fora de uma janela de 7, dentro de uma de 15.
        $this->guia(['validade_senha' => today()->copy()->addDays(10)->toDateString()]);

        $configuracao->forceFill(['senha_alerta_dias' => 7])->save();
        $this->assertSame(0, $this->linhas()['senha_vencendo']['value']);

        $configuracao->forceFill(['senha_alerta_dias' => 15])->save();
        $this->assertSame(1, $this->linhas()['senha_vencendo']['value']);
    }

    public function test_senha_ja_vencida_nao_entra_na_janela(): void
    {
        $this->autenticar();
        $this->guia(['validade_senha' => today()->copy()->subDay()->toDateString()]);

        $this->assertSame(0, $this->linhas()['senha_vencendo']['value']);
    }

    public function test_filtro_da_linha_devolve_o_mesmo_conjunto_que_o_card_promete(): void
    {
        $this->autenticar();
        $guia = $this->guia();
        app(GuiaService::class)->registrarTransicao($guia, GuiaStatus::DENIED);

        $doCard = $this->linhas()['negadas']['value'];
        $daListagem = $this->getJson('/api/guias?status=denied&pendente=1')->assertOk()->json('meta.total');

        $this->assertSame($doCard, $daListagem);
    }

    public function test_filtro_de_senha_vencendo_usa_a_janela_do_tenant(): void
    {
        $usuario = $this->autenticar();
        ConfiguracaoGlobal::doTenant((int) $usuario->tenant_id)
            ->forceFill(['senha_alerta_dias' => 15])->save();

        $this->guia(['validade_senha' => today()->copy()->addDays(10)->toDateString()]);

        $total = $this->getJson('/api/guias?senha_vencendo=1')->assertOk()->json('meta.total');

        $this->assertSame(1, $total);
    }

    public function test_sem_permissao_de_guias_o_card_nao_vem(): void
    {
        $usuario = $this->autenticar();

        // A permissão é revogada explicitamente: todos os papéis semeados têm
        // `dashboard.guias`, então usar um papel "mais fraco" testaria outra
        // coisa e passaria por acidente.
        Role::query()
            ->where('name', 'admin')
            ->where('tenant_id', $usuario->tenant_id)
            ->firstOrFail()
            ->revokePermissionTo('dashboard.guias');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $corpo = $this->getJson('/api/dashboard')->assertOk()->json('data');

        $this->assertNull($corpo['guias_card']);
    }
}
