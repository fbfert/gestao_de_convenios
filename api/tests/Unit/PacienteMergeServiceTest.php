<?php

namespace Tests\Unit;

use App\Models\Antecipacao;
use App\Models\ClinicaPacientePendente;
use App\Models\Convenio;
use App\Models\Especialidade;
use App\Models\Guia;
use App\Models\Paciente;
use App\Models\PacienteArquivo;
use App\Models\PacienteDocumento;
use App\Models\PacienteTelefone;
use App\Models\Profissional;
use App\Models\Solicitacao;
use App\Models\Tenant;
use App\Services\PacienteMergeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class PacienteMergeServiceTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private function tenantId(): int
    {
        return Tenant::query()->where('slug', 'clinica-exemplo')->firstOrFail()->id;
    }

    private function convenioId(): int
    {
        return Convenio::query()->where('tenant_id', $this->tenantId())->where('nome', 'Unimed')->firstOrFail()->id;
    }

    private function profissionalId(): int
    {
        return Profissional::query()->where('tenant_id', $this->tenantId())->firstOrFail()->id;
    }

    private function especialidadeId(): int
    {
        return Especialidade::query()->where('tenant_id', $this->tenantId())->firstOrFail()->id;
    }

    private function criarPaciente(array $overrides = []): Paciente
    {
        return Paciente::query()->create(array_merge([
            'tenant_id' => $this->tenantId(),
            'nome' => 'Abner dos Santos Beiger',
            'convenio_id' => $this->convenioId(),
            'carteirinha' => '00011122233',
            'ativo' => true,
        ], $overrides));
    }

    private function criarSolicitacao(Paciente $paciente): Solicitacao
    {
        return Solicitacao::query()->create([
            'tenant_id' => $this->tenantId(),
            'paciente_id' => $paciente->id,
            'profissional_id' => $this->profissionalId(),
            'especialidade_id' => $this->especialidadeId(),
            'convenio_id' => $this->convenioId(),
            'medico_id' => null,
            'status' => 'em_analise',
            'solicitado_em' => today(),
        ]);
    }

    private function criarGuia(Paciente $paciente): Guia
    {
        return Guia::query()->create([
            'tenant_id' => $this->tenantId(),
            'solicitacao_id' => null,
            'convenio_id' => $this->convenioId(),
            'paciente_id' => $paciente->id,
            'profissional_id' => $this->profissionalId(),
            'especialidade_id' => $this->especialidadeId(),
            'numero_guia' => 'GUIA-MERGE-'.uniqid(),
            'tipo_terapia' => 'especializada',
            'status' => 'under_review',
            'data_solicitacao' => today(),
        ]);
    }

    private function criarAntecipacao(Paciente $paciente, Guia $guia): Antecipacao
    {
        return Antecipacao::query()->create([
            'tenant_id' => $this->tenantId(),
            'guia_id' => $guia->id,
            'paciente_id' => $paciente->id,
            'convenio_id' => $this->convenioId(),
            'ciclo_inicio' => today(),
            'ciclo_fim' => today(),
            'qtd_autorizada' => 1,
            'qtd_utilizada' => 0,
            'status' => 'open',
        ]);
    }

    public function test_mesclar_reponta_solicitacoes_guias_e_antecipacoes(): void
    {
        $tenantId = $this->tenantId();
        $vencedor = $this->criarPaciente(['nome' => 'Abner dos Santos Beiger', 'cpf' => null]);
        $perdedor = $this->criarPaciente(['nome' => 'Abner Santos Beiger', 'carteirinha' => 'SYNC-CLINICA-102']);

        $solicitacao = $this->criarSolicitacao($perdedor);
        $guia = $this->criarGuia($perdedor);
        $antecipacao = $this->criarAntecipacao($perdedor, $guia);

        (new PacienteMergeService())->mesclar($tenantId, $vencedor->id, $perdedor->id);

        $this->assertSame($vencedor->id, $solicitacao->fresh()->paciente_id);
        $this->assertSame($vencedor->id, $guia->fresh()->paciente_id);
        $this->assertSame($vencedor->id, $antecipacao->fresh()->paciente_id);
    }

    public function test_mesclar_reponta_telefones_documentos_e_arquivos(): void
    {
        $tenantId = $this->tenantId();
        $vencedor = $this->criarPaciente();
        $perdedor = $this->criarPaciente(['nome' => 'Abner Santos Beiger']);

        $telefone = PacienteTelefone::query()->create([
            'tenant_id' => $tenantId, 'paciente_id' => $perdedor->id,
            'numero' => '11999990000', 'rotulo' => 'celular', 'principal' => true, 'ordem' => 0,
        ]);
        $documento = PacienteDocumento::query()->create([
            'tenant_id' => $tenantId, 'paciente_id' => $perdedor->id,
            'tipo' => 'carteirinha', 'path' => 'x.jpg', 'mime' => 'image/jpeg', 'nome_original' => 'x.jpg',
            'expira_em' => today()->addDays(30),
        ]);
        $arquivo = PacienteArquivo::query()->create([
            'tenant_id' => $tenantId, 'paciente_id' => $perdedor->id,
            'tipo' => 'pedido_medico', 'nome_original' => 'y.pdf', 'path' => 'y.pdf',
        ]);

        (new PacienteMergeService())->mesclar($tenantId, $vencedor->id, $perdedor->id);

        $this->assertSame($vencedor->id, $telefone->fresh()->paciente_id);
        $this->assertSame($vencedor->id, $documento->fresh()->paciente_id);
        $this->assertSame($vencedor->id, $arquivo->fresh()->paciente_id);
    }

    public function test_mesclar_desativa_perdedor_e_marca_mesclado(): void
    {
        $tenantId = $this->tenantId();
        $vencedor = $this->criarPaciente();
        $perdedor = $this->criarPaciente(['nome' => 'Abner Santos Beiger']);

        (new PacienteMergeService())->mesclar($tenantId, $vencedor->id, $perdedor->id);

        $perdedor->refresh();
        $this->assertFalse($perdedor->ativo);
        $this->assertSame($vencedor->id, $perdedor->mesclado_em_id);
        $this->assertNotNull($perdedor->mesclado_em);
        $this->assertTrue($vencedor->fresh()->ativo);
    }

    public function test_mesclar_nunca_sobrescreve_carteirinha_do_vencedor(): void
    {
        $tenantId = $this->tenantId();
        $vencedor = $this->criarPaciente(['carteirinha' => '99988877766']);
        $perdedor = $this->criarPaciente(['nome' => 'Abner Santos Beiger', 'carteirinha' => 'SYNC-CLINICA-102']);

        (new PacienteMergeService())->mesclar($tenantId, $vencedor->id, $perdedor->id);

        $this->assertSame('99988877766', $vencedor->fresh()->carteirinha);
    }

    public function test_mesclar_preenche_so_campos_vazios_do_vencedor(): void
    {
        $tenantId = $this->tenantId();
        $vencedor = $this->criarPaciente(['cpf' => null, 'data_nascimento' => null, 'telefone' => null]);
        $perdedor = $this->criarPaciente([
            'nome' => 'Abner Santos Beiger',
            'cpf' => '11144477735',
            'data_nascimento' => '1990-01-01',
            'telefone' => '11999990000',
        ]);

        (new PacienteMergeService())->mesclar($tenantId, $vencedor->id, $perdedor->id);

        $vencedor->refresh();
        $this->assertSame('11144477735', $vencedor->cpf);
        $this->assertSame('1990-01-01', $vencedor->data_nascimento->toDateString());
        $this->assertSame('11999990000', $vencedor->telefone);
    }

    public function test_mesclar_nao_sobrescreve_cpf_ja_preenchido_do_vencedor(): void
    {
        $tenantId = $this->tenantId();
        $vencedor = $this->criarPaciente(['cpf' => '22233344455']);
        $perdedor = $this->criarPaciente(['nome' => 'Abner Santos Beiger', 'cpf' => '11144477735']);

        (new PacienteMergeService())->mesclar($tenantId, $vencedor->id, $perdedor->id);

        $this->assertSame('22233344455', $vencedor->fresh()->cpf);
    }

    public function test_mesclar_com_clinica_id_conflitante_sem_escolha_lanca_excecao(): void
    {
        $tenantId = $this->tenantId();
        $vencedor = $this->criarPaciente(['clinica_id' => 501]);
        $perdedor = $this->criarPaciente(['nome' => 'Abner Santos Beiger', 'clinica_id' => 502]);

        $this->expectException(InvalidArgumentException::class);
        (new PacienteMergeService())->mesclar($tenantId, $vencedor->id, $perdedor->id);
    }

    public function test_mesclar_com_clinica_id_conflitante_e_escolha_aplica_o_escolhido(): void
    {
        $tenantId = $this->tenantId();
        $vencedor = $this->criarPaciente(['clinica_id' => 501]);
        $perdedor = $this->criarPaciente(['nome' => 'Abner Santos Beiger', 'clinica_id' => 502]);

        (new PacienteMergeService())->mesclar($tenantId, $vencedor->id, $perdedor->id, 502);

        $this->assertSame(502, $vencedor->fresh()->clinica_id);
        $this->assertNull($perdedor->fresh()->clinica_id);
    }

    public function test_mesclar_transfere_clinica_id_quando_so_perdedor_tem(): void
    {
        $tenantId = $this->tenantId();
        $vencedor = $this->criarPaciente();
        $perdedor = $this->criarPaciente(['nome' => 'Abner Santos Beiger', 'clinica_id' => 502]);

        (new PacienteMergeService())->mesclar($tenantId, $vencedor->id, $perdedor->id);

        $this->assertSame(502, $vencedor->fresh()->clinica_id);
        $this->assertNull($perdedor->fresh()->clinica_id);
    }

    public function test_nao_permite_mesclar_paciente_ja_mesclado(): void
    {
        $tenantId = $this->tenantId();
        $vencedor = $this->criarPaciente();
        $perdedor = $this->criarPaciente(['nome' => 'Abner Santos Beiger']);
        $terceiro = $this->criarPaciente(['nome' => 'Abner S. Beiger']);

        (new PacienteMergeService())->mesclar($tenantId, $vencedor->id, $perdedor->id);

        $this->expectException(InvalidArgumentException::class);
        (new PacienteMergeService())->mesclar($tenantId, $terceiro->id, $perdedor->id);
    }

    public function test_reponta_pendencia_de_sync_e_linha_de_importacao(): void
    {
        $tenantId = $this->tenantId();
        $vencedor = $this->criarPaciente();
        $perdedor = $this->criarPaciente(['nome' => 'Abner Santos Beiger']);

        $pendencia = ClinicaPacientePendente::query()->create([
            'tenant_id' => $tenantId,
            'clinica_id' => 999,
            'dados_remoto' => ['nome' => 'Outro Paciente'],
            'remoto_atualizado_em' => now(),
            'status' => 'pendente',
            'candidato_paciente_id' => $perdedor->id,
            'similaridade' => 91,
            'candidatos_json' => [],
        ]);

        (new PacienteMergeService())->mesclar($tenantId, $vencedor->id, $perdedor->id);

        $this->assertSame($vencedor->id, $pendencia->fresh()->candidato_paciente_id);
    }

    public function test_preview_conta_registros_afetados(): void
    {
        $tenantId = $this->tenantId();
        $vencedor = $this->criarPaciente();
        $perdedor = $this->criarPaciente(['nome' => 'Abner Santos Beiger']);
        $this->criarSolicitacao($perdedor);
        $guia = $this->criarGuia($perdedor);
        $this->criarAntecipacao($perdedor, $guia);

        $resumo = (new PacienteMergeService())->preview($tenantId, $vencedor->id, $perdedor->id);

        $this->assertSame(1, $resumo['solicitacoes']);
        $this->assertSame(1, $resumo['guias']);
        $this->assertSame(1, $resumo['antecipacoes']);
        $this->assertFalse($resumo['conflito_clinica_id']);
    }
}
