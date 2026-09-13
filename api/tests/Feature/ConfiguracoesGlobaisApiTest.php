<?php

namespace Tests\Feature;

use App\Models\ConfiguracaoGlobal;
use App\Models\Tenant;
use App\Models\User;
use App\Scopes\TenantScope;
use App\Support\TenantContext;
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
            ->assertJsonPath('data.antecipacao_dias', 20)
            ->assertJsonPath('data.antecipacao_referencia', 'validade_senha')
            ->assertJsonPath('data.sessoes_padrao', 10)
            ->assertJsonPath('data.itens_por_pagina', 15)
            ->assertJsonPath('data.auditoria_retencao_meses', 12)
            ->assertJsonPath('data.carteirinha_retencao_dias', 30)
            ->assertJsonPath('data.unimed_recheck_horas_sucesso', 24)
            ->assertJsonPath('data.unimed_recheck_horas_falha', 2)
            ->assertJsonPath('data.unimed_verificacao_incerta_intervalo_minutos', 60)
            ->assertJsonPath('data.unimed_verificacao_incerta_horario_inicio', '02:00')
            ->assertJsonPath('data.unimed_verificacao_incerta_horario_fim', '12:50')
            ->assertJsonPath('data.automacao_reconsulta_status_ativo', true)
            ->assertJsonPath('data.automacao_captura_senha_validade_ativo', true)
            ->assertJsonPath('data.unimed_captura_senha_validade_intervalo_horas', 6)
            ->assertJsonPath('data.automacao_verificacao_incerta_ativo', true)
            ->assertJsonPath('data.automacao_sincronizacao_clinica_ativo', true)
            ->assertJsonPath('data.automacao_sincronizacao_clinica_diurno_horario_inicio', '08:00')
            ->assertJsonPath('data.automacao_sincronizacao_clinica_diurno_horario_fim', '18:00')
            ->assertJsonPath('data.automacao_sincronizacao_clinica_diurno_intervalo_minutos', 10)
            ->assertJsonPath('data.automacao_sincronizacao_clinica_noturno_horario_inicio', '18:00')
            ->assertJsonPath('data.automacao_sincronizacao_clinica_noturno_horario_fim', '22:00')
            ->assertJsonPath('data.automacao_sincronizacao_clinica_noturno_intervalo_minutos', 30)
            ->assertJsonPath('data.automacao_sincronizacao_clinica_madrugada_horario_inicio', '22:00')
            ->assertJsonPath('data.automacao_sincronizacao_clinica_madrugada_horario_fim', '07:59')
            ->assertJsonPath('data.automacao_sincronizacao_clinica_madrugada_intervalo_minutos', 60)
            ->assertJsonPath('data.automacao_expurgo_auditoria_ativo', true)
            ->assertJsonPath('data.automacao_expurgo_carteirinhas_ativo', true)
            ->assertJsonPath('data.automacao_verificacao_guias_diaria_ativo', true);
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

        // Intervalo da busca de senha/validade so aceita as 4 opcoes da tela.
        $this->putJson('/api/configuracoes/globais', $this->payloadValido([
            'unimed_captura_senha_validade_intervalo_horas' => 3,
        ]))->assertJsonValidationErrors('unimed_captura_senha_validade_intervalo_horas');

        // So aceita os 3 valores conhecidos de referencia da antecipacao.
        $this->putJson('/api/configuracoes/globais', $this->payloadValido([
            'antecipacao_referencia' => 'outra_coisa',
        ]))->assertJsonValidationErrors('antecipacao_referencia');
    }

    public function test_aceita_dias_apos_a_guia_criada_como_referencia_de_antecipacao(): void
    {
        $this->autenticarComToken();

        $this->putJson('/api/configuracoes/globais', $this->payloadValido([
            'antecipacao_referencia' => 'data_solicitacao',
            'antecipacao_dias' => 40,
        ]))->assertOk()
            ->assertJsonPath('data.antecipacao_referencia', 'data_solicitacao')
            ->assertJsonPath('data.antecipacao_dias', 40);
    }

    /** A janela "madrugada" cruza a meia-noite de propósito (fim < início) — não pode exigir `after`. */
    public function test_janela_madrugada_da_sincronizacao_clinica_aceita_fim_antes_do_inicio(): void
    {
        $this->autenticarComToken();

        $this->putJson('/api/configuracoes/globais', $this->payloadValido([
            'automacao_sincronizacao_clinica_madrugada_horario_inicio' => '22:00',
            'automacao_sincronizacao_clinica_madrugada_horario_fim' => '07:59',
        ]))->assertOk()
            ->assertJsonPath('data.automacao_sincronizacao_clinica_madrugada_horario_inicio', '22:00')
            ->assertJsonPath('data.automacao_sincronizacao_clinica_madrugada_horario_fim', '07:59');
    }

    public function test_liga_e_desliga_automacoes_individualmente(): void
    {
        $this->autenticarComToken();

        $this->putJson('/api/configuracoes/globais', $this->payloadValido([
            'automacao_reconsulta_status_ativo' => false,
            'automacao_captura_senha_validade_ativo' => false,
            'unimed_captura_senha_validade_intervalo_horas' => 24,
            'automacao_verificacao_incerta_ativo' => false,
            'automacao_sincronizacao_clinica_ativo' => false,
            'automacao_sincronizacao_clinica_diurno_intervalo_minutos' => 15,
            'automacao_expurgo_auditoria_ativo' => false,
            'automacao_expurgo_carteirinhas_ativo' => false,
            'automacao_verificacao_guias_diaria_ativo' => false,
        ]))->assertOk()
            ->assertJsonPath('data.automacao_reconsulta_status_ativo', false)
            ->assertJsonPath('data.automacao_captura_senha_validade_ativo', false)
            ->assertJsonPath('data.unimed_captura_senha_validade_intervalo_horas', 24)
            ->assertJsonPath('data.automacao_verificacao_incerta_ativo', false)
            ->assertJsonPath('data.automacao_sincronizacao_clinica_ativo', false)
            ->assertJsonPath('data.automacao_sincronizacao_clinica_diurno_intervalo_minutos', 15)
            ->assertJsonPath('data.automacao_expurgo_auditoria_ativo', false)
            ->assertJsonPath('data.automacao_expurgo_carteirinhas_ativo', false)
            ->assertJsonPath('data.automacao_verificacao_guias_diaria_ativo', false);
    }

    private function payloadValido(array $overrides = []): array
    {
        return array_merge([
            'sessao_minutos' => 120,
            'senha_alerta_dias' => 15,
            'antecipacao_dias' => 20,
            'antecipacao_referencia' => 'validade_senha',
            'sessoes_padrao' => 20,
            'itens_por_pagina' => 50,
            'auditoria_retencao_meses' => 12,
            'carteirinha_retencao_dias' => 30,
            'unimed_recheck_horas_sucesso' => 24,
            'unimed_recheck_horas_falha' => 2,
            'unimed_verificacao_incerta_intervalo_minutos' => 60,
            'unimed_verificacao_incerta_horario_inicio' => '02:00',
            'unimed_verificacao_incerta_horario_fim' => '12:50',
            'automacao_reconsulta_status_ativo' => true,
            'automacao_captura_senha_validade_ativo' => true,
            'unimed_captura_senha_validade_intervalo_horas' => 6,
            'automacao_verificacao_incerta_ativo' => true,
            'automacao_sincronizacao_clinica_ativo' => true,
            'automacao_sincronizacao_clinica_diurno_horario_inicio' => '08:00',
            'automacao_sincronizacao_clinica_diurno_horario_fim' => '18:00',
            'automacao_sincronizacao_clinica_diurno_intervalo_minutos' => 10,
            'automacao_sincronizacao_clinica_noturno_horario_inicio' => '18:00',
            'automacao_sincronizacao_clinica_noturno_horario_fim' => '22:00',
            'automacao_sincronizacao_clinica_noturno_intervalo_minutos' => 30,
            'automacao_sincronizacao_clinica_madrugada_horario_inicio' => '22:00',
            'automacao_sincronizacao_clinica_madrugada_horario_fim' => '07:59',
            'automacao_sincronizacao_clinica_madrugada_intervalo_minutos' => 60,
            'automacao_expurgo_auditoria_ativo' => true,
            'automacao_expurgo_carteirinhas_ativo' => true,
            'automacao_verificacao_guias_diaria_ativo' => true,
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

    /**
     * `itens_por_pagina` era enfeite: editável, salva e auditada, e nenhuma
     * linha de código a lia — cada controller trazia o próprio número fixo. O
     * operador mudava, via o registro na auditoria, e a listagem continuava
     * igual. Este teste existe para que a configuração não volte a mentir.
     */
    public function test_itens_por_pagina_governa_o_tamanho_das_listagens(): void
    {
        $this->autenticarComToken();

        ConfiguracaoGlobal::doTenant((int) $this->usuario()->tenant_id)
            ->update(['itens_por_pagina' => 7]);

        $this->getJson('/api/guias')->assertOk()->assertJsonPath('meta.per_page', 7);
        $this->getJson('/api/solicitacoes')->assertOk()->assertJsonPath('meta.per_page', 7);
        $this->getJson('/api/usuarios')->assertOk()->assertJsonPath('meta.per_page', 7);
    }

    /** Um `per_page` explícito na query continua mandando — é o caso das buscas dirigidas. */
    public function test_per_page_explicito_prevalece_sobre_a_configuracao(): void
    {
        $this->autenticarComToken();

        ConfiguracaoGlobal::doTenant((int) $this->usuario()->tenant_id)
            ->update(['itens_por_pagina' => 7]);

        $this->getJson('/api/guias?per_page=30')->assertOk()->assertJsonPath('meta.per_page', 30);
    }

    /**
     * `doTenant()` recebe o tenant por parâmetro e precisa valer para ele. Sob o
     * TenantScope, chamado de dentro de outro tenant, não enxergava a linha
     * existente e batia no índice único ao tentar criar a segunda.
     */
    public function test_configuracao_de_outro_tenant_e_lida_sem_esbarrar_no_indice_unico(): void
    {
        $tenantId = (int) $this->usuario()->tenant_id;
        TenantContext::set($tenantId);
        ConfiguracaoGlobal::doTenant($tenantId);

        $outroTenant = Tenant::query()->create([
            'nome' => 'Clínica Config Vizinha',
            'slug' => 'clinica-config-vizinha',
            'cnpj' => '33.333.333/0001-33',
            'ativo' => true,
        ]);

        // Duas vezes: a primeira cria a linha do outro tenant, a segunda tem de
        // encontrá-la em vez de tentar criar de novo.
        ConfiguracaoGlobal::doTenant((int) $outroTenant->id);
        $configuracao = ConfiguracaoGlobal::doTenant((int) $outroTenant->id);

        $this->assertSame((int) $outroTenant->id, (int) $configuracao->tenant_id);
        $this->assertSame(
            1,
            ConfiguracaoGlobal::query()
                ->withoutGlobalScope(TenantScope::class)
                ->where('tenant_id', $outroTenant->id)
                ->count(),
        );
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
