<?php

namespace Tests\Unit;

use App\Models\Convenio;
use App\Models\Guia;
use App\Models\Paciente;
use App\Models\Profissional;
use App\Models\Especialidade;
use App\Models\Tenant;
use App\Services\Connectors\ConnectorResolver;
use App\Services\Connectors\ManualConnector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConnectorResolverTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_resolve_manual_connector_and_checar_retorna_pending_manual(): void
    {
        $resolver = app(ConnectorResolver::class);
        $convenio = Convenio::query()->where('nome', 'Unimed')->firstOrFail();
        $connector = $resolver->resolver($convenio);

        $this->assertInstanceOf(ManualConnector::class, $connector);

        $guia = $this->novaGuia($convenio->nome);
        $resultado = $connector->checar($guia);

        $this->assertSame('pending_manual', $resultado['status']);
        $this->assertSame($guia->id, $resultado['detalhes']['guia_id']);
        $this->assertSame($convenio->id, $resultado['detalhes']['convenio_id']);
    }

    private function novaGuia(string $convenioNome): Guia
    {
        $tenant = Tenant::query()->where('slug', 'clinica-exemplo')->firstOrFail();
        $convenio = Convenio::query()->where('nome', $convenioNome)->firstOrFail();
        $especialidade = Especialidade::query()->where('nome', 'Fisioterapia')->firstOrFail();
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
            'tipo_terapia' => 'especializada',
            'status' => 'under_review',
            'data_solicitacao' => today(),
            'data_finalizacao' => null,
            'senha' => null,
            'validade_senha' => null,
            'observacoes' => null,
        ]);
    }
}
