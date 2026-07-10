<?php

namespace Tests\Unit;

use App\Exceptions\GuiaStatusInvalidoException;
use App\Models\Antecipacao;
use App\Models\Convenio;
use App\Models\Especialidade;
use App\Models\Guia;
use App\Models\Paciente;
use App\Models\Profissional;
use App\Models\Tenant;
use App\Services\AntecipacaoService;
use App\Services\GuiaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class GuiaServiceTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_finalizar_calcula_validade_e_abre_antecipacao(): void
    {
        $antecipacaoService = Mockery::mock(AntecipacaoService::class);
        $antecipacaoService->shouldReceive('abrirCiclo')->once()->andReturnUsing(function (Guia $guia) {
            return Antecipacao::query()->create([
                'tenant_id' => $guia->tenant_id,
                'guia_id' => $guia->id,
                'paciente_id' => $guia->paciente_id,
                'convenio_id' => $guia->convenio_id,
                'ciclo_inicio' => today(),
                'ciclo_fim' => today(),
                'qtd_autorizada' => 1,
                'qtd_utilizada' => 0,
                'status' => 'open',
            ]);
        });
        $this->app->instance(AntecipacaoService::class, $antecipacaoService);

        $service = app(GuiaService::class);
        $guia = $this->novaGuia('Unimed', 'Fisioterapia', 'especializada');

        $finalizada = $service->finalizar($guia, [
            'senha' => 'ABC123',
        ]);

        $this->assertSame('finalized', $finalizada->status);
        $this->assertSame('ABC123', $finalizada->senha);
        $this->assertTrue($finalizada->data_finalizacao->isSameDay(today()));
        $this->assertTrue($finalizada->validade_senha->isSameDay(today()->copy()->addDays(30)));
    }

    public function test_finalizar_rejeita_sem_senha(): void
    {
        $service = app(GuiaService::class);
        $guia = $this->novaGuia('Unimed', 'Fisioterapia', 'especializada');

        $this->expectException(GuiaStatusInvalidoException::class);

        $service->finalizar($guia, []);
    }

    public function test_denegar_muda_status_para_denied(): void
    {
        $service = app(GuiaService::class);
        $guia = $this->novaGuia('Unimed', 'Fisioterapia', 'especializada');

        $denied = $service->negar($guia, 'documentação incompleta');

        $this->assertSame('denied', $denied->status);
        $this->assertSame('documentação incompleta', $denied->observacoes);
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
