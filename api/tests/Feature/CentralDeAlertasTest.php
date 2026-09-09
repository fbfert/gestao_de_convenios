<?php

namespace Tests\Feature;

use App\Models\Alerta;
use App\Models\AlertaRegra;
use App\Models\Convenio;
use App\Models\Especialidade;
use App\Models\Guia;
use App\Models\Paciente;
use App\Models\Profissional;
use App\Models\SaudeComponente;
use App\Models\User;
use App\Services\Alertas\AvaliadorDeAlertas;
use App\Services\GuiaService;
use App\Support\GuiaStatus;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class CentralDeAlertasTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private int $tenantId;

    private function autenticar(): User
    {
        $usuario = User::query()->where('email', 'admin@clinica-exemplo.test')->firstOrFail();

        Sanctum::actingAs($usuario);
        TenantContext::set((int) $usuario->tenant_id);
        app(PermissionRegistrar::class)->setPermissionsTeamId((int) $usuario->tenant_id);
        $this->tenantId = (int) $usuario->tenant_id;

        return $usuario;
    }

    private function guiaNegada(): Guia
    {
        $convenio = Convenio::query()->where('nome', 'Unimed')->firstOrFail();
        $especialidade = Especialidade::query()->where('nome', 'Fisioterapia')->firstOrFail();
        $profissional = Profissional::query()->where('especialidade_id', $especialidade->id)->firstOrFail();
        $paciente = Paciente::query()->firstOrFail();

        $guia = Guia::query()->create([
            'tenant_id' => $this->tenantId,
            'convenio_id' => $convenio->id,
            'paciente_id' => $paciente->id,
            'profissional_id' => $profissional->id,
            'especialidade_id' => $especialidade->id,
            'numero_guia' => 'ALERTA-'.uniqid(),
            'tipo_terapia' => 'especializada',
            'status' => GuiaStatus::UNDER_REVIEW,
            'data_solicitacao' => today()->toDateString(),
        ]);

        app(GuiaService::class)->registrarTransicao($guia, GuiaStatus::DENIED);

        return $guia;
    }

    private function avaliar(): array
    {
        return app(AvaliadorDeAlertas::class)->avaliarTenant($this->tenantId);
    }

    public function test_avaliar_duas_vezes_nao_duplica(): void
    {
        $this->autenticar();
        $this->guiaNegada();

        $this->avaliar();
        $this->avaliar();

        $abertos = Alerta::query()
            ->where('chave', AlertaRegra::CHAVE_GUIA_NEGADA)
            ->aberto()
            ->count();

        $this->assertSame(1, $abertos);
    }

    public function test_condicao_que_some_resolve_o_alerta(): void
    {
        $this->autenticar();
        $guia = $this->guiaNegada();

        $this->avaliar();
        $this->assertSame(1, Alerta::query()->where('chave', AlertaRegra::CHAVE_GUIA_NEGADA)->aberto()->count());

        // Ocultar o alerta da guia é o que tira a condição de pé.
        app(GuiaService::class)->ocultarAlertaNegacao($guia);
        $this->avaliar();

        $this->assertSame(0, Alerta::query()->where('chave', AlertaRegra::CHAVE_GUIA_NEGADA)->aberto()->count());
        $this->assertSame(1, Alerta::query()->where('chave', AlertaRegra::CHAVE_GUIA_NEGADA)->resolvido()->count());
    }

    public function test_condicao_que_volta_abre_alerta_novo_preservando_o_resolvido(): void
    {
        $this->autenticar();
        $guia = $this->guiaNegada();

        $this->avaliar();
        app(GuiaService::class)->ocultarAlertaNegacao($guia);
        $this->avaliar();

        // Volta a valer: o índice único precisa permitir isto, senão a
        // deduplicação impediria o alerta de nascer de novo.
        $guia->forceFill(['alerta_negacao_ocultado_em' => null])->save();
        $this->avaliar();

        $this->assertSame(1, Alerta::query()->where('chave', AlertaRegra::CHAVE_GUIA_NEGADA)->aberto()->count());
        $this->assertSame(1, Alerta::query()->where('chave', AlertaRegra::CHAVE_GUIA_NEGADA)->resolvido()->count());
    }

    public function test_regra_desativada_nao_gera_nada(): void
    {
        $this->autenticar();
        $this->guiaNegada();

        AlertaRegra::query()->where('chave', AlertaRegra::CHAVE_GUIA_NEGADA)->update(['ativo' => false]);
        $this->avaliar();

        $this->assertSame(0, Alerta::query()->where('chave', AlertaRegra::CHAVE_GUIA_NEGADA)->count());
    }

    public function test_desativar_regra_resolve_o_que_ja_estava_aberto(): void
    {
        $this->autenticar();
        $this->guiaNegada();
        $this->avaliar();

        AlertaRegra::query()->where('chave', AlertaRegra::CHAVE_GUIA_NEGADA)->update(['ativo' => false]);
        $this->avaliar();

        // Sem isto, desligar a regra deixaria alertas órfãos para sempre.
        $this->assertSame(0, Alerta::query()->where('chave', AlertaRegra::CHAVE_GUIA_NEGADA)->aberto()->count());
    }

    public function test_alerta_silenciado_nao_aparece_entre_os_pendentes(): void
    {
        $this->autenticar();
        $this->guiaNegada();
        $this->avaliar();

        $alerta = Alerta::query()->where('chave', AlertaRegra::CHAVE_GUIA_NEGADA)->aberto()->firstOrFail();

        $this->postJson("/api/alertas/{$alerta->id}/silenciar", ['ate' => now()->addDays(3)->toIso8601String()])
            ->assertOk();

        // Filtrado por chave: o tenant semeado tem o componente `scheduler` sem
        // heartbeat, que também gera alerta — contar tudo mediria outra coisa.
        $chave = AlertaRegra::CHAVE_GUIA_NEGADA;

        $this->assertSame(0, $this->getJson("/api/alertas?chave={$chave}")->assertOk()->json('meta.total'));
        $this->assertSame(
            1,
            $this->getJson("/api/alertas?situacao=silenciado&chave={$chave}")->assertOk()->json('meta.total'),
        );
    }

    public function test_reconhecer_registra_quem_e_quando(): void
    {
        $usuario = $this->autenticar();
        $this->guiaNegada();
        $this->avaliar();

        $alerta = Alerta::query()->aberto()->where('chave', AlertaRegra::CHAVE_GUIA_NEGADA)->firstOrFail();

        $this->postJson("/api/alertas/{$alerta->id}/reconhecer")->assertOk();

        $this->assertSame($usuario->id, $alerta->fresh()->reconhecido_por);
        $this->assertNotNull($alerta->fresh()->reconhecido_em);
    }

    public function test_senha_vencendo_segue_o_limiar_da_regra(): void
    {
        $this->autenticar();
        $convenio = Convenio::query()->where('nome', 'Unimed')->firstOrFail();
        $especialidade = Especialidade::query()->where('nome', 'Fisioterapia')->firstOrFail();
        $profissional = Profissional::query()->where('especialidade_id', $especialidade->id)->firstOrFail();
        $paciente = Paciente::query()->firstOrFail();

        Guia::query()->create([
            'tenant_id' => $this->tenantId,
            'convenio_id' => $convenio->id,
            'paciente_id' => $paciente->id,
            'profissional_id' => $profissional->id,
            'especialidade_id' => $especialidade->id,
            'numero_guia' => 'SENHA-'.uniqid(),
            'tipo_terapia' => 'especializada',
            'status' => GuiaStatus::UNDER_REVIEW,
            'data_solicitacao' => today()->toDateString(),
            'validade_senha' => today()->copy()->addDays(5)->toDateString(),
        ]);

        // Limiar 2: cinco dias ainda é amarelo.
        $this->avaliar();
        $alerta = Alerta::query()->where('chave', AlertaRegra::CHAVE_SENHA_VENCENDO)->aberto()->firstOrFail();
        $this->assertSame(Alerta::NIVEL_AMARELO, $alerta->nivel);

        // Limiar 7: os mesmos cinco dias passam a ser vermelhos, sem código novo.
        AlertaRegra::query()
            ->where('chave', AlertaRegra::CHAVE_SENHA_VENCENDO)
            ->update(['limiar_vermelho' => 7]);
        $this->avaliar();

        $this->assertSame(Alerta::NIVEL_VERMELHO, $alerta->fresh()->nivel);
    }

    public function test_componente_fora_vira_alerta(): void
    {
        $this->autenticar();

        $componente = SaudeComponente::query()->withoutGlobalScopes()->create([
            'tenant_id' => $this->tenantId,
            'chave' => 'conector.morto',
            'nome' => 'Conector morto',
            'tipo' => 'connector',
            'intervalo_esperado_segundos' => 60,
            'ativo' => true,
            'ultimo_heartbeat_em' => now()->subSeconds(600),
        ]);

        $this->avaliar();

        // Pelo componente, e não pela contagem total: o `scheduler` semeado
        // também nasce sem heartbeat e gera o seu próprio alerta.
        $this->assertSame(
            1,
            Alerta::query()
                ->where('chave', AlertaRegra::CHAVE_COMPONENTE_FORA)
                ->where('entidade', 'saude_componentes')
                ->where('entidade_id', $componente->id)
                ->aberto()
                ->count(),
        );
    }

    public function test_card_do_dashboard_ignora_verde(): void
    {
        $this->autenticar();

        Alerta::query()->create([
            'tenant_id' => $this->tenantId,
            'chave' => 'teste.verde',
            'nivel' => Alerta::NIVEL_VERDE,
            'titulo' => 'Informativo',
            'aberto_em' => now(),
            'aberto_dedupe' => Alerta::DEDUPE_ABERTO,
        ]);

        $card = $this->getJson('/api/dashboard')->assertOk()->json('data.alertas_card');

        $this->assertSame([], $card);
    }

    public function test_sem_permissao_de_ver_alertas_a_listagem_recusa(): void
    {
        $usuario = $this->autenticar();

        Role::query()
            ->where('name', 'admin')
            ->where('tenant_id', $usuario->tenant_id)
            ->firstOrFail()
            ->revokePermissionTo('alertas.view');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->getJson('/api/alertas')->assertForbidden();
    }

    public function test_sem_permissao_de_gerir_nao_altera_regra(): void
    {
        $usuario = $this->autenticar();
        $regra = AlertaRegra::query()->where('chave', AlertaRegra::CHAVE_GUIA_NEGADA)->firstOrFail();

        Role::query()
            ->where('name', 'admin')
            ->where('tenant_id', $usuario->tenant_id)
            ->firstOrFail()
            ->revokePermissionTo('alertas.manage');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->putJson("/api/alertas/regras/{$regra->id}", [
            'ativo' => false,
            'nivel_base' => Alerta::NIVEL_AMARELO,
            'limiar_amarelo' => null,
            'limiar_vermelho' => null,
            'critica' => false,
        ])->assertForbidden();
    }

    public function test_tenant_novo_nasce_com_as_regras_padrao(): void
    {
        $this->autenticar();

        $chaves = AlertaRegra::query()->pluck('chave')->sort()->values()->all();

        $this->assertSame([
            AlertaRegra::CHAVE_AUTOMACAO_FALHAS,
            AlertaRegra::CHAVE_COMPONENTE_FORA,
            AlertaRegra::CHAVE_GUIA_NEGADA,
            AlertaRegra::CHAVE_SENHA_VENCENDO,
        ], $chaves);
    }
}
