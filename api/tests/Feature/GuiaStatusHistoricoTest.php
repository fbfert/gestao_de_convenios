<?php

namespace Tests\Feature;

use App\Models\Convenio;
use App\Models\Especialidade;
use App\Models\Guia;
use App\Models\GuiaStatusHistorico;
use App\Models\Paciente;
use App\Models\Profissional;
use App\Models\User;
use App\Services\GuiaService;
use App\Support\GuiaStatus;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use RuntimeException;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class GuiaStatusHistoricoTest extends TestCase
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

    private function guia(string $status = GuiaStatus::UNDER_REVIEW): Guia
    {
        $tenantId = TenantContext::get();
        $convenio = Convenio::query()->where('nome', 'Unimed')->firstOrFail();
        $especialidade = Especialidade::query()->where('nome', 'Fisioterapia')->firstOrFail();
        $profissional = Profissional::query()->where('especialidade_id', $especialidade->id)->firstOrFail();
        $paciente = Paciente::query()->firstOrFail();

        return Guia::query()->create([
            'tenant_id' => $tenantId,
            'convenio_id' => $convenio->id,
            'paciente_id' => $paciente->id,
            'profissional_id' => $profissional->id,
            'especialidade_id' => $especialidade->id,
            'numero_guia' => 'HIST-'.uniqid(),
            'tipo_terapia' => 'especializada',
            'status' => $status,
            'data_solicitacao' => today()->toDateString(),
        ]);
    }

    public function test_criacao_registra_a_primeira_transicao(): void
    {
        $this->autenticar();
        $guia = $this->guia();

        $historico = GuiaStatusHistorico::query()->where('guia_id', $guia->id)->get();

        $this->assertCount(1, $historico);
        $this->assertNull($historico->first()->de);
        $this->assertSame(GuiaStatus::UNDER_REVIEW, $historico->first()->para);
    }

    public function test_transicao_grava_historico_e_carimbo(): void
    {
        $this->autenticar();
        $guia = $this->guia();

        app(GuiaService::class)->registrarTransicao($guia, GuiaStatus::DENIED, [
            'origem' => GuiaStatusHistorico::ORIGEM_MANUAL,
            'motivo' => 'Sem cobertura contratual',
        ]);

        $linha = GuiaStatusHistorico::query()
            ->where('guia_id', $guia->id)
            ->where('para', GuiaStatus::DENIED)
            ->firstOrFail();

        $this->assertSame(GuiaStatus::UNDER_REVIEW, $linha->de);
        $this->assertSame('Sem cobertura contratual', $linha->motivo);
        $this->assertNotNull($guia->fresh()->negada_em);
    }

    public function test_transicao_para_aprovada_carimba_a_data(): void
    {
        $this->autenticar();
        $guia = $this->guia();

        app(GuiaService::class)->registrarTransicao($guia, GuiaStatus::APPROVED);

        $this->assertNotNull($guia->fresh()->aprovada_em);
        $this->assertNull($guia->fresh()->negada_em);
    }

    public function test_transicao_de_robo_nao_tem_usuario_e_guarda_a_origem(): void
    {
        $this->autenticar();
        $guia = $this->guia();

        app(GuiaService::class)->registrarTransicao($guia, GuiaStatus::APPROVED, [
            'origem' => GuiaStatusHistorico::ORIGEM_AUTOMACAO,
        ]);

        $linha = GuiaStatusHistorico::query()
            ->where('guia_id', $guia->id)
            ->where('para', GuiaStatus::APPROVED)
            ->firstOrFail();

        $this->assertNull($linha->user_id);
        $this->assertSame(GuiaStatusHistorico::ORIGEM_AUTOMACAO, $linha->origem);
    }

    public function test_transicao_manual_registra_o_usuario(): void
    {
        $usuario = $this->autenticar();
        $guia = $this->guia();

        app(GuiaService::class)->registrarTransicao($guia, GuiaStatus::DENIED);

        $linha = GuiaStatusHistorico::query()
            ->where('guia_id', $guia->id)
            ->where('para', GuiaStatus::DENIED)
            ->firstOrFail();

        $this->assertSame($usuario->id, $linha->user_id);
    }

    public function test_segunda_negacao_acrescenta_linha_e_atualiza_o_carimbo(): void
    {
        $this->autenticar();
        $guia = $this->guia();
        $servico = app(GuiaService::class);

        $servico->registrarTransicao($guia, GuiaStatus::DENIED, ['ocorrido_em' => now()->subDays(3)]);
        $primeira = $guia->fresh()->negada_em;

        $servico->registrarTransicao($guia, GuiaStatus::UNDER_REVIEW);
        $servico->registrarTransicao($guia, GuiaStatus::DENIED);

        $negacoes = GuiaStatusHistorico::query()
            ->where('guia_id', $guia->id)
            ->where('para', GuiaStatus::DENIED)
            ->count();

        // O histórico é acumulativo; o carimbo é a última vez.
        $this->assertSame(2, $negacoes);
        $this->assertTrue($guia->fresh()->negada_em->greaterThan($primeira));
    }

    public function test_a_trava_reprova_escrita_de_status_por_fora(): void
    {
        $this->autenticar();
        $guia = $this->guia();

        // É este teste que deve FALHAR se alguém acrescentar um update de
        // status solto em qualquer ponto do código.
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/registrarTransicao/');

        $guia->status = GuiaStatus::APPROVED;
        $guia->save();
    }

    public function test_a_trava_tambem_reprova_force_fill(): void
    {
        $this->autenticar();
        $guia = $this->guia();

        // forceFill escapa do fillable, mas não do observer: a trava reprova
        // pelo efeito, e não pelo caminho que o código tomou.
        $this->expectException(RuntimeException::class);

        $guia->forceFill(['status' => GuiaStatus::CANCELED])->save();
    }

    public function test_salvar_outros_campos_continua_livre(): void
    {
        $this->autenticar();
        $guia = $this->guia();

        $guia->observacoes = 'Correção de digitação';
        $guia->save();

        $this->assertSame('Correção de digitação', $guia->fresh()->observacoes);
    }

    public function test_backfill_em_simulacao_nao_grava(): void
    {
        $this->autenticar();
        $guia = $this->guia();
        GuiaStatusHistorico::query()->where('guia_id', $guia->id)->delete();

        $antes = GuiaStatusHistorico::query()->count();

        $this->artisan('guias:backfill-status-historico --dry-run')->assertSuccessful();

        $this->assertSame($antes, GuiaStatusHistorico::query()->count());
    }

    public function test_backfill_marca_as_linhas_como_migracao(): void
    {
        $this->autenticar();
        $guia = $this->guia();
        GuiaStatusHistorico::query()->where('guia_id', $guia->id)->delete();

        $this->artisan('guias:backfill-status-historico')->assertSuccessful();

        $linhas = GuiaStatusHistorico::query()->where('guia_id', $guia->id)->get();

        $this->assertNotEmpty($linhas);
        $this->assertSame(
            [GuiaStatusHistorico::ORIGEM_MIGRACAO],
            $linhas->pluck('origem')->unique()->values()->all(),
        );
    }
}
