<?php

namespace Tests\Feature;

use App\Models\Especialidade;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Relatorios\RelatorioAba;
use App\Services\Relatorios\RelatorioFiltros;
use App\Services\Relatorios\RelatorioPeriodo;
use App\Services\Relatorios\RelatorioService;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Bloco 1 da change `relatorios-de-uso`: permissão por aba, validação do
 * período, escolha de clínica e cache.
 *
 * Os serviços ainda devolvem o contrato vazio — o cálculo é o bloco 2. O que
 * está sob teste aqui é o que protege esse cálculo: quem pode pedir, o que pode
 * pedir, e de qual clínica sai o número.
 */
class RelatoriosApiTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private const DE = '2026-09-01';

    private const ATE = '2026-09-30';

    // ── Permissão por aba ───────────────────────────────────────────────────

    public function test_admin_abre_as_quatro_abas(): void
    {
        $this->autenticar('admin@clinica-exemplo.test');

        foreach (RelatorioAba::TODAS as $aba) {
            $this->getJson($this->url($aba))->assertOk();
        }
    }

    public function test_sem_a_permissao_da_aba_o_acesso_e_negado(): void
    {
        $this->autenticar('profissional@clinica-exemplo.test');

        foreach (RelatorioAba::TODAS as $aba) {
            $this->getJson($this->url($aba))->assertForbidden();
        }
    }

    /**
     * A permissão é POR ABA, e não uma só para a tela: o funcionário vê
     * operação e automações e continua fora do financeiro. É esse recorte que
     * justifica quatro permissões em vez de uma.
     */
    public function test_permissao_de_uma_aba_nao_abre_as_outras(): void
    {
        $this->autenticar('funcionario@clinica-exemplo.test');

        $this->getJson($this->url(RelatorioAba::OPERACAO))->assertOk();
        $this->getJson($this->url(RelatorioAba::AUTOMACOES))->assertOk();
        $this->getJson($this->url(RelatorioAba::FINANCEIRO))->assertForbidden();
        $this->getJson($this->url(RelatorioAba::USO))->assertForbidden();
    }

    public function test_exportacao_exige_a_mesma_permissao_da_aba(): void
    {
        $this->autenticar('funcionario@clinica-exemplo.test');

        $this->getJson($this->url(RelatorioAba::FINANCEIRO, ['tabela' => 'por_convenio', 'formato' => 'csv']).'&x=1')
            ->assertForbidden();
    }

    // ── Contrato da resposta ────────────────────────────────────────────────

    public function test_a_resposta_traz_o_contrato_completo(): void
    {
        $this->autenticar('admin@clinica-exemplo.test');

        $resposta = $this->getJson($this->url(RelatorioAba::OPERACAO))->assertOk();

        $resposta->assertJsonStructure([
            'data' => [
                'periodo' => ['de', 'ate', 'granularidade', 'dias'],
                'comparacao',
                'filtros_aplicados',
                'kpis',
                'series',
                'tabelas',
                'gerado_em',
                'cache',
            ],
        ]);

        $resposta->assertJsonPath('data.periodo.de', self::DE);
        $resposta->assertJsonPath('data.periodo.ate', self::ATE);
        $resposta->assertJsonPath('data.periodo.dias', 30);
        // 30 dias: série diária.
        $resposta->assertJsonPath('data.periodo.granularidade', RelatorioPeriodo::DIA);
        $resposta->assertJsonPath('data.comparacao', null);
    }

    public function test_comparacao_devolve_o_periodo_anterior(): void
    {
        $this->autenticar('admin@clinica-exemplo.test');

        $this->getJson($this->url(RelatorioAba::OPERACAO, ['comparar' => 1]))
            ->assertOk()
            ->assertJsonPath('data.comparacao.de', '2026-08-02')
            ->assertJsonPath('data.comparacao.ate', '2026-08-31');
    }

    public function test_granularidade_automatica_pelo_tamanho_do_periodo(): void
    {
        $this->autenticar('admin@clinica-exemplo.test');

        $this->getJson($this->url(RelatorioAba::OPERACAO, ['de' => '2026-01-01', 'ate' => '2026-03-31']))
            ->assertOk()
            ->assertJsonPath('data.periodo.granularidade', RelatorioPeriodo::SEMANA);

        $this->getJson($this->url(RelatorioAba::OPERACAO, ['de' => '2026-01-01', 'ate' => '2026-08-31']))
            ->assertOk()
            ->assertJsonPath('data.periodo.granularidade', RelatorioPeriodo::MES);
    }

    public function test_granularidade_informada_prevalece(): void
    {
        $this->autenticar('admin@clinica-exemplo.test');

        $this->getJson($this->url(RelatorioAba::OPERACAO, ['granularidade' => RelatorioPeriodo::MES]))
            ->assertOk()
            ->assertJsonPath('data.periodo.granularidade', RelatorioPeriodo::MES);
    }

    // ── Validação do período e dos filtros ──────────────────────────────────

    public function test_periodo_e_obrigatorio(): void
    {
        $this->autenticar('admin@clinica-exemplo.test');

        $this->getJson('/api/relatorios/'.RelatorioAba::OPERACAO)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['de', 'ate']);
    }

    public function test_periodo_invertido_e_recusado(): void
    {
        $this->autenticar('admin@clinica-exemplo.test');

        $this->getJson($this->url(RelatorioAba::OPERACAO, ['de' => '2026-09-30', 'ate' => '2026-09-01']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['ate']);
    }

    public function test_periodo_acima_do_teto_e_recusado(): void
    {
        $this->autenticar('admin@clinica-exemplo.test');

        $this->getJson($this->url(RelatorioAba::OPERACAO, ['de' => '2025-09-18', 'ate' => '2026-09-19']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['ate']);
    }

    public function test_exatamente_o_teto_passa(): void
    {
        $this->autenticar('admin@clinica-exemplo.test');

        $this->getJson($this->url(RelatorioAba::OPERACAO, ['de' => '2025-09-19', 'ate' => '2026-09-19']))
            ->assertOk()
            ->assertJsonPath('data.periodo.dias', RelatorioPeriodo::MAX_DIAS);
    }

    public function test_data_em_formato_invalido_e_recusada(): void
    {
        $this->autenticar('admin@clinica-exemplo.test');

        $this->getJson($this->url(RelatorioAba::OPERACAO, ['de' => '01/09/2026']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['de']);
    }

    public function test_granularidade_desconhecida_e_recusada(): void
    {
        $this->autenticar('admin@clinica-exemplo.test');

        $this->getJson($this->url(RelatorioAba::OPERACAO, ['granularidade' => 'trimestre']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['granularidade']);
    }

    /**
     * O 422 não pode virar oráculo: id de convênio de OUTRA clínica tem que ser
     * recusado igual a um id que não existe em lugar nenhum.
     */
    public function test_filtro_com_id_de_outra_clinica_e_recusado(): void
    {
        $vizinha = Tenant::factory()->create();
        $especialidadeVizinha = Especialidade::query()->create([
            'tenant_id' => $vizinha->id,
            'nome' => 'Fisioterapia da vizinha',
            'ativo' => true,
        ]);

        $this->autenticar('admin@clinica-exemplo.test');

        $this->getJson($this->url(RelatorioAba::OPERACAO, ['especialidade_id' => $especialidadeVizinha->id]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['especialidade_id']);
    }

    public function test_filtro_da_propria_clinica_e_aceito(): void
    {
        $this->autenticar('admin@clinica-exemplo.test');

        $especialidade = Especialidade::query()->firstOrFail();

        $this->getJson($this->url(RelatorioAba::OPERACAO, ['especialidade_id' => $especialidade->id]))
            ->assertOk();
    }

    // ── Escolha de clínica ──────────────────────────────────────────────────

    public function test_usuario_comum_que_envia_clinica_recebe_403(): void
    {
        $vizinha = Tenant::factory()->create();

        $this->autenticar('admin@clinica-exemplo.test');

        $this->getJson($this->url(RelatorioAba::OPERACAO, ['tenant_id' => $vizinha->id]))
            ->assertForbidden();

        $this->getJson($this->url(RelatorioAba::OPERACAO, ['tenant_id' => 'todos']))
            ->assertForbidden();
    }

    /** Nem com a própria clínica: o parâmetro não é dele, e 403 diz isso. */
    public function test_usuario_comum_e_barrado_mesmo_apontando_para_a_propria_clinica(): void
    {
        $usuario = $this->autenticar('admin@clinica-exemplo.test');

        $this->getJson($this->url(RelatorioAba::OPERACAO, ['tenant_id' => $usuario->tenant_id]))
            ->assertForbidden();
    }

    public function test_super_admin_escolhe_uma_clinica(): void
    {
        $vizinha = Tenant::factory()->create();

        $this->autenticarSuperAdmin();

        $this->getJson($this->url(RelatorioAba::OPERACAO, ['tenant_id' => $vizinha->id]))->assertOk();
    }

    public function test_super_admin_pode_pedir_todas_as_clinicas(): void
    {
        $this->autenticarSuperAdmin();

        $this->getJson($this->url(RelatorioAba::OPERACAO, ['tenant_id' => 'todos']))->assertOk();
    }

    public function test_clinica_inexistente_e_recusada_mesmo_para_super_admin(): void
    {
        $this->autenticarSuperAdmin();

        $this->getJson($this->url(RelatorioAba::OPERACAO, ['tenant_id' => 999999]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['tenant_id']);
    }

    // ── Recorte de clínica dentro do serviço ────────────────────────────────

    /**
     * O `naClinica()` é o único ponto do sistema que derruba o escopo global de
     * propósito. Estes três casos são a razão de ele existir num lugar só.
     */
    public function test_o_servico_conta_apenas_a_clinica_do_filtro(): void
    {
        [$daCasa, $daVizinha, $vizinha] = $this->duasClinicasComEspecialidades();

        $servico = $this->servicoQueContaEspecialidades();

        $this->assertSame(
            $daCasa,
            $this->valorDoKpi($servico, $this->filtrosPara($this->tenantDaCasa())),
            'clínica do usuário',
        );

        $this->assertSame(
            $daVizinha,
            $this->valorDoKpi($servico, $this->filtrosPara($vizinha->id)),
            'clínica escolhida pelo super admin',
        );

        $this->assertSame(
            $daCasa + $daVizinha,
            $this->valorDoKpi($servico, $this->filtrosPara(null)),
            'todas as clínicas',
        );
    }

    /**
     * E o escopo global continua valendo fora do serviço: derrubá-lo aqui não
     * pode ter afrouxado nada para o resto do sistema.
     */
    public function test_o_escopo_global_continua_valendo_fora_do_relatorio(): void
    {
        [$daCasa] = $this->duasClinicasComEspecialidades();

        $this->assertSame($daCasa, Especialidade::query()->count());
    }

    // ── Cache ───────────────────────────────────────────────────────────────

    public function test_a_segunda_consulta_igual_vem_do_cache(): void
    {
        $this->autenticar('admin@clinica-exemplo.test');

        $this->getJson($this->url(RelatorioAba::OPERACAO))
            ->assertOk()
            ->assertJsonPath('data.cache', false);

        $this->getJson($this->url(RelatorioAba::OPERACAO))
            ->assertOk()
            ->assertJsonPath('data.cache', true);
    }

    public function test_filtro_diferente_nao_reaproveita(): void
    {
        $this->autenticar('admin@clinica-exemplo.test');

        $this->getJson($this->url(RelatorioAba::OPERACAO))->assertOk();

        $this->getJson($this->url(RelatorioAba::OPERACAO, ['comparar' => 1]))
            ->assertOk()
            ->assertJsonPath('data.cache', false);
    }

    public function test_abas_diferentes_nao_compartilham_cache(): void
    {
        $this->autenticar('admin@clinica-exemplo.test');

        $this->getJson($this->url(RelatorioAba::OPERACAO))->assertOk();

        $this->getJson($this->url(RelatorioAba::FINANCEIRO))
            ->assertOk()
            ->assertJsonPath('data.cache', false);
    }

    public function test_clinicas_diferentes_nao_compartilham_cache(): void
    {
        $vizinha = Tenant::factory()->create();

        $this->autenticarSuperAdmin();

        $this->getJson($this->url(RelatorioAba::OPERACAO))->assertOk();

        $this->getJson($this->url(RelatorioAba::OPERACAO, ['tenant_id' => $vizinha->id]))
            ->assertOk()
            ->assertJsonPath('data.cache', false);
    }

    // ── Apoio ───────────────────────────────────────────────────────────────

    /** @param array<string, mixed> $extra */
    private function url(string $aba, array $extra = []): string
    {
        return '/api/relatorios/'.$aba.'?'.http_build_query([
            'de' => self::DE,
            'ate' => self::ATE,
            ...$extra,
        ]);
    }

    private function autenticar(string $email): User
    {
        $usuario = User::query()->where('email', $email)->firstOrFail();

        Sanctum::actingAs($usuario);
        TenantContext::set((int) $usuario->tenant_id);
        app(PermissionRegistrar::class)->setPermissionsTeamId((int) $usuario->tenant_id);

        return $usuario;
    }

    /**
     * Super admin com papel `admin` na própria clínica: a flag libera a escolha
     * de clínica, e o papel entrega as quatro permissões de relatório.
     */
    private function autenticarSuperAdmin(): User
    {
        $usuario = $this->autenticar('admin@clinica-exemplo.test');
        $usuario->forceFill(['super_admin' => true])->save();

        return $usuario;
    }

    private function tenantDaCasa(): int
    {
        return (int) Tenant::query()->where('slug', 'clinica-exemplo')->firstOrFail()->id;
    }

    /** @return array{0: int, 1: int, 2: Tenant} quantas especialidades tem cada clínica */
    private function duasClinicasComEspecialidades(): array
    {
        $vizinha = Tenant::factory()->create();

        foreach (['Fonoaudiologia da vizinha', 'Psicologia da vizinha'] as $nome) {
            Especialidade::query()->create([
                'tenant_id' => $vizinha->id,
                'nome' => $nome,
                'ativo' => true,
            ]);
        }

        TenantContext::set($this->tenantDaCasa());

        return [Especialidade::query()->count(), 2, $vizinha];
    }

    private function filtrosPara(?int $tenantId): RelatorioFiltros
    {
        return new RelatorioFiltros(
            periodo: RelatorioPeriodo::entre(self::DE, self::ATE),
            tenantId: $tenantId,
        );
    }

    /** Um serviço mínimo só para exercitar o recorte de clínica da base. */
    private function servicoQueContaEspecialidades(): RelatorioService
    {
        return new class extends RelatorioService
        {
            public function aba(): string
            {
                return RelatorioAba::OPERACAO;
            }

            protected function kpisDeclarados(): array
            {
                return ['especialidades' => ['label' => 'Especialidades', 'formato' => self::FORMATO_INTEIRO]];
            }

            protected function metricas(RelatorioFiltros $filtros, bool $ehComparacao = false): array
            {
                return ['especialidades' => $this->naClinica(Especialidade::query(), $filtros)->count()];
            }

            protected function series(RelatorioFiltros $filtros): array
            {
                return [];
            }

            protected function tabelas(RelatorioFiltros $filtros): array
            {
                return [];
            }

            protected function filtrosAplicados(RelatorioFiltros $filtros): array
            {
                return [];
            }
        };
    }

    private function valorDoKpi(RelatorioService $servico, RelatorioFiltros $filtros): int
    {
        return (int) $servico->montar($filtros)['kpis'][0]['valor'];
    }
}
