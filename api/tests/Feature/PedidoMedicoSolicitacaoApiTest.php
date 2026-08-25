<?php

namespace Tests\Feature;

use App\Models\AiOpenaiSetting;
use App\Models\AiPromptTemplate;
use App\Models\Cid;
use App\Models\Convenio;
use App\Models\Especialidade;
use App\Models\Medico;
use App\Models\Paciente;
use App\Models\Profissional;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PedidoMedicoSolicitacaoApiTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_le_varias_especialidades_e_aponta_as_sem_cadastro(): void
    {
        Storage::fake('local');
        $user = $this->autenticar();
        $this->configurarIa($user->tenant_id);

        // Caso real: pedido de acompanhamento multidisciplinar. Antes o
        // contrato pedia `especialidade_nome` no singular e as demais
        // especialidades sobravam em `observacoes`, fora dos campos da tela.
        Http::fake([
            'api.openai.com/v1/responses' => Http::response([
                'output' => [[
                    'content' => [[
                        'type' => 'output_text',
                        'text' => json_encode([
                            'paciente_nome' => 'Ana Paula Ribeiro',
                            'medico_nome' => 'Carlos Almeida',
                            'especialidades' => ['Fisioterapia', 'Fonoaudiologia', 'Psicopedagogia'],
                            'solicitado_em' => '2026-07-22',
                            'observacoes' => 'Todos os métodos citam ABA.',
                        ]),
                    ]],
                ]],
            ]),
        ]);

        $lidas = $this->postJson('/api/solicitacoes/ler-pedido-medico', [
            'arquivo' => UploadedFile::fake()->create('pedido.jpg', 128, 'image/jpeg'),
        ])->assertOk()->json('data.sugestoes.especialidades');

        $this->assertCount(3, $lidas);
        $this->assertSame(
            ['Fisioterapia', 'Fonoaudiologia', 'Psicopedagogia'],
            array_column($lidas, 'termo'),
        );

        // Fisioterapia existe no seed e tem que casar.
        $this->assertSame('Fisioterapia', $lidas[0]['matches'][0]['nome']);

        $this->assertFalse($lidas[0]['sugere_cadastro']);

        // Psicopedagogia nao existe. similar_text a aproxima de "Psicologia"
        // (75%), que e outra terapia, entao ela fica abaixo do corte e a tela
        // oferece cadastrar em vez de aplicar o palpite.
        $semCadastro = collect($lidas)->firstWhere('termo', 'Psicopedagogia');
        $this->assertTrue($semCadastro['sugere_cadastro']);
        $this->assertNotSame('Psicopedagogia', $semCadastro['matches'][0]['nome'] ?? null);
    }

    public function test_aceita_a_chave_antiga_no_singular(): void
    {
        Storage::fake('local');
        $user = $this->autenticar();
        $this->configurarIa($user->tenant_id);

        // Um prompt editado a mao, ou um modelo que ignore a instrucao, ainda
        // pode devolver `especialidade_nome`.
        Http::fake([
            'api.openai.com/v1/responses' => Http::response([
                'output' => [[
                    'content' => [[
                        'type' => 'output_text',
                        'text' => json_encode([
                            'paciente_nome' => 'Ana Paula Ribeiro',
                            'especialidade_nome' => 'Fisioterapia',
                        ]),
                    ]],
                ]],
            ]),
        ]);

        $lidas = $this->postJson('/api/solicitacoes/ler-pedido-medico', [
            'arquivo' => UploadedFile::fake()->create('pedido.jpg', 128, 'image/jpeg'),
        ])->assertOk()->json('data.sugestoes.especialidades');

        $this->assertCount(1, $lidas);
        $this->assertSame('Fisioterapia', $lidas[0]['termo']);
        $this->assertSame('Fisioterapia', $lidas[0]['matches'][0]['nome']);
    }

    public function test_analisa_pedido_medico_e_cria_solicitacao_com_anexo(): void
    {
        Storage::fake('local');
        $user = $this->autenticar();
        $this->configurarIa($user->tenant_id);

        Http::fake([
            'api.openai.com/v1/responses' => Http::response([
                'output' => [
                    [
                        'content' => [
                            [
                                'type' => 'output_text',
                                'text' => json_encode([
                                    'paciente_nome' => 'Ana Paula Ribeiro',
                                    'medico_nome' => 'Carlos Almeida',
                                    'especialidade_nome' => 'Fisioterapia',
                                    'solicitado_em' => '2026-07-22',
                                    'observacoes' => 'Pedido com assinatura ilegível.',
                                ]),
                            ],
                        ],
                    ],
                ],
            ]),
        ]);

        $analisar = $this->postJson('/api/solicitacoes/ler-pedido-medico', [
            'arquivo' => UploadedFile::fake()->create('pedido-medico.jpg', 128, 'image/jpeg'),
        ]);

        $analisar->assertOk()
            ->assertJsonPath('data.dados.paciente_nome', 'Ana Paula Ribeiro');

        $this->assertLessThanOrEqual(5, count($analisar->json('data.sugestoes.pacientes')));
        $this->assertSame('Ana Paula Ribeiro', $analisar->json('data.sugestoes.pacientes.0.nome'));

        $uploadId = $analisar->json('data.upload_id');
        Storage::disk('local')->assertExists($uploadId);

        $payload = $this->payloadSolicitacao([
            'pedido_medico_upload_id' => $uploadId,
            'pedido_medico_nome_original' => 'pedido-medico.jpg',
            'pedido_medico_mime' => 'image/jpeg',
            'pedido_medico_ai_result' => $analisar->json('data.dados'),
        ]);

        $create = $this->postJson('/api/solicitacoes', $payload)
            ->assertCreated()
            ->assertJsonPath('data.pedido_medico.nome_original', 'pedido-medico.jpg')
            ->assertJsonPath('data.documentos.0.tipo', 'pedido_medico');

        $id = $create->json('data.id');
        $this->assertDatabaseHas('solicitacao_documentos', [
            'solicitacao_id' => $id,
            'tipo' => 'pedido_medico',
            'nome_original' => 'pedido-medico.jpg',
        ]);

        $this->getJson("/api/solicitacoes/{$id}")
            ->assertOk()
            ->assertJsonPath('data.pedido_medico.mime', 'image/jpeg');

        $this->getJson("/api/solicitacoes/{$id}/pedido-medico")
            ->assertOk();
    }

    public function test_cria_cadastros_rapidos_para_fluxo_de_pedido_medico(): void
    {
        $this->autenticar();
        $convenio = Convenio::query()->where('nome', 'Unimed')->firstOrFail();

        $this->postJson('/api/solicitacoes/pacientes-rapido', [
            'nome' => 'Paciente Novo IA',
            'convenio_id' => $convenio->id,
            'carteirinha' => 'UNI-2026-9999',
        ])
            ->assertCreated()
            ->assertJsonPath('data.nome', 'Paciente Novo IA')
            ->assertJsonPath('data.carteirinha', 'UNI-2026-9999');

        $this->postJson('/api/solicitacoes/pacientes-rapido', [
            'nome' => 'Paciente Sem Carteirinha',
            'convenio_id' => $convenio->id,
        ])->assertStatus(422)->assertJsonValidationErrors('carteirinha');

        $this->postJson('/api/solicitacoes/especialidades-rapido', [
            'nome' => 'Terapia Ocupacional IA',
        ])
            ->assertCreated()
            ->assertJsonPath('data.nome', 'Terapia Ocupacional IA');

        $this->postJson('/api/solicitacoes/medicos-rapido', [
            'nome' => 'Dra. Nova IA',
        ])
            ->assertCreated()
            ->assertJsonPath('data.nome', 'Dra. Nova IA')
            ->assertJsonPath('data.crm', 'PENDENTE')
            ->assertJsonPath('data.especialidade_medica', 'Pendente');
    }

    public function test_cadastro_rapido_de_medico_aceita_crm_e_especialidade(): void
    {
        $this->autenticar();

        $this->postJson('/api/solicitacoes/medicos-rapido', [
            'nome' => 'Dr. Extraido Pela IA',
            'crm' => '12345/SC',
            'especialidade_medica' => 'Pediatria',
        ])
            ->assertCreated()
            ->assertJsonPath('data.nome', 'Dr. Extraido Pela IA')
            ->assertJsonPath('data.crm', '12345/SC')
            ->assertJsonPath('data.especialidade_medica', 'Pediatria');
    }

    public function test_analise_extrai_crm_especialidade_do_medico_e_casa_cid_com_catalogo(): void
    {
        Storage::fake('local');
        $user = $this->autenticar();
        $this->configurarIa($user->tenant_id);
        $cidCatalogado = Cid::query()->where('codigo', 'F84.0')->firstOrFail();

        Http::fake([
            'api.openai.com/v1/responses' => Http::response([
                'output' => [
                    [
                        'content' => [
                            [
                                'type' => 'output_text',
                                'text' => json_encode([
                                    'paciente_nome' => 'Ana Paula Ribeiro',
                                    'medico_nome' => 'Carlos Almeida',
                                    'medico_crm' => '54321/SC',
                                    'medico_especialidade' => 'Neurologia',
                                    'especialidades' => ['Fisioterapia'],
                                    // Descricao ligeiramente diferente do cadastro, so pra
                                    // confirmar que o casamento e por similaridade/codigo,
                                    // nao por igualdade exata de string.
                                    'cids' => ['F84.0 - Autismo infantil (leve)', 'Z99.9 - Sem cadastro parecido'],
                                    'solicitado_em' => '2026-07-22',
                                ]),
                            ],
                        ],
                    ],
                ],
            ]),
        ]);

        $analisar = $this->postJson('/api/solicitacoes/ler-pedido-medico', [
            'arquivo' => UploadedFile::fake()->create('pedido-medico.jpg', 128, 'image/jpeg'),
        ])->assertOk();

        $analisar
            ->assertJsonPath('data.dados.medico_crm', '54321/SC')
            ->assertJsonPath('data.dados.medico_especialidade', 'Neurologia');

        $cids = $analisar->json('data.sugestoes.cids');
        $this->assertCount(2, $cids);

        $lidoComCadastro = collect($cids)->firstWhere('termo', 'F84.0 - Autismo infantil (leve)');
        $this->assertNotNull($lidoComCadastro);
        $this->assertFalse($lidoComCadastro['sugere_cadastro']);
        $this->assertSame($cidCatalogado->id, $lidoComCadastro['matches'][0]['id']);
        // json_encode(100.0) vira "100" (sem casa decimal), e volta como int
        // no decode — assertEquals em vez de assertSame evita falso negativo
        // por causa disso.
        $this->assertEquals(100, $lidoComCadastro['matches'][0]['similaridade']);

        $lidoSemCadastro = collect($cids)->firstWhere('termo', 'Z99.9 - Sem cadastro parecido');
        $this->assertNotNull($lidoSemCadastro);
        $this->assertTrue($lidoSemCadastro['sugere_cadastro']);
    }

    private function autenticar(): User
    {
        $user = User::query()->where('email', 'admin@clinica-exemplo.test')->firstOrFail();
        Sanctum::actingAs($user);

        return $user;
    }

    private function configurarIa(int $tenantId): void
    {
        AiOpenaiSetting::query()->create([
            'tenant_id' => $tenantId,
            'api_key' => 'sk-teste-pedido',
            'base_url' => 'https://api.openai.com/v1',
            'ativo' => true,
        ]);

        AiPromptTemplate::query()->create([
            'tenant_id' => $tenantId,
            'chave' => 'ler_solicitacao_medica',
            'nome' => 'Ler solicitação médica',
            'model_id' => 'gpt-5.6-luna',
            'system_prompt' => 'Responda em JSON.',
            'user_prompt' => 'Leia o pedido médico.',
            'ativo' => true,
        ]);
    }

    private function payloadSolicitacao(array $extra = []): array
    {
        $convenio = Convenio::query()->where('nome', 'Unimed')->firstOrFail();
        $especialidade = Especialidade::query()->where('nome', 'Fisioterapia')->firstOrFail();
        $profissional = Profissional::query()->where('especialidade_id', $especialidade->id)->firstOrFail();
        $paciente = Paciente::query()->where('convenio_id', $convenio->id)->firstOrFail();
        $medico = Medico::query()->where('nome', 'Dr. Carlos Almeida')->firstOrFail();

        return [
            'paciente_id' => $paciente->id,
            'profissional_id' => $profissional->id,
            'especialidade_id' => $especialidade->id,
            'convenio_id' => $convenio->id,
            'medico_id' => $medico->id,
            'cid_ids' => [Cid::query()->where('codigo', 'F84.0')->firstOrFail()->id],
            'solicitado_em' => '2026-07-22',
            'observacoes' => 'Pedido com assinatura ilegível.',
        ] + $extra;
    }
}
