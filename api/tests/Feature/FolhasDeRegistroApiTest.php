<?php

namespace Tests\Feature;

use App\Models\Convenio;
use App\Models\Especialidade;
use App\Models\Guia;
use App\Models\Paciente;
use App\Models\PacienteArquivo;
use App\Models\Profissional;
use App\Models\Tenant;
use App\Models\User;
use App\Support\GuiaStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Várias folhas de registro por guia — ver a spec `importacao-de-sessoes`.
 *
 * Uma guia de dez sessões costuma ser impressa em duas vias e preenchida em
 * partes, então o anexo deixou de ser um arquivo por remessa para ser N folhas
 * por guia, anexáveis também depois.
 */
class FolhasDeRegistroApiTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_confirmacao_aceita_varias_folhas_de_uma_vez(): void
    {
        $this->autenticar();
        $guia = $this->guiaAprovada();

        $this->post("/api/guias/{$guia->id}/lancamentos/importar-transcricao", [
            'profissional_id' => $guia->profissional_id,
            'confirmar_envio' => true,
            'sessoes' => [
                ['data_sessao' => '2026-09-21', 'hora_inicio' => '08:00'],
            ],
            'pdf_registro_sessoes' => [
                UploadedFile::fake()->create('folha-1.pdf', 64, 'application/pdf'),
                UploadedFile::fake()->create('folha-2.pdf', 64, 'application/pdf'),
            ],
        ])->assertCreated();

        $folhas = PacienteArquivo::query()
            ->where('paciente_id', $guia->paciente_id)
            ->where('tipo', 'registro_sessoes')
            ->get();

        $this->assertCount(2, $folhas);
        $this->assertSame(
            [$guia->id, $guia->id],
            $folhas->pluck('metadata.guia_id')->all(),
        );
        $this->assertEqualsCanonicalizing(
            ['folha-1.pdf', 'folha-2.pdf'],
            $folhas->pluck('nome_original')->all(),
        );
    }

    public function test_confirmacao_continua_aceitando_uma_folha_so(): void
    {
        $this->autenticar();
        $guia = $this->guiaAprovada();

        $this->post("/api/guias/{$guia->id}/lancamentos/importar-transcricao", [
            'profissional_id' => $guia->profissional_id,
            'confirmar_envio' => true,
            'sessoes' => [['data_sessao' => '2026-09-21', 'hora_inicio' => '08:00']],
            'pdf_registro_sessoes' => UploadedFile::fake()->create('unica.pdf', 64, 'application/pdf'),
        ])->assertCreated();

        $this->assertSame(1, PacienteArquivo::query()->where('tipo', 'registro_sessoes')->count());
    }

    public function test_anexa_folha_depois_sem_confirmar_sessoes_de_novo(): void
    {
        $this->autenticar();
        $guia = $this->guiaAprovada();

        $this->post("/api/guias/{$guia->id}/folhas-registro", [
            'arquivos' => [UploadedFile::fake()->create('segunda-via.pdf', 64, 'application/pdf')],
        ])->assertCreated()
            ->assertJsonPath('data.0.nome_original', 'segunda-via.pdf');

        $this->assertSame(0, $guia->lancamentos()->count());
        $this->assertSame(1, PacienteArquivo::query()->where('tipo', 'registro_sessoes')->count());
    }

    public function test_lista_folhas_com_data_e_quem_enviou(): void
    {
        $usuario = $this->autenticar();
        $guia = $this->guiaAprovada();

        $this->post("/api/guias/{$guia->id}/folhas-registro", [
            'arquivos' => [UploadedFile::fake()->create('folha.pdf', 64, 'application/pdf')],
        ])->assertCreated();

        $resposta = $this->getJson("/api/guias/{$guia->id}/folhas-registro")->assertOk();

        $this->assertCount(1, $resposta->json('data'));
        $this->assertSame('folha.pdf', $resposta->json('data.0.nome_original'));
        $this->assertSame($usuario->name, $resposta->json('data.0.enviado_por'));
        $this->assertNotNull($resposta->json('data.0.enviado_em'));
    }

    public function test_lista_nao_mistura_folhas_de_outra_guia_do_mesmo_paciente(): void
    {
        $this->autenticar();
        $primeira = $this->guiaAprovada();
        $segunda = $this->guiaAprovada(paciente: $primeira->paciente);

        $this->post("/api/guias/{$primeira->id}/folhas-registro", [
            'arquivos' => [UploadedFile::fake()->create('da-primeira.pdf', 64, 'application/pdf')],
        ])->assertCreated();

        $this->post("/api/guias/{$segunda->id}/folhas-registro", [
            'arquivos' => [UploadedFile::fake()->create('da-segunda.pdf', 64, 'application/pdf')],
        ])->assertCreated();

        $resposta = $this->getJson("/api/guias/{$primeira->id}/folhas-registro")->assertOk();

        $this->assertCount(1, $resposta->json('data'));
        $this->assertSame('da-primeira.pdf', $resposta->json('data.0.nome_original'));
    }

    public function test_remove_folha_de_guia_ainda_nao_finalizada(): void
    {
        $this->autenticar();
        $guia = $this->guiaAprovada();

        $this->post("/api/guias/{$guia->id}/folhas-registro", [
            'arquivos' => [UploadedFile::fake()->create('folha.pdf', 64, 'application/pdf')],
        ])->assertCreated();

        $folha = PacienteArquivo::query()->where('tipo', 'registro_sessoes')->firstOrFail();

        $this->deleteJson("/api/guias/{$guia->id}/folhas-registro/{$folha->id}")->assertNoContent();

        $this->assertSame(0, PacienteArquivo::query()->where('tipo', 'registro_sessoes')->count());
    }

    /**
     * Depois de finalizada na operadora a folha deixa de ser rascunho: é o
     * comprovante do que foi enviado, e apagá-la apagaria a prova da remessa.
     */
    public function test_nao_remove_folha_de_guia_ja_finalizada(): void
    {
        $this->autenticar();
        $guia = $this->guiaAprovada();

        $this->post("/api/guias/{$guia->id}/folhas-registro", [
            'arquivos' => [UploadedFile::fake()->create('folha.pdf', 64, 'application/pdf')],
        ])->assertCreated();

        $folha = PacienteArquivo::query()->where('tipo', 'registro_sessoes')->firstOrFail();

        // Status de guia só muda por registrarTransicao() — o model recusa
        // qualquer outro caminho (ver Guia::booted).
        app(\App\Services\GuiaService::class)->registrarTransicao($guia, GuiaStatus::FINALIZED);

        $this->deleteJson("/api/guias/{$guia->id}/folhas-registro/{$folha->id}")
            ->assertStatus(422)
            ->assertJsonValidationErrors(['arquivo']);

        $this->assertSame(1, PacienteArquivo::query()->where('tipo', 'registro_sessoes')->count());
    }

    public function test_nao_remove_folha_que_pertence_a_outra_guia(): void
    {
        $this->autenticar();
        $dona = $this->guiaAprovada();
        $outra = $this->guiaAprovada(paciente: $dona->paciente);

        $this->post("/api/guias/{$dona->id}/folhas-registro", [
            'arquivos' => [UploadedFile::fake()->create('folha.pdf', 64, 'application/pdf')],
        ])->assertCreated();

        $folha = PacienteArquivo::query()->where('tipo', 'registro_sessoes')->firstOrFail();

        $this->deleteJson("/api/guias/{$outra->id}/folhas-registro/{$folha->id}")
            ->assertStatus(422)
            ->assertJsonValidationErrors(['arquivo']);
    }

    public function test_regional_0220_continua_exigindo_ao_menos_uma_folha(): void
    {
        $this->autenticar();
        $guia = $this->guiaAprovada();

        $this->postJson("/api/guias/{$guia->id}/lancamentos/importar-transcricao", [
            'profissional_id' => $guia->profissional_id,
            'confirmar_envio' => true,
            'numero_cartao' => '0220 090000 551.330-8',
            'sessoes' => [['data_sessao' => '2026-09-21', 'hora_inicio' => '08:00']],
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['pdf_registro_sessoes']);

        // E uma folha basta — as outras vias podem vir depois.
        $this->post("/api/guias/{$guia->id}/lancamentos/importar-transcricao", [
            'profissional_id' => $guia->profissional_id,
            'confirmar_envio' => true,
            'numero_cartao' => '0220 090000 551.330-8',
            'sessoes' => [['data_sessao' => '2026-09-21', 'hora_inicio' => '08:00']],
            'pdf_registro_sessoes' => UploadedFile::fake()->create('folha.pdf', 64, 'application/pdf'),
        ])->assertCreated();
    }

    private function autenticar(): User
    {
        $usuario = User::query()->where('email', 'admin@clinica-exemplo.test')->firstOrFail();
        Sanctum::actingAs($usuario);

        return $usuario;
    }

    private function guiaAprovada(?Paciente $paciente = null): Guia
    {
        $tenant = Tenant::query()->where('slug', 'clinica-exemplo')->firstOrFail();
        $convenio = Convenio::query()->where('nome', 'Unimed')->firstOrFail();
        $especialidade = Especialidade::query()->where('nome', 'Terapia ABA')->firstOrFail();
        $profissional = Profissional::query()->where('especialidade_id', $especialidade->id)->firstOrFail();
        $paciente ??= Paciente::query()->where('convenio_id', $convenio->id)->firstOrFail();

        return Guia::query()->create([
            'tenant_id' => $tenant->id,
            'convenio_id' => $convenio->id,
            'paciente_id' => $paciente->id,
            'profissional_id' => $profissional->id,
            'especialidade_id' => $especialidade->id,
            'numero_guia' => 'FOLHA-'.uniqid(),
            'tipo_terapia' => 'especializada',
            'status' => GuiaStatus::APPROVED,
            'sessoes_autorizadas' => 10,
            'data_solicitacao' => today(),
        ]);
    }
}
