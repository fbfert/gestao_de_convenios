<?php

namespace Tests\Unit;

use App\Exceptions\GuiaStatusInvalidoException;
use App\Models\Convenio;
use App\Models\Especialidade;
use App\Models\Guia;
use App\Models\Paciente;
use App\Models\Profissional;
use App\Models\Tenant;
use App\Services\GuiaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuiaServiceTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_finalizar_calcula_validade_e_ja_aceita_lancamento(): void
    {
        $service = app(GuiaService::class);
        $guia = $this->novaGuia('Unimed', 'Fisioterapia', 'especializada');

        $finalizada = $service->finalizar($guia, [
            'senha' => 'ABC123',
        ]);

        $this->assertSame('finalized', $finalizada->status);
        $this->assertSame('ABC123', $finalizada->senha);
        $this->assertTrue($finalizada->data_finalizacao->isSameDay(today()));
        $this->assertTrue($finalizada->validade_senha->isSameDay(today()->copy()->addDays(30)));
        // Desde 10/09/2026: Finalizar é só bookkeeping — lançar sessão não
        // depende mais dele, só do status (ver Guia::aceitaLancamento()).
        $this->assertTrue($finalizada->aceitaLancamento());
    }

    public function test_finalizar_aceita_guia_approved_usando_senha_ja_capturada_pela_automacao(): void
    {
        $service = app(GuiaService::class);
        $guia = $this->novaGuia('Unimed', 'Fisioterapia', 'especializada');
        // Arranjo do estado passa pelo ponto único de escrita como qualquer
        // outro código: a trava do model não abre exceção para teste.
        $guia->forceFill([
            'senha' => 'AUTO-999',
            'validade_senha' => today()->copy()->addDays(10),
        ]);
        $service->registrarTransicao($guia, 'approved', ['origem' => 'automacao']);

        // O ponto central do pedido de 10/09/2026: já aceita lançamento
        // assim que aprovada, mesmo antes de Finalizar rodar.
        $this->assertTrue($guia->fresh()->aceitaLancamento());

        $finalizada = $service->finalizar($guia, []);

        $this->assertSame('finalized', $finalizada->status);
        $this->assertSame('AUTO-999', $finalizada->senha);
        $this->assertTrue($finalizada->validade_senha->isSameDay(today()->copy()->addDays(10)));
    }

    public function test_finalizar_rejeita_guia_negada(): void
    {
        $service = app(GuiaService::class);
        $guia = $this->novaGuia('Unimed', 'Fisioterapia', 'especializada');
        $service->registrarTransicao($guia, 'denied');

        $this->expectException(GuiaStatusInvalidoException::class);

        $service->finalizar($guia, ['senha' => 'ABC123']);
    }

    public function test_finalizar_rejeita_sem_senha(): void
    {
        $service = app(GuiaService::class);
        $guia = $this->novaGuia('Unimed', 'Fisioterapia', 'especializada');

        $this->expectException(GuiaStatusInvalidoException::class);

        $service->finalizar($guia, []);
    }

    public function test_ocultar_alerta_negacao_preenche_timestamp_sem_mudar_status(): void
    {
        $service = app(GuiaService::class);
        $guia = $this->novaGuia('Unimed', 'Fisioterapia', 'especializada');
        $service->registrarTransicao($guia, 'denied');

        $this->assertNull($guia->alerta_negacao_ocultado_em);

        $ocultada = $service->ocultarAlertaNegacao($guia);

        $this->assertSame('denied', $ocultada->status);
        $this->assertNotNull($ocultada->alerta_negacao_ocultado_em);
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
