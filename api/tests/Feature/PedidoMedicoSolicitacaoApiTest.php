<?php

namespace Tests\Feature;

use App\Models\AiOpenaiSetting;
use App\Models\AiPromptTemplate;
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
            ->assertJsonPath('data.pedido_medico.nome_original', 'pedido-medico.jpg');

        $id = $create->json('data.id');
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
        ])
            ->assertCreated()
            ->assertJsonPath('data.nome', 'Paciente Novo IA');

        $this->postJson('/api/solicitacoes/especialidades-rapido', [
            'nome' => 'Terapia Ocupacional IA',
        ])
            ->assertCreated()
            ->assertJsonPath('data.nome', 'Terapia Ocupacional IA');

        $this->postJson('/api/solicitacoes/medicos-rapido', [
            'nome' => 'Dra. Nova IA',
        ])
            ->assertCreated()
            ->assertJsonPath('data.nome', 'Dra. Nova IA');
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
            'solicitado_em' => '2026-07-22',
            'observacoes' => 'Pedido com assinatura ilegível.',
        ] + $extra;
    }
}
