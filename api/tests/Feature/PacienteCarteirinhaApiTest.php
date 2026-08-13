<?php

namespace Tests\Feature;

use App\Jobs\ExpurgarCarteirinhasJob;
use App\Models\AiOpenaiSetting;
use App\Models\AiPromptTemplate;
use App\Models\ConfiguracaoGlobal;
use App\Models\Convenio;
use App\Models\Paciente;
use App\Models\PacienteDocumento;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PacienteCarteirinhaApiTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_recusa_cpf_com_digito_verificador_errado(): void
    {
        $this->autenticar();
        $convenio = $this->convenio();

        $this->postJson('/api/pacientes', [
            'nome' => 'Paciente CPF Inválido',
            'cpf' => '111.222.333-44',
            'carteirinha' => 'CPF-TESTE-1',
            'convenio_id' => $convenio->id,
        ])->assertStatus(422)->assertJsonValidationErrors('cpf');
    }

    public function test_grava_cpf_somente_com_digitos(): void
    {
        $this->autenticar();
        $convenio = $this->convenio();

        $this->postJson('/api/pacientes', [
            'nome' => 'Paciente CPF Válido',
            'cpf' => '111.222.333-96',
            'carteirinha' => 'CPF-TESTE-2',
            'convenio_id' => $convenio->id,
        ])
            ->assertCreated()
            ->assertJsonPath('data.cpf', '11122233396');
    }

    public function test_aceita_cadastro_sem_cpf(): void
    {
        $this->autenticar();
        $convenio = $this->convenio();

        $this->postJson('/api/pacientes', [
            'nome' => 'Paciente Sem CPF',
            'cpf' => '',
            'carteirinha' => 'CPF-TESTE-3',
            'convenio_id' => $convenio->id,
        ])
            ->assertCreated()
            ->assertJsonPath('data.cpf', null);
    }

    public function test_um_unico_telefone_principal(): void
    {
        $this->autenticar();
        $convenio = $this->convenio();

        $this->postJson('/api/pacientes', [
            'nome' => 'Paciente Telefones',
            'carteirinha' => 'TEL-TESTE-1',
            'convenio_id' => $convenio->id,
            'telefones' => [
                ['numero' => '11911111111', 'principal' => true],
                ['numero' => '11922222222', 'principal' => true],
                // Linha em branco é desistência do operador, não erro.
                ['numero' => ''],
            ],
        ])
            ->assertCreated()
            ->assertJsonCount(2, 'data.telefones')
            ->assertJsonPath('data.telefones.0.principal', true)
            ->assertJsonPath('data.telefones.1.principal', false);
    }

    public function test_busca_encontra_paciente_por_telefone_e_cpf(): void
    {
        $this->autenticar();
        $convenio = $this->convenio();

        $this->postJson('/api/pacientes', [
            'nome' => 'Paciente Procurável',
            'cpf' => '222.333.444-05',
            'carteirinha' => 'BUSCA-TESTE-1',
            'convenio_id' => $convenio->id,
            'telefones' => [['numero' => '(11) 98765-4321']],
        ])->assertCreated();

        $this->getJson('/api/pacientes?busca=98765-4321')
            ->assertOk()
            ->assertJsonFragment(['nome' => 'Paciente Procurável']);

        $this->getJson('/api/pacientes?busca=222.333.444-05')
            ->assertOk()
            ->assertJsonFragment(['nome' => 'Paciente Procurável']);
    }

    public function test_identificador_do_clinica_agil_nao_e_aceito_nem_apagado(): void
    {
        $this->autenticar();
        $convenio = $this->convenio();

        $paciente = Paciente::query()->create([
            'tenant_id' => $this->usuario()->tenant_id,
            'nome' => 'Paciente Externo',
            'carteirinha' => 'CA-TESTE-1',
            'convenio_id' => $convenio->id,
            'clinica_agil_id' => 'CA-999',
            'ativo' => true,
        ]);

        $this->patchJson("/api/pacientes/{$paciente->id}", [
            'nome' => 'Paciente Externo Editado',
            'clinica_agil_id' => 'TENTATIVA-DE-TROCA',
        ])->assertOk();

        $this->assertSame('CA-999', $paciente->fresh()->clinica_agil_id);
    }

    public function test_carteirinha_vencida_aparece_no_recurso(): void
    {
        $this->autenticar();
        $convenio = $this->convenio();

        $this->postJson('/api/pacientes', [
            'nome' => 'Paciente Vencido',
            'carteirinha' => 'VENC-TESTE-1',
            'convenio_id' => $convenio->id,
            'validade_carteirinha' => now()->subDay()->toDateString(),
        ])
            ->assertCreated()
            ->assertJsonPath('data.carteirinha_vencida', true);
    }

    public function test_leitura_da_carteirinha_preenche_dados_e_guarda_imagem_com_prazo(): void
    {
        $this->autenticar();
        $convenio = $this->convenio();
        $this->configurarIa();
        Storage::fake('local');

        Http::fake([
            '*/responses' => Http::response([
                'output_text' => json_encode([
                    'carteirinha' => '0064 1234 567890 12 3',
                    'nome' => 'Joana da Silva',
                    'convenio' => $convenio->nome,
                    'cpf' => '111.222.333-96',
                    'data_nascimento' => '2015-04-10',
                    'validade_carteirinha' => '2030-12-31',
                    'observacoes' => null,
                ]),
            ]),
        ]);

        $resposta = $this->postJson('/api/pacientes/ler-carteirinha', [
            'arquivo' => UploadedFile::fake()->image('carteirinha.jpg'),
        ])->assertOk();

        $resposta
            ->assertJsonPath('data.dados.carteirinha', '00641234567890123')
            ->assertJsonPath('data.dados.nome', 'Joana da Silva')
            ->assertJsonPath('data.dados.cpf', '11122233396')
            ->assertJsonPath('data.dados.data_nascimento', '2015-04-10')
            ->assertJsonPath('data.convenio.id', $convenio->id);

        $documento = PacienteDocumento::query()->firstOrFail();

        // Nasce sem dono e com prazo: se o cadastro não for gravado, o expurgo
        // leva a imagem embora.
        $this->assertNull($documento->paciente_id);
        $this->assertSame(30, (int) now()->startOfDay()->diffInDays($documento->expira_em->startOfDay()));
        Storage::disk('local')->assertExists($documento->path);
    }

    public function test_convenio_lido_que_nao_existe_nao_preenche_o_campo(): void
    {
        $this->autenticar();
        $this->convenio();
        $this->configurarIa();
        Storage::fake('local');

        Http::fake([
            '*/responses' => Http::response([
                'output_text' => json_encode([
                    'carteirinha' => '123456',
                    'nome' => 'Paciente Sem Convênio',
                    'convenio' => 'Operadora Que Nao Existe Aqui',
                ]),
            ]),
        ]);

        $resposta = $this->postJson('/api/pacientes/ler-carteirinha', [
            'arquivo' => UploadedFile::fake()->image('carteirinha.jpg'),
        ])
            ->assertOk()
            ->assertJsonPath('data.convenio.id', null)
            ->assertJsonPath('data.convenio.lido', 'Operadora Que Nao Existe Aqui');

        // Mesmo sem casamento certeiro, os mais proximos voltam com nota: a
        // tela mostra o quanto o palpite e forte em vez de so dizer "nao achei".
        $candidatos = $resposta->json('data.convenio.candidatos');

        $this->assertNotEmpty($candidatos);
        $this->assertArrayHasKey('similaridade', $candidatos[0]);
        $this->assertLessThan(85, $candidatos[0]['similaridade']);
    }

    public function test_convenio_reconhecido_traz_a_nota_de_semelhanca(): void
    {
        $this->autenticar();
        $convenio = $this->convenio();
        $this->configurarIa();
        Storage::fake('local');

        Http::fake([
            '*/responses' => Http::response([
                'output_text' => json_encode([
                    'carteirinha' => '123456',
                    'nome' => 'Paciente Com Convênio',
                    'convenio' => $convenio->nome,
                ]),
            ]),
        ]);

        $this->postJson('/api/pacientes/ler-carteirinha', [
            'arquivo' => UploadedFile::fake()->image('carteirinha.jpg'),
        ])
            ->assertOk()
            ->assertJsonPath('data.convenio.id', $convenio->id)
            ->assertJsonPath('data.convenio.similaridade', 100)
            ->assertJsonPath('data.convenio.candidatos.0.nome', $convenio->nome);
    }

    public function test_leitura_exige_ia_configurada(): void
    {
        $this->autenticar();
        Storage::fake('local');

        $this->postJson('/api/pacientes/ler-carteirinha', [
            'arquivo' => UploadedFile::fake()->image('carteirinha.jpg'),
        ])->assertStatus(422)->assertJsonValidationErrors('openai');
    }

    public function test_prompt_de_sistema_nasce_na_primeira_leitura(): void
    {
        $this->autenticar();
        $tenantId = (int) $this->usuario()->tenant_id;
        Storage::fake('local');

        $this->assertDatabaseMissing('ai_prompt_templates', [
            'tenant_id' => $tenantId,
            'chave' => 'ler_carteirinha',
        ]);

        // Falha por falta de conexao, e nao por falta de prompt: quem nunca
        // abriu a tela de IA nao pode ser mandado configurar o que o sistema
        // deveria ter criado sozinho.
        $this->postJson('/api/pacientes/ler-carteirinha', [
            'arquivo' => UploadedFile::fake()->image('carteirinha.jpg'),
        ])->assertStatus(422);

        $this->assertDatabaseHas('ai_prompt_templates', [
            'tenant_id' => $tenantId,
            'chave' => 'ler_carteirinha',
        ]);
    }

    public function test_gravar_paciente_adota_a_imagem_lida(): void
    {
        $this->autenticar();
        $convenio = $this->convenio();

        $documento = PacienteDocumento::query()->create([
            'tenant_id' => $this->usuario()->tenant_id,
            'paciente_id' => null,
            'tipo' => 'carteirinha',
            'path' => 'carteirinhas/1/teste.jpg',
            'mime' => 'image/jpeg',
            'nome_original' => 'carteirinha.jpg',
            'expira_em' => now()->addDays(30),
        ]);

        $this->postJson('/api/pacientes', [
            'nome' => 'Paciente Com Imagem',
            'carteirinha' => 'IMG-TESTE-1',
            'convenio_id' => $convenio->id,
            'carteirinha_documento_id' => $documento->id,
        ])->assertCreated();

        $this->assertNotNull($documento->fresh()->paciente_id);
    }

    public function test_expurgo_apaga_imagem_vencida_e_preserva_a_vigente(): void
    {
        Storage::fake('local');
        $tenantId = (int) $this->usuario()->tenant_id;

        Storage::disk('local')->put('carteirinhas/vencida.jpg', 'x');
        Storage::disk('local')->put('carteirinhas/vigente.jpg', 'x');

        $vencida = PacienteDocumento::query()->create([
            'tenant_id' => $tenantId,
            'tipo' => 'carteirinha',
            'path' => 'carteirinhas/vencida.jpg',
            'expira_em' => now()->subDay(),
        ]);

        $vigente = PacienteDocumento::query()->create([
            'tenant_id' => $tenantId,
            'tipo' => 'carteirinha',
            'path' => 'carteirinhas/vigente.jpg',
            'expira_em' => now()->addDay(),
        ]);

        (new ExpurgarCarteirinhasJob)->handle();

        $this->assertDatabaseMissing('paciente_documentos', ['id' => $vencida->id]);
        $this->assertDatabaseHas('paciente_documentos', ['id' => $vigente->id]);
        Storage::disk('local')->assertMissing('carteirinhas/vencida.jpg');
        Storage::disk('local')->assertExists('carteirinhas/vigente.jpg');
    }

    private function configurarIa(): void
    {
        $tenantId = (int) $this->usuario()->tenant_id;

        AiPromptTemplate::garantirPadroes($tenantId);

        AiOpenaiSetting::query()->updateOrCreate(
            ['tenant_id' => $tenantId],
            [
                'api_key' => 'sk-teste',
                'base_url' => 'https://api.openai.com/v1',
                'ativo' => true,
            ],
        );

        ConfiguracaoGlobal::doTenant($tenantId);
    }

    private function convenio(): Convenio
    {
        return Convenio::query()->where('nome', 'Unimed')->firstOrFail();
    }

    private function autenticar(): void
    {
        Sanctum::actingAs($this->usuario());
    }

    private function usuario(): User
    {
        return User::query()->where('email', 'admin@clinica-exemplo.test')->firstOrFail();
    }
}
