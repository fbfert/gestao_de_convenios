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
            ->assertJsonPath('data.itens_por_pagina', 15);
    }

    public function test_salva_e_valida_os_limites(): void
    {
        $this->autenticarComToken();

        $this->putJson('/api/configuracoes/globais', [
            'sessao_minutos' => 120,
            'senha_alerta_dias' => 15,
            'sessoes_padrao' => 20,
            'itens_por_pagina' => 50,
        ])->assertOk()->assertJsonPath('data.sessao_minutos', 120);

        $this->putJson('/api/configuracoes/globais', [
            'sessao_minutos' => 999999,
            'senha_alerta_dias' => 15,
            'sessoes_padrao' => 20,
            'itens_por_pagina' => 50,
        ])->assertJsonValidationErrors('sessao_minutos');

        $this->putJson('/api/configuracoes/globais', [
            'sessao_minutos' => 120,
            'senha_alerta_dias' => 15,
            'sessoes_padrao' => 20,
            'itens_por_pagina' => 1,
        ])->assertJsonValidationErrors('itens_por_pagina');
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
