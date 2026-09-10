<?php

namespace Tests\Unit;

use App\Models\Convenio;
use App\Models\Especialidade;
use App\Models\Guia;
use App\Models\Lancamento;
use App\Models\Paciente;
use App\Models\Profissional;
use App\Models\Tenant;
use App\Services\LancamentoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class LancamentoServiceTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_registrar_cria_lancamento_completado_direto_na_guia(): void
    {
        $service = app(LancamentoService::class);

        $guia = $this->novaGuia('Unimed', 'Fisioterapia', 'especializada', sessoesAutorizadas: 10);
        $profissional = Profissional::query()->where('nome', 'Dra. Marina Tavares')->firstOrFail();

        $lancamento = $service->registrar($guia, $profissional, today());

        $this->assertInstanceOf(Lancamento::class, $lancamento);
        $this->assertSame('completed', $lancamento->status);
        $this->assertSame($guia->id, $lancamento->guia_id);
        $this->assertSame($profissional->id, $lancamento->profissional_id);
        $this->assertSame(9, $guia->fresh()->sessoesDisponiveis());
    }

    public function test_registrar_recusa_quando_a_guia_nao_esta_aprovada(): void
    {
        $service = app(LancamentoService::class);

        $guia = $this->novaGuia('Unimed', 'Fisioterapia', 'especializada', sessoesAutorizadas: 10, status: 'under_review');
        $profissional = Profissional::query()->where('nome', 'Dra. Marina Tavares')->firstOrFail();

        $this->expectException(ValidationException::class);

        try {
            $service->registrar($guia, $profissional, today());
        } finally {
            $this->assertSame(0, Lancamento::query()->where('guia_id', $guia->id)->count());
        }
    }

    public function test_registrar_recusa_quando_a_cota_ja_esta_esgotada(): void
    {
        $service = app(LancamentoService::class);

        $guia = $this->novaGuia('Unimed', 'Fisioterapia', 'especializada', sessoesAutorizadas: 1);
        $profissional = Profissional::query()->where('nome', 'Dra. Marina Tavares')->firstOrFail();

        $service->registrar($guia, $profissional, today());

        $this->expectException(ValidationException::class);

        try {
            $service->registrar($guia, $profissional, today());
        } finally {
            $this->assertSame(1, Lancamento::query()->where('guia_id', $guia->id)->count());
        }
    }

    public function test_remover_libera_a_cota_de_novo_sem_balde_separado(): void
    {
        $service = app(LancamentoService::class);

        $guia = $this->novaGuia('Unimed', 'Fisioterapia', 'especializada', sessoesAutorizadas: 1);
        $profissional = Profissional::query()->where('nome', 'Dra. Marina Tavares')->firstOrFail();

        $lancamento = $service->registrar($guia, $profissional, today());
        $this->assertSame(0, $guia->fresh()->sessoesDisponiveis());

        $service->remover($lancamento);

        $this->assertSame(1, $guia->fresh()->sessoesDisponiveis());
    }

    private function novaGuia(
        string $convenioNome,
        string $especialidadeNome,
        string $tipoTerapia,
        ?int $sessoesAutorizadas = null,
        string $status = 'approved',
    ): Guia {
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
            'status' => $status,
            'sessoes_autorizadas' => $sessoesAutorizadas,
            'data_solicitacao' => today(),
            'data_finalizacao' => null,
            'senha' => null,
            'validade_senha' => null,
            'observacoes' => null,
        ]);
    }
}
