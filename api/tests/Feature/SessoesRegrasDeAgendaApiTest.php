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
use App\Services\LancamentoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * As regras de agenda vistas de fora, pela API — ver a spec
 * `sessoes-regras-de-agenda`. O avaliador em si tem teste próprio
 * (AvaliadorDeAgendaTest); aqui o que importa é que cada caminho de entrada
 * de sessão passa por ele e que nada é gravado quando ele acusa conflito.
 */
class SessoesRegrasDeAgendaApiTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_confirmacao_da_folha_recusa_conflito_e_nao_grava_nada(): void
    {
        $this->autenticar();
        $guia = $this->guiaAprovada('Terapia ABA');

        $resposta = $this->postJson("/api/guias/{$guia->id}/lancamentos/importar-transcricao", [
            'profissional_id' => $guia->profissional_id,
            'confirmar_envio' => true,
            'sessoes' => [
                ['data_sessao' => '2026-09-21', 'hora_inicio' => '08:00'],
                ['data_sessao' => '2026-09-21', 'hora_inicio' => '08:30'],
            ],
        ]);

        $resposta->assertStatus(422)
            ->assertJsonPath('conflitos.0.tipo', 'intervalo')
            ->assertJsonPath('conflitos.0.referencia', '1');

        $this->assertStringContainsString('30 minutos', $resposta->json('conflitos.0.mensagem'));
        $this->assertSame(0, $guia->lancamentos()->count());
    }

    public function test_confirmacao_da_folha_devolve_todos_os_conflitos_de_uma_vez(): void
    {
        $this->autenticar();
        $guia = $this->guiaAprovada('Terapia ABA');

        $resposta = $this->postJson("/api/guias/{$guia->id}/lancamentos/importar-transcricao", [
            'profissional_id' => $guia->profissional_id,
            'confirmar_envio' => true,
            'sessoes' => [
                ['data_sessao' => '2026-09-21', 'hora_inicio' => '08:00'],
                ['data_sessao' => '2026-09-21', 'hora_inicio' => '08:10'],
                ['data_sessao' => '2026-09-22', 'hora_inicio' => '08:00'],
                ['data_sessao' => '2026-09-22', 'hora_inicio' => '08:20'],
            ],
        ]);

        $resposta->assertStatus(422);
        $this->assertCount(2, $resposta->json('conflitos'));
    }

    public function test_confirmacao_da_folha_aponta_a_guia_da_sessao_ja_gravada(): void
    {
        $this->autenticar();
        $guiaAntiga = $this->guiaAprovada('Terapia ABA');
        $this->gravarSessao($guiaAntiga, '2026-09-21', '08:00');

        $guiaNova = $this->guiaAprovada('Terapia ABA', paciente: $guiaAntiga->paciente);

        $resposta = $this->postJson("/api/guias/{$guiaNova->id}/lancamentos/importar-transcricao", [
            'profissional_id' => $guiaNova->profissional_id,
            'confirmar_envio' => true,
            'sessoes' => [
                ['data_sessao' => '2026-09-21', 'hora_inicio' => '08:20'],
            ],
        ]);

        $resposta->assertStatus(422)
            ->assertJsonPath('conflitos.0.guia_numero', $guiaAntiga->numero_guia);

        $this->assertSame(0, $guiaNova->lancamentos()->count());
    }

    public function test_confirmacao_da_folha_passa_quando_as_sessoes_respeitam_as_regras(): void
    {
        $this->autenticar();
        $guia = $this->guiaAprovada('Terapia ABA');

        $this->postJson("/api/guias/{$guia->id}/lancamentos/importar-transcricao", [
            'profissional_id' => $guia->profissional_id,
            'confirmar_envio' => true,
            'sessoes' => [
                ['data_sessao' => '2026-09-21', 'hora_inicio' => '08:00'],
                ['data_sessao' => '2026-09-21', 'hora_inicio' => '09:00'],
            ],
        ])->assertCreated();

        $this->assertSame(2, $guia->lancamentos()->count());
    }

    /**
     * A divergência de paciente aceita justificativa; conflito de agenda não.
     * Mandar as duas coisas juntas não abre exceção nenhuma.
     */
    public function test_conflito_de_agenda_nao_aceita_justificativa(): void
    {
        $this->autenticar();
        $guia = $this->guiaAprovada('Terapia ABA');

        $this->postJson("/api/guias/{$guia->id}/lancamentos/importar-transcricao", [
            'profissional_id' => $guia->profissional_id,
            'confirmar_envio' => true,
            'sessoes' => [
                ['data_sessao' => '2026-09-21', 'hora_inicio' => '08:00'],
                ['data_sessao' => '2026-09-21', 'hora_inicio' => '08:30'],
            ],
            'divergencia' => 'Nome do paciente: a folha diz outra coisa',
            'divergencia_justificativa' => 'Conferido na recepção com documento em mãos.',
        ])->assertStatus(422)
            ->assertJsonPath('conflitos.0.tipo', 'intervalo');

        $this->assertSame(0, $guia->lancamentos()->count());
    }

    public function test_conferencia_da_agenda_nao_grava_e_devolve_conflitos(): void
    {
        $this->autenticar();
        $guia = $this->guiaAprovada('Terapia ABA');

        $this->postJson("/api/guias/{$guia->id}/lancamentos/conferir-agenda", [
            'profissional_id' => $guia->profissional_id,
            'sessoes' => [
                ['data_sessao' => '2026-09-21', 'hora_inicio' => '08:00'],
                ['data_sessao' => '2026-09-21', 'hora_inicio' => '08:30'],
            ],
        ])->assertOk()
            ->assertJsonPath('data.conflitos.0.tipo', 'intervalo')
            ->assertJsonPath('data.conflitos.0.referencia', '1');

        $this->assertSame(0, $guia->lancamentos()->count());
    }

    public function test_conferencia_da_agenda_devolve_o_aviso_de_choque_do_profissional(): void
    {
        $this->autenticar();
        $guiaOutro = $this->guiaAprovada('Terapia ABA');
        $this->gravarSessao($guiaOutro, '2026-09-21', '08:00');

        $outroPaciente = Paciente::query()->whereKeyNot($guiaOutro->paciente_id)->firstOrFail();
        $guia = $this->guiaAprovada('Terapia ABA', paciente: $outroPaciente);

        $resposta = $this->postJson("/api/guias/{$guia->id}/lancamentos/conferir-agenda", [
            'profissional_id' => $guia->profissional_id,
            'sessoes' => [
                ['data_sessao' => '2026-09-21', 'hora_inicio' => '08:00'],
            ],
        ])->assertOk();

        $this->assertSame([], $resposta->json('data.conflitos'));
        $this->assertSame('choque_profissional', $resposta->json('data.avisos.0.tipo'));
    }

    public function test_conferencia_da_agenda_fica_limpa_quando_nao_ha_conflito(): void
    {
        $this->autenticar();
        $guia = $this->guiaAprovada('Terapia ABA');

        $this->postJson("/api/guias/{$guia->id}/lancamentos/conferir-agenda", [
            'profissional_id' => $guia->profissional_id,
            'sessoes' => [
                ['data_sessao' => '2026-09-21', 'hora_inicio' => '08:00'],
                ['data_sessao' => '2026-09-21', 'hora_inicio' => '09:00'],
            ],
        ])->assertOk()
            ->assertJsonPath('data.conflitos', [])
            ->assertJsonPath('data.avisos', []);
    }

    public function test_cadastro_avulso_recusa_conflito(): void
    {
        $this->autenticar();
        $guia = $this->guiaAprovada('Terapia ABA');
        $this->gravarSessao($guia, '2026-09-21', '08:00');

        $this->postJson("/api/guias/{$guia->id}/lancamentos", [
            'profissional_id' => $guia->profissional_id,
            'data_sessao' => '2026-09-21',
            'hora_inicio' => '08:30',
        ])->assertStatus(422)
            ->assertJsonPath('conflitos.0.tipo', 'intervalo');

        $this->assertSame(1, $guia->lancamentos()->count());
    }

    public function test_cadastro_avulso_passa_com_o_intervalo_respeitado(): void
    {
        $this->autenticar();
        $guia = $this->guiaAprovada('Terapia ABA');
        $this->gravarSessao($guia, '2026-09-21', '08:00');

        $this->postJson("/api/guias/{$guia->id}/lancamentos", [
            'profissional_id' => $guia->profissional_id,
            'data_sessao' => '2026-09-21',
            'hora_inicio' => '09:00',
        ])->assertCreated();

        $this->assertSame(2, $guia->lancamentos()->count());
    }

    public function test_edicao_de_sessao_recusa_conflito(): void
    {
        $this->autenticar();
        $guia = $this->guiaAprovada('Terapia ABA');
        $this->gravarSessao($guia, '2026-09-21', '08:00');
        $alvo = $this->gravarSessao($guia, '2026-09-21', '10:00');

        $this->patchJson("/api/lancamentos/{$alvo->id}", [
            'hora_inicio' => '08:20',
        ])->assertStatus(422)
            ->assertJsonPath('conflitos.0.tipo', 'intervalo');

        $this->assertSame('10:00', substr((string) $alvo->fresh()->hora_inicio, 0, 5));
    }

    public function test_edicao_de_sessao_nao_conflita_consigo_mesma(): void
    {
        $this->autenticar();
        $guia = $this->guiaAprovada('Terapia ABA');
        $alvo = $this->gravarSessao($guia, '2026-09-21', '08:00');

        $this->patchJson("/api/lancamentos/{$alvo->id}", [
            'hora_inicio' => '08:10',
        ])->assertOk();

        $this->assertSame('08:10', substr((string) $alvo->fresh()->hora_inicio, 0, 5));
    }

    public function test_limite_diario_da_especialidade_convencional_e_de_uma_sessao(): void
    {
        $this->autenticar();
        $guia = $this->guiaAprovada('Fonoaudiologia');

        $this->postJson("/api/guias/{$guia->id}/lancamentos/importar-transcricao", [
            'profissional_id' => $guia->profissional_id,
            'confirmar_envio' => true,
            'sessoes' => [
                ['data_sessao' => '2026-09-21', 'hora_inicio' => '08:00'],
                ['data_sessao' => '2026-09-21', 'hora_inicio' => '14:00'],
            ],
        ])->assertStatus(422)
            ->assertJsonPath('conflitos.0.tipo', 'limite_diario');

        $this->assertSame(0, $guia->lancamentos()->count());
    }

    public function test_limite_diario_da_especialidade_aba_e_de_oito_sessoes(): void
    {
        $this->autenticar();
        $guia = $this->guiaAprovada('Terapia ABA', sessoesAutorizadas: 10);

        $sessoes = [];
        for ($i = 0; $i < 9; $i++) {
            $sessoes[] = [
                'data_sessao' => '2026-09-21',
                'hora_inicio' => sprintf('%02d:00', 7 + $i),
            ];
        }

        $this->postJson("/api/guias/{$guia->id}/lancamentos/importar-transcricao", [
            'profissional_id' => $guia->profissional_id,
            'confirmar_envio' => true,
            'sessoes' => $sessoes,
        ])->assertStatus(422)
            ->assertJsonPath('conflitos.0.tipo', 'limite_diario');

        // Oito passam.
        $this->postJson("/api/guias/{$guia->id}/lancamentos/importar-transcricao", [
            'profissional_id' => $guia->profissional_id,
            'confirmar_envio' => true,
            'sessoes' => array_slice($sessoes, 0, 8),
        ])->assertCreated();

        $this->assertSame(8, $guia->lancamentos()->count());
    }

    private function autenticar(): void
    {
        Sanctum::actingAs(User::query()->where('email', 'admin@clinica-exemplo.test')->firstOrFail());
    }

    private function gravarSessao(Guia $guia, string $data, string $hora): Lancamento
    {
        return Lancamento::query()->create([
            'tenant_id' => $guia->tenant_id,
            'guia_id' => $guia->id,
            'profissional_id' => $guia->profissional_id,
            'data_sessao' => $data,
            'hora_inicio' => $hora,
            'status' => 'completed',
        ]);
    }

    private function guiaAprovada(
        string $especialidadeNome,
        ?Paciente $paciente = null,
        int $sessoesAutorizadas = 10,
    ): Guia {
        $tenant = Tenant::query()->where('slug', 'clinica-exemplo')->firstOrFail();
        $convenio = Convenio::query()->where('nome', 'Unimed')->firstOrFail();
        $especialidade = Especialidade::query()->where('nome', $especialidadeNome)->firstOrFail();
        $profissional = Profissional::query()->where('especialidade_id', $especialidade->id)->firstOrFail();
        $paciente ??= Paciente::query()->where('convenio_id', $convenio->id)->firstOrFail();

        $guia = Guia::query()->create([
            'tenant_id' => $tenant->id,
            'convenio_id' => $convenio->id,
            'paciente_id' => $paciente->id,
            'profissional_id' => $profissional->id,
            'especialidade_id' => $especialidade->id,
            'numero_guia' => 'AGENDA-'.uniqid(),
            'tipo_terapia' => 'especializada',
            'status' => 'approved',
            'sessoes_autorizadas' => $sessoesAutorizadas,
            'data_solicitacao' => today(),
        ]);

        return $guia->load(['especialidade', 'paciente']);
    }
}
