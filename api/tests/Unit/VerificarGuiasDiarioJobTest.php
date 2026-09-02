<?php

namespace Tests\Unit;

use App\Jobs\VerificarGuiasDiarioJob;
use App\Models\ConectorExecucao;
use App\Models\Convenio;
use App\Models\Especialidade;
use App\Models\Guia;
use App\Models\Paciente;
use App\Models\Profissional;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VerificarGuiasDiarioJobTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_job_grava_execucao_para_guias_em_under_review_por_convenio(): void
    {
        $this->criarGuiasUnderReview();

        app(VerificarGuiasDiarioJob::class)->handle(app(\App\Services\Connectors\ConnectorResolver::class));

        $this->assertSame(3, ConectorExecucao::query()->count());
        $this->assertSame(3, ConectorExecucao::query()->where('status', 'pending_manual')->count());
    }

    public function test_job_legado_ignora_guias_unimed_rda(): void
    {
        $this->criarGuiasUnderReview();
        Convenio::query()->where('nome', 'Unimed')->update(['connector_driver' => 'unimed_rda']);

        app(VerificarGuiasDiarioJob::class)->handle(app(\App\Services\Connectors\ConnectorResolver::class));

        $this->assertSame(2, ConectorExecucao::query()->count());
        $this->assertDatabaseMissing('conector_execucoes', [
            'convenio_id' => Convenio::query()->where('nome', 'Unimed')->firstOrFail()->id,
        ]);
    }

    public function test_nao_roda_quando_automacao_esta_desligada_para_o_tenant(): void
    {
        $this->criarGuiasUnderReview();
        $tenant = Tenant::query()->where('slug', 'clinica-exemplo')->firstOrFail();
        \App\Models\ConfiguracaoGlobal::doTenant($tenant->id)->update([
            'automacao_verificacao_guias_diaria_ativo' => false,
        ]);

        app(VerificarGuiasDiarioJob::class)->handle(app(\App\Services\Connectors\ConnectorResolver::class));

        $this->assertSame(0, ConectorExecucao::query()->count());
    }

    private function criarGuiasUnderReview(): void
    {
        Guia::query()->delete();
        $tenant = Tenant::query()->where('slug', 'clinica-exemplo')->firstOrFail();
        $especialidade = Especialidade::query()->where('nome', 'Fisioterapia')->firstOrFail();
        $profissional = Profissional::query()->where('especialidade_id', $especialidade->id)->firstOrFail();

        foreach (['Unimed', 'SC Saúde', 'Celos'] as $nomeConvenio) {
            $convenio = Convenio::query()->where('nome', $nomeConvenio)->firstOrFail();
            $paciente = Paciente::query()->where('convenio_id', $convenio->id)->firstOrFail();

            Guia::query()->create([
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
}
