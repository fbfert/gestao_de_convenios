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

    /** A captura da webcam chega como JPEG e é guardada como PDF. */
    public function test_confirmacao_com_imagem_guarda_a_folha_em_pdf(): void
    {
        $this->autenticar();
        $guia = $this->guiaAprovada();

        $this->post("/api/guias/{$guia->id}/lancamentos/importar-transcricao", [
            'profissional_id' => $guia->profissional_id,
            'confirmar_envio' => true,
            'sessoes' => [['data_sessao' => '2026-09-21', 'hora_inicio' => '08:00']],
            'pdf_registro_sessoes' => UploadedFile::fake()->image('registro-sessoes.jpg', 120, 90),
        ])->assertCreated();

        $folha = PacienteArquivo::query()->where('tipo', 'registro_sessoes')->sole();

        $this->assertSame('registro-sessoes.pdf', $folha->nome_original);
        $this->assertSame('application/pdf', $folha->mime);
        $this->assertStringEndsWith('.pdf', $folha->path);

        $conteudo = Storage::disk('local')->get($folha->path);
        $this->assertStringStartsWith('%PDF-', $conteudo);
        $this->assertStringContainsString('/Filter /DCTDecode', $conteudo);
        // Imagem mais larga que alta: A4 deitado.
        $this->assertStringContainsString('/MediaBox [0 0 842 595]', $conteudo);
        $this->assertStringEndsWith("%%EOF\n", $conteudo);
    }

    public function test_anexar_depois_tambem_converte_png_em_pdf(): void
    {
        $this->autenticar();
        $guia = $this->guiaAprovada();

        $this->post("/api/guias/{$guia->id}/folhas-registro", [
            'arquivos' => [UploadedFile::fake()->image('foto-da-folha.png', 60, 90)],
        ])->assertCreated()
            ->assertJsonPath('data.0.nome_original', 'foto-da-folha.pdf')
            ->assertJsonPath('data.0.mime', 'application/pdf');

        $folha = PacienteArquivo::query()->where('tipo', 'registro_sessoes')->sole();
        $conteudo = Storage::disk('local')->get($folha->path);
        $this->assertStringStartsWith('%PDF-', $conteudo);
        $this->assertStringContainsString('/MediaBox [0 0 595 842]', $conteudo);
    }

    /** Confirmação recusada não deixa folha órfã na pasta do paciente. */
    public function test_confirmacao_recusada_nao_guarda_a_folha(): void
    {
        $this->autenticar();
        $guia = $this->guiaAprovada();

        // Duas sessões a dez minutos uma da outra: conflito de agenda.
        $this->post("/api/guias/{$guia->id}/lancamentos/importar-transcricao", [
            'profissional_id' => $guia->profissional_id,
            'confirmar_envio' => true,
            'sessoes' => [
                ['data_sessao' => '2026-09-21', 'hora_inicio' => '08:00'],
                ['data_sessao' => '2026-09-21', 'hora_inicio' => '08:10'],
            ],
            'pdf_registro_sessoes' => UploadedFile::fake()->image('registro-sessoes.jpg'),
        ], ['Accept' => 'application/json'])->assertStatus(422);

        $this->assertSame(0, PacienteArquivo::query()->where('tipo', 'registro_sessoes')->count());
        $this->assertSame([], Storage::disk('local')->allFiles("pacientes/{$guia->paciente_id}"));
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

    /** A pasta do paciente não é desvio da regra acima. */
    public function test_pasta_do_paciente_nao_remove_folha_de_guia_ja_finalizada(): void
    {
        $this->autenticar();
        $guia = $this->guiaAprovada();

        $this->post("/api/guias/{$guia->id}/folhas-registro", [
            'arquivos' => [UploadedFile::fake()->create('folha.pdf', 64, 'application/pdf')],
        ])->assertCreated();

        $folha = PacienteArquivo::query()->where('tipo', 'registro_sessoes')->firstOrFail();
        app(\App\Services\GuiaService::class)->registrarTransicao($guia, GuiaStatus::FINALIZED);

        $this->getJson("/api/pacientes/{$guia->paciente_id}/arquivos")
            ->assertOk()
            ->assertJsonPath('data.0.guia.numero_guia', $guia->numero_guia)
            ->assertJsonPath('data.0.guia.folha_travada', true);

        $this->deleteJson("/api/pacientes/{$guia->paciente_id}/arquivos/{$folha->id}")
            ->assertStatus(422)
            ->assertJsonValidationErrors(['arquivo']);

        $this->assertSame(1, PacienteArquivo::query()->where('tipo', 'registro_sessoes')->count());
        Storage::disk('local')->assertExists($folha->path);
    }

    public function test_pasta_do_paciente_mostra_a_guia_e_remove_folha_de_guia_em_aberto(): void
    {
        $this->autenticar();
        $guia = $this->guiaAprovada();

        $this->post("/api/guias/{$guia->id}/folhas-registro", [
            'arquivos' => [UploadedFile::fake()->create('folha.pdf', 64, 'application/pdf')],
        ])->assertCreated();

        $folha = PacienteArquivo::query()->where('tipo', 'registro_sessoes')->firstOrFail();

        $this->getJson("/api/pacientes/{$guia->paciente_id}/arquivos")
            ->assertOk()
            ->assertJsonPath('data.0.guia.id', $guia->id)
            ->assertJsonPath('data.0.guia.numero_guia', $guia->numero_guia)
            ->assertJsonPath('data.0.guia.folha_travada', false);

        $this->deleteJson("/api/pacientes/{$guia->paciente_id}/arquivos/{$folha->id}")->assertNoContent();

        $this->assertSame(0, PacienteArquivo::query()->where('tipo', 'registro_sessoes')->count());
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

    /** A captura da webcam, já convertida em PDF, satisfaz a regional 0220. */
    public function test_regional_0220_aceita_a_folha_capturada_pela_webcam(): void
    {
        $this->autenticar();
        $guia = $this->guiaAprovada();

        $this->post("/api/guias/{$guia->id}/lancamentos/importar-transcricao", [
            'profissional_id' => $guia->profissional_id,
            'confirmar_envio' => true,
            'numero_cartao' => '0220 090000 551.330-8',
            'sessoes' => [['data_sessao' => '2026-09-21', 'hora_inicio' => '08:00']],
            'pdf_registro_sessoes' => UploadedFile::fake()->image('registro-sessoes.jpg'),
        ])->assertCreated();

        $this->assertSame('application/pdf', PacienteArquivo::query()->where('tipo', 'registro_sessoes')->sole()->mime);
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
