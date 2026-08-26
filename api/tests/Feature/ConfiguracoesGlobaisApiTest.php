<?php

namespace Tests\Feature;

use App\Models\ConfiguracaoGlobal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ConfiguracoesGlobaisApiTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_devolve_os_padroes_sem_precisar_de_registro_previo(): void
    {
        $this->autenticarComToken();

        $this->getJson('/api/configuracoes/globais')
            ->assertOk()
            ->assertJsonPath('data.sessao_minutos', 480)
            ->assertJsonPath('data.senha_alerta_dias', 7)
            ->assertJsonPath('data.sessoes_padrao', 10)
            ->assertJsonPath('data.itens_por_pagina', 15)
            ->assertJsonPath('data.auditoria_retencao_meses', 12)
            ->assertJsonPath('data.carteirinha_retencao_dias', 30)
            ->assertJsonPath('data.unimed_recheck_horas_sucesso', 24)
            ->assertJsonPath('data.unimed_recheck_horas_falha', 2)
            ->assertJsonPath('data.unimed_verificacao_incerta_intervalo_minutos', 60)
            ->assertJsonPath('data.unimed_verificacao_incerta_horario_inicio', '02:00')
            ->assertJsonPath('data.unimed_verificacao_incerta_horario_fim', '12:50');
    }

    public function test_salva_e_valida_os_limites(): void
    {
        $this->autenticarComToken();

        $this->putJson('/api/configuracoes/globais', $this->payloadValido([
            'sessao_minutos' => 120,
            'auditoria_retencao_meses' => 24,
            'carteirinha_retencao_dias' => 45,
        ]))->assertOk()
            ->assertJsonPath('data.sessao_minutos', 120)
            ->assertJsonPath('data.auditoria_retencao_meses', 24);

        $this->putJson('/api/configuracoes/globais', $this->payloadValido([
            'sessao_minutos' => 999999,
        ]))->assertJsonValidationErrors('sessao_minutos');

        $this->putJson('/api/configuracoes/globais', $this->payloadValido([
            'itens_por_pagina' => 1,
        ]))->assertJsonValidationErrors('itens_por_pagina');

        // Piso de 3 meses: prazo menor esvaziaria a trilha antes de qualquer
        // conferencia de fechamento.
        $this->putJson('/api/configuracoes/globais', $this->payloadValido([
            'auditoria_retencao_meses' => 1,
        ]))->assertJsonValidationErrors('auditoria_retencao_meses');

        // Teto de 168h (7 dias): acima disso o reagendamento deixa de ser prazo
        // curto de retry e vira "praticamente nunca".
        $this->putJson('/api/configuracoes/globais', $this->payloadValido([
            'unimed_recheck_horas_falha' => 200,
        ]))->assertJsonValidationErrors('unimed_recheck_horas_falha');

        // Horario de fim precisa vir depois do horario de inicio.
        $this->putJson('/api/configuracoes/globais', $this->payloadValido([
            'unimed_verificacao_incerta_horario_inicio' => '13:00',
            'unimed_verificacao_incerta_horario_fim' => '12:50',
        ]))->assertJsonValidationErrors('unimed_verificacao_incerta_horario_fim');
    }

    private function payloadValido(array $overrides = []): array
    {
        return array_merge([
            'sessao_minutos' => 120,
            'senha_alerta_dias' => 15,
            'sessoes_padrao' => 20,
            'itens_por_pagina' => 50,
            'auditoria_retencao_meses' => 12,
            'carteirinha_retencao_dias' => 30,
            'unimed_recheck_horas_sucesso' => 24,
            'unimed_recheck_horas_falha' => 2,
            'unimed_verificacao_incerta_intervalo_minutos' => 60,
            'unimed_verificacao_incerta_horario_inicio' => '02:00',
            'unimed_verificacao_incerta_horario_fim' => '12:50',
        ], $overrides);
    }

    public function test_token_expira_depois_do_tempo_configurado(): void
    {
        $user = $this->usuario();
        ConfiguracaoGlobal::doTenant((int) $user->tenant_id)->update(['sessao_minutos' => 60]);

        $token = $user->createToken('teste')->plainTextToken;

        // Dentro do prazo.
        $this->withToken($token)->getJson('/api/dashboard')->assertOk();

        // Passado o prazo, contado da emissao.
        Carbon::setTestNow(now()->addMinutes(61));

        $this->withToken($token)->getJson('/api/dashboard')
            ->assertStatus(401)
            ->assertJsonPath('message', 'Sua sessão expirou. Entre novamente.');

        // O token e apagado, nao so recusado: um vazamento de localStorage nao
        // pode deixar credencial viva no banco.
        $this->assertDatabaseCount('personal_access_tokens', 0);

        Carbon::setTestNow();
    }

    public function test_zero_desliga_a_expiracao(): void
    {
        $user = $this->usuario();
        ConfiguracaoGlobal::doTenant((int) $user->tenant_id)->update(['sessao_minutos' => 0]);

        $token = $user->createToken('teste')->plainTextToken;

        Carbon::setTestNow(now()->addYear());
        $this->withToken($token)->getJson('/api/dashboard')->assertOk();
        Carbon::setTestNow();
    }

    private function usuario(): User
    {
        return User::query()->where('email', 'admin@clinica-exemplo.test')->firstOrFail();
    }

    private function autenticarComToken(): void
    {
        $this->withToken($this->usuario()->createToken('teste')->plainTextToken);
    }
}
