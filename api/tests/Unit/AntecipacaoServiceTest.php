<?php

namespace Tests\Unit;

use App\Exceptions\AntecipacaoCotaEsgotadaException;
use App\Models\Antecipacao;
use App\Models\Convenio;
use App\Models\Especialidade;
use App\Models\Guia;
use App\Models\Paciente;
use App\Models\Profissional;
use App\Models\Tenant;
use App\Services\AntecipacaoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AntecipacaoServiceTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_abrir_ciclo_diario_usa_regra_e_periodo_corretos(): void
    {
        $service = app(AntecipacaoService::class);
        $guia = $this->novaGuia('Unimed', 'Fisioterapia', 'especializada');

        $antecipacao = $service->abrirCiclo($guia);

        $this->assertSame(1, $antecipacao->qtd_autorizada);
        $this->assertSame(0, $antecipacao->qtd_utilizada);
        $this->assertSame('open', $antecipacao->status);
        $this->assertTrue($antecipacao->ciclo_inicio->isSameDay(today()));
        $this->assertTrue($antecipacao->ciclo_fim->isSameDay(today()));
    }

    public function test_abrir_ciclo_semanal_calcula_sete_dias(): void
    {
        $service = app(AntecipacaoService::class);
        $guia = $this->novaGuia('SC Saúde', 'Fonoaudiologia', 'especializada');

        $antecipacao = $service->abrirCiclo($guia);

        $this->assertSame(1, $antecipacao->qtd_autorizada);
        $this->assertTrue($antecipacao->ciclo_inicio->isSameDay(today()));
        $this->assertTrue($antecipacao->ciclo_fim->isSameDay(today()->copy()->addDays(6)));
    }

    public function test_abrir_ciclo_mensal_calcula_periodo_correto(): void
    {
        $service = app(AntecipacaoService::class);
        $guia = $this->novaGuia('SC Saúde', 'Fonoaudiologia', 'convencional');

        $antecipacao = $service->abrirCiclo($guia);

        $this->assertSame(4, $antecipacao->qtd_autorizada);
        $this->assertTrue($antecipacao->ciclo_inicio->isSameDay(today()));
        $this->assertTrue($antecipacao->ciclo_fim->isSameDay(today()->copy()->addMonthNoOverflow()->subDay()));
    }

    public function test_abrir_ciclo_usa_sessoes_autorizadas_da_guia_quando_definido(): void
    {
        $service = app(AntecipacaoService::class);
        $guia = $this->novaGuia('Unimed', 'Fisioterapia', 'especializada');
        $guia->forceFill([
            'sessoes_autorizadas' => 10,
            'validade_senha' => today()->addDays(30),
        ])->save();

        $antecipacao = $service->abrirCiclo($guia);

        $this->assertSame(10, $antecipacao->qtd_autorizada);
        $this->assertTrue($antecipacao->ciclo_fim->isSameDay(today()->copy()->addDays(30)));
    }

    public function test_consumir_cota_incrementa_e_fecha_quando_atinge_limite(): void
    {
        $service = app(AntecipacaoService::class);
        $guia = $this->novaGuia('Unimed', 'Fisioterapia', 'especializada');

        $antecipacao = $service->abrirCiclo($guia);
        $service->consumirCota($antecipacao);

        $antecipacao->refresh();

        $this->assertSame(1, $antecipacao->qtd_utilizada);
        $this->assertSame('closed', $antecipacao->status);

        $this->expectException(AntecipacaoCotaEsgotadaException::class);

        $service->consumirCota($antecipacao);
    }

    private function novaGuia(string $convenioNome, string $especialidadeNome, string $tipoTerapia): Guia
    {
        $tenant = Tenant::query()->where('slug', 'clinica-exemplo')->firstOrFail();
        $convenio = Convenio::query()->where('nome', $convenioNome)->firstOrFail();
        $especialidade = Especialidade::query()->where('nome', $especialidadeNome)->firstOrFail();
        $profissional = Profissional::query()->where('especialidade_id', $especialidade->id)->firstOrFail();
        $paciente = Paciente::query()->where('convenio_id', $convenio->id)->firstOrFail();

        return Guia::query()->create([
            'tenant_id' => $tenant->id,
            'solicitacao_id' => null,
            'convenio_id' => $convenio->id,
            'paciente_id' => $paciente->id,
            'profissional_id' => $profissional->id,
            'especialidade_id' => $especialidade->id,
            'numero_guia' => 'GUIA-'.uniqid(),
            'tipo_terapia' => $tipoTerapia,
            'status' => 'under_review',
            'data_solicitacao' => today(),
            'data_finalizacao' => null,
            'senha' => null,
            'validade_senha' => null,
            'observacoes' => null,
        ]);
    }
}
