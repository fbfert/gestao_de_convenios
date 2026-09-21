<?php

namespace Tests\Unit;

use App\Models\Convenio;
use App\Models\Especialidade;
use App\Models\Guia;
use App\Models\Lancamento;
use App\Models\Paciente;
use App\Models\Profissional;
use App\Models\Tenant;
use App\Services\Sessoes\AvaliadorDeAgenda;
use App\Services\Sessoes\ConflitoDeAgenda;
use App\Services\Sessoes\SessaoCandidata;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AvaliadorDeAgendaTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_aceita_sessoes_com_o_intervalo_minimo(): void
    {
        $guia = $this->novaGuia('Terapia ABA');

        $resultado = app(AvaliadorDeAgenda::class)->avaliar([
            $this->candidata($guia, '1', '2026-09-21', '08:00'),
            $this->candidata($guia, '2', '2026-09-21', '08:50'),
        ]);

        $this->assertFalse($resultado->temConflito());
    }

    public function test_recusa_sessoes_coladas_dentro_do_mesmo_lote(): void
    {
        $guia = $this->novaGuia('Terapia ABA');

        $resultado = app(AvaliadorDeAgenda::class)->avaliar([
            $this->candidata($guia, '1', '2026-09-21', '08:00'),
            $this->candidata($guia, '2', '2026-09-21', '08:30'),
        ]);

        $this->assertTrue($resultado->temConflito());
        $this->assertCount(1, $resultado->conflitos);
        $this->assertSame(ConflitoDeAgenda::TIPO_INTERVALO, $resultado->conflitos[0]->tipo);
        $this->assertStringContainsString('30 minutos', $resultado->conflitos[0]->mensagem);
    }

    public function test_recusa_duas_sessoes_no_mesmo_horario(): void
    {
        $guia = $this->novaGuia('Terapia ABA');

        $resultado = app(AvaliadorDeAgenda::class)->avaliar([
            $this->candidata($guia, '1', '2026-09-21', '08:00'),
            $this->candidata($guia, '2', '2026-09-21', '08:00'),
        ]);

        $this->assertSame(ConflitoDeAgenda::TIPO_MESMO_HORARIO, $resultado->conflitos[0]->tipo);
    }

    public function test_recusa_mesmo_horario_em_especialidades_diferentes(): void
    {
        $guiaAba = $this->novaGuia('Terapia ABA');
        $guiaFono = $this->novaGuia('Fonoaudiologia', paciente: $guiaAba->paciente);

        $resultado = app(AvaliadorDeAgenda::class)->avaliar([
            $this->candidata($guiaAba, '1', '2026-09-21', '08:00'),
            $this->candidata($guiaFono, '2', '2026-09-21', '08:00'),
        ]);

        $this->assertSame(ConflitoDeAgenda::TIPO_MESMO_HORARIO, $resultado->conflitos[0]->tipo);
    }

    public function test_aceita_especialidades_diferentes_no_mesmo_dia_em_horarios_distintos(): void
    {
        $guiaAba = $this->novaGuia('Terapia ABA');
        $guiaFono = $this->novaGuia('Fonoaudiologia', paciente: $guiaAba->paciente);

        $resultado = app(AvaliadorDeAgenda::class)->avaliar([
            $this->candidata($guiaAba, '1', '2026-09-21', '08:00'),
            $this->candidata($guiaFono, '2', '2026-09-21', '09:00'),
        ]);

        $this->assertFalse($resultado->temConflito());
    }

    public function test_aceita_oito_sessoes_aba_no_dia(): void
    {
        $guia = $this->novaGuia('Terapia ABA');

        $candidatas = [];
        for ($i = 0; $i < 8; $i++) {
            $candidatas[] = $this->candidata(
                $guia,
                (string) ($i + 1),
                '2026-09-21',
                CarbonImmutable::parse('08:00')->addMinutes(60 * $i)->format('H:i'),
            );
        }

        $resultado = app(AvaliadorDeAgenda::class)->avaliar($candidatas);

        $this->assertFalse($resultado->temConflito());
    }

    public function test_recusa_a_nona_sessao_aba_no_dia(): void
    {
        $guia = $this->novaGuia('Terapia ABA');

        $candidatas = [];
        for ($i = 0; $i < 9; $i++) {
            $candidatas[] = $this->candidata(
                $guia,
                (string) ($i + 1),
                '2026-09-21',
                CarbonImmutable::parse('07:00')->addMinutes(55 * $i)->format('H:i'),
            );
        }

        $resultado = app(AvaliadorDeAgenda::class)->avaliar($candidatas);

        $limites = array_values(array_filter(
            $resultado->conflitos,
            fn (ConflitoDeAgenda $c) => $c->tipo === ConflitoDeAgenda::TIPO_LIMITE_DIARIO,
        ));

        $this->assertCount(1, $limites);
        $this->assertSame('9', $limites[0]->referencia);
        $this->assertStringContainsString('limite é de 8', $limites[0]->mensagem);
    }

    public function test_recusa_a_segunda_sessao_convencional_no_dia(): void
    {
        $guia = $this->novaGuia('Fonoaudiologia');

        $resultado = app(AvaliadorDeAgenda::class)->avaliar([
            $this->candidata($guia, '1', '2026-09-21', '08:00'),
            $this->candidata($guia, '2', '2026-09-21', '14:00'),
        ]);

        $limites = array_values(array_filter(
            $resultado->conflitos,
            fn (ConflitoDeAgenda $c) => $c->tipo === ConflitoDeAgenda::TIPO_LIMITE_DIARIO,
        ));

        $this->assertCount(1, $limites);
        $this->assertSame('2', $limites[0]->referencia);
        $this->assertStringContainsString('limite é de 1 sessão', $limites[0]->mensagem);
    }

    public function test_ignora_sessao_sem_hora_de_inicio_no_intervalo(): void
    {
        $guia = $this->novaGuia('Terapia ABA');

        $resultado = app(AvaliadorDeAgenda::class)->avaliar([
            $this->candidata($guia, '1', '2026-09-21', '08:00'),
            $this->candidata($guia, '2', '2026-09-21', null),
        ]);

        $intervalos = array_filter(
            $resultado->conflitos,
            fn (ConflitoDeAgenda $c) => $c->tipo !== ConflitoDeAgenda::TIPO_LIMITE_DIARIO,
        );

        $this->assertSame([], array_values($intervalos));
    }

    public function test_detecta_conflito_com_sessao_de_outra_guia_do_mesmo_paciente(): void
    {
        $guiaAntiga = $this->novaGuia('Terapia ABA');
        $this->gravarSessao($guiaAntiga, '2026-09-21', '08:00');

        $guiaNova = $this->novaGuia('Terapia ABA', paciente: $guiaAntiga->paciente);

        $resultado = app(AvaliadorDeAgenda::class)->avaliar([
            $this->candidata($guiaNova, '1', '2026-09-21', '08:20'),
        ]);

        $this->assertTrue($resultado->temConflito());
        $this->assertSame($guiaAntiga->id, $resultado->conflitos[0]->guiaId);
        $this->assertStringContainsString($guiaAntiga->numero_guia, $resultado->conflitos[0]->mensagem);
    }

    public function test_sessao_cancelada_nao_ocupa_horario(): void
    {
        $guiaAntiga = $this->novaGuia('Terapia ABA');
        $this->gravarSessao($guiaAntiga, '2026-09-21', '08:00', status: 'canceled');

        $guiaNova = $this->novaGuia('Terapia ABA', paciente: $guiaAntiga->paciente);

        $resultado = app(AvaliadorDeAgenda::class)->avaliar([
            $this->candidata($guiaNova, '1', '2026-09-21', '08:20'),
        ]);

        $this->assertFalse($resultado->temConflito());
    }

    public function test_sessao_de_outro_paciente_nao_conflita(): void
    {
        $guiaOutro = $this->novaGuia('Terapia ABA');
        $this->gravarSessao($guiaOutro, '2026-09-21', '08:00');

        $guia = $this->novaGuia('Terapia ABA', paciente: $this->outroPaciente($guiaOutro->paciente_id));

        $resultado = app(AvaliadorDeAgenda::class)->avaliar([
            $this->candidata($guia, '1', '2026-09-21', '08:00'),
        ]);

        $this->assertFalse($resultado->temConflito());
    }

    public function test_editar_a_propria_sessao_nao_conflita_consigo_mesma(): void
    {
        $guia = $this->novaGuia('Terapia ABA');
        $lancamento = $this->gravarSessao($guia, '2026-09-21', '08:00');

        $resultado = app(AvaliadorDeAgenda::class)->avaliar([
            new SessaoCandidata(
                referencia: 'lancamento:'.$lancamento->id,
                pacienteId: (int) $guia->paciente_id,
                especialidadeId: (int) $guia->especialidade_id,
                especialidadeNome: $guia->especialidade->nome,
                profissionalId: (int) $guia->profissional_id,
                data: CarbonImmutable::parse('2026-09-21'),
                horaInicio: '08:10',
                lancamentoId: (int) $lancamento->id,
                guiaId: (int) $guia->id,
                guiaNumero: $guia->numero_guia,
            ),
        ]);

        $this->assertFalse($resultado->temConflito());
    }

    public function test_choque_do_profissional_vira_aviso_e_nao_conflito(): void
    {
        $guiaOutro = $this->novaGuia('Terapia ABA');
        $this->gravarSessao($guiaOutro, '2026-09-21', '08:00');

        $guia = $this->novaGuia(
            'Terapia ABA',
            paciente: $this->outroPaciente($guiaOutro->paciente_id),
            profissional: $guiaOutro->profissional,
        );

        $resultado = app(AvaliadorDeAgenda::class)->avaliar([
            $this->candidata($guia, '1', '2026-09-21', '08:00'),
        ]);

        $this->assertFalse($resultado->temConflito());
        $this->assertCount(1, $resultado->avisos);
        $this->assertStringContainsString('executante já tem sessão', $resultado->avisos[0]->mensagem);
    }

    public function test_lote_vazio_nao_avalia_nada(): void
    {
        $resultado = app(AvaliadorDeAgenda::class)->avaliar([]);

        $this->assertFalse($resultado->temConflito());
        $this->assertSame([], $resultado->avisos);
    }

    private function candidata(Guia $guia, string $referencia, string $data, ?string $hora): SessaoCandidata
    {
        return new SessaoCandidata(
            referencia: $referencia,
            pacienteId: (int) $guia->paciente_id,
            especialidadeId: (int) $guia->especialidade_id,
            especialidadeNome: $guia->especialidade->nome,
            profissionalId: (int) $guia->profissional_id,
            data: CarbonImmutable::parse($data),
            horaInicio: $hora,
            guiaId: (int) $guia->id,
            guiaNumero: $guia->numero_guia,
        );
    }

    private function gravarSessao(Guia $guia, string $data, string $hora, string $status = 'completed'): Lancamento
    {
        return Lancamento::query()->create([
            'tenant_id' => $guia->tenant_id,
            'guia_id' => $guia->id,
            'profissional_id' => $guia->profissional_id,
            'data_sessao' => $data,
            'hora_inicio' => $hora,
            'status' => $status,
        ]);
    }

    private function outroPaciente(int $exceto): Paciente
    {
        return Paciente::query()->whereKeyNot($exceto)->firstOrFail();
    }

    private function novaGuia(
        string $especialidadeNome,
        ?Paciente $paciente = null,
        ?Profissional $profissional = null,
    ): Guia {
        $tenant = Tenant::query()->where('slug', 'clinica-exemplo')->firstOrFail();
        $convenio = Convenio::query()->where('nome', 'Unimed')->firstOrFail();
        $especialidade = Especialidade::query()->where('nome', $especialidadeNome)->firstOrFail();
        $profissional ??= Profissional::query()->where('especialidade_id', $especialidade->id)->firstOrFail();
        $paciente ??= Paciente::query()->where('convenio_id', $convenio->id)->firstOrFail();

        $guia = Guia::query()->create([
            'tenant_id' => $tenant->id,
            'convenio_id' => $convenio->id,
            'paciente_id' => $paciente->id,
            'profissional_id' => $profissional->id,
            'especialidade_id' => $especialidade->id,
            'numero_guia' => 'GUIA-'.uniqid(),
            'tipo_terapia' => 'especializada',
            'status' => 'approved',
            'sessoes_autorizadas' => 10,
            'data_solicitacao' => today(),
        ]);

        return $guia->load(['especialidade', 'paciente']);
    }
}
