<?php

namespace Tests\Unit;

use App\Models\Convenio;
use App\Services\ConvenioRegraService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConvenioRegraServiceTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_criar_nova_regra_encerra_a_vigente_do_mesmo_tipo(): void
    {
        $convenio = Convenio::query()->where('nome', 'Unimed')->firstOrFail();
        $service = app(ConvenioRegraService::class);

        $anterior = $service->criar($convenio, [
            'tipo_terapia' => 'especializada',
            'frequencia_lancamento' => 'semanal',
            'qtd_autorizada_por_ciclo' => 4,
            'validade_senha_dias' => 30,
            'observacoes' => null,
            'vigente_desde' => '2026-01-01',
        ]);

        $nova = $service->criar($convenio, [
            'tipo_terapia' => 'especializada',
            'frequencia_lancamento' => 'mensal',
            'qtd_autorizada_por_ciclo' => 12,
            'validade_senha_dias' => 60,
            'observacoes' => 'Nova vigência',
            'vigente_desde' => '2026-02-01',
        ]);

        $this->assertSame('2026-01-31', $anterior->fresh()->vigente_ate->toDateString());
        $this->assertNull($nova->vigente_ate);
        $this->assertSame('mensal', $nova->frequencia_lancamento);
    }
}
