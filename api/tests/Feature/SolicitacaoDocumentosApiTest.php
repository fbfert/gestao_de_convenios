<?php

namespace Tests\Feature;

use App\Models\Cid;
use App\Models\Convenio;
use App\Models\Especialidade;
use App\Models\Guia;
use App\Models\Medico;
use App\Models\Paciente;
use App\Models\Profissional;
use App\Models\Solicitacao;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SolicitacaoDocumentosApiTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_cria_solicitacao_com_varias_especialidades_e_profissional_por_item(): void
    {
        $this->autenticar();
        $itens = $this->itens();

        $response = $this->postJson('/api/solicitacoes', $this->payload([
            'itens' => [
                ['especialidade_id' => $itens[0]['especialidade_id'], 'profissional_id' => $itens[0]['profissional_id'], 'quantidade' => 12],
                ['especialidade_id' => $itens[1]['especialidade_id'], 'profissional_id' => $itens[1]['profissional_id']],
            ],
        ]))->assertCreated();

        $response->assertJsonCount(2, 'data.itens');
        $this->assertSame(12, $response->json('data.itens.0.quantidade'));
        $this->assertSame(10, $response->json('data.itens.1.quantidade'), 'Item sem quantidade usa o padrão 10.');
        $this->assertSame($itens[0]['profissional_id'], $response->json('data.itens.0.profissional_id'));
        $this->assertSame($itens[1]['profissional_id'], $response->json('data.itens.1.profissional_id'));
    }

    public function test_anexa_laudo_na_solicitacao_e_documentos_por_especialidade(): void
    {
        Storage::fake('local');
        $this->autenticar();
        $solicitacao = $this->criarSolicitacaoComDoisItens();
        $itemId = $solicitacao->itens()->orderBy('id')->value('id');

        $laudo = $this->postJson("/api/solicitacoes/{$solicitacao->id}/documentos", [
            'tipo' => 'laudo_medico',
            'arquivo' => UploadedFile::fake()->create('laudo.pdf', 64, 'application/pdf'),
        ])->assertCreated();

        $this->assertSame('laudo_medico', $laudo->json('data.documentos.0.tipo'));
        $this->assertNull($laudo->json('data.documentos.0.solicitacao_item_id'));

        $plano = $this->postJson("/api/solicitacoes/{$solicitacao->id}/documentos", [
            'tipo' => 'plano_individualizado',
            'solicitacao_item_id' => $itemId,
            'arquivo' => UploadedFile::fake()->create('plano.png', 32, 'image/png'),
        ])->assertCreated();

        $this->postJson("/api/solicitacoes/{$solicitacao->id}/documentos", [
            'tipo' => 'relatorio_evolucao',
            'solicitacao_item_id' => $itemId,
            'arquivo' => UploadedFile::fake()->create('evolucao.pdf', 32, 'application/pdf'),
        ])->assertCreated();

        $this->assertDatabaseHas('solicitacao_documentos', [
            'solicitacao_id' => $solicitacao->id,
            'solicitacao_item_id' => $itemId,
            'tipo' => 'plano_individualizado',
        ]);

        $documentoId = collect($plano->json('data.documentos'))
            ->firstWhere('tipo', 'plano_individualizado')['id'];

        $this->getJson("/api/solicitacoes/{$solicitacao->id}/documentos/{$documentoId}")->assertOk();

        $this->deleteJson("/api/solicitacoes/{$solicitacao->id}/documentos/{$documentoId}")->assertOk();
        $this->assertDatabaseMissing('solicitacao_documentos', ['id' => $documentoId]);
    }

    public function test_recusa_documento_por_item_sem_especialidade_vinculada(): void
    {
        Storage::fake('local');
        $this->autenticar();
        $solicitacao = $this->criarSolicitacaoComDoisItens();

        $this->postJson("/api/solicitacoes/{$solicitacao->id}/documentos", [
            'tipo' => 'plano_individualizado',
            'arquivo' => UploadedFile::fake()->create('plano.pdf', 32, 'application/pdf'),
        ])->assertStatus(422)->assertJsonValidationErrors('solicitacao_item_id');

        $this->postJson("/api/solicitacoes/{$solicitacao->id}/documentos", [
            'tipo' => 'laudo_medico',
            'solicitacao_item_id' => $solicitacao->itens()->value('id'),
            'arquivo' => UploadedFile::fake()->create('laudo.pdf', 32, 'application/pdf'),
        ])->assertStatus(422)->assertJsonValidationErrors('solicitacao_item_id');
    }

    public function test_recusa_item_de_outra_solicitacao(): void
    {
        Storage::fake('local');
        $this->autenticar();
        $solicitacao = $this->criarSolicitacaoComDoisItens();
        $outra = $this->criarSolicitacaoComDoisItens();

        $this->postJson("/api/solicitacoes/{$solicitacao->id}/documentos", [
            'tipo' => 'relatorio_evolucao',
            'solicitacao_item_id' => $outra->itens()->value('id'),
            'arquivo' => UploadedFile::fake()->create('evolucao.pdf', 32, 'application/pdf'),
        ])->assertStatus(422)->assertJsonValidationErrors('solicitacao_item_id');
    }

    public function test_recusa_segundo_pedido_medico_e_libera_apos_remover(): void
    {
        Storage::fake('local');
        $this->autenticar();
        $solicitacao = $this->criarSolicitacaoComDoisItens();

        $primeiro = $this->postJson("/api/solicitacoes/{$solicitacao->id}/documentos", [
            'tipo' => 'pedido_medico',
            'arquivo' => UploadedFile::fake()->create('pedido.pdf', 32, 'application/pdf'),
        ])->assertCreated();

        $this->assertSame('pedido.pdf', $primeiro->json('data.pedido_medico.nome_original'));

        $this->postJson("/api/solicitacoes/{$solicitacao->id}/documentos", [
            'tipo' => 'pedido_medico',
            'arquivo' => UploadedFile::fake()->create('outro.pdf', 32, 'application/pdf'),
        ])->assertStatus(422);

        $documentoId = collect($primeiro->json('data.documentos'))
            ->firstWhere('tipo', 'pedido_medico')['id'];

        $remocao = $this->deleteJson("/api/solicitacoes/{$solicitacao->id}/documentos/{$documentoId}")
            ->assertOk();

        $this->assertNull($remocao->json('data.pedido_medico'));

        $this->postJson("/api/solicitacoes/{$solicitacao->id}/documentos", [
            'tipo' => 'pedido_medico',
            'arquivo' => UploadedFile::fake()->create('outro.pdf', 32, 'application/pdf'),
        ])->assertCreated();
    }

    public function test_recusa_anexo_fora_do_formato_ou_acima_de_cinco_megas(): void
    {
        Storage::fake('local');
        $this->autenticar();
        $solicitacao = $this->criarSolicitacaoComDoisItens();

        $this->postJson("/api/solicitacoes/{$solicitacao->id}/documentos", [
            'tipo' => 'laudo_medico',
            'arquivo' => UploadedFile::fake()->create('laudo.txt', 10, 'text/plain'),
        ])->assertStatus(422)->assertJsonValidationErrors('arquivo');

        $this->postJson("/api/solicitacoes/{$solicitacao->id}/documentos", [
            'tipo' => 'laudo_medico',
            'arquivo' => UploadedFile::fake()->create('laudo.pdf', 6000, 'application/pdf'),
        ])->assertStatus(422)->assertJsonValidationErrors('arquivo');
    }

    public function test_bloqueia_remocao_de_anexo_depois_da_guia_gerada(): void
    {
        Storage::fake('local');
        $this->autenticar();
        $solicitacao = $this->criarSolicitacaoComDoisItens();
        $itens = $solicitacao->itens()->orderBy('id')->get();

        $pedido = $this->postJson("/api/solicitacoes/{$solicitacao->id}/documentos", [
            'tipo' => 'pedido_medico',
            'arquivo' => UploadedFile::fake()->create('pedido.pdf', 32, 'application/pdf'),
        ])->assertCreated();

        $plano = $this->postJson("/api/solicitacoes/{$solicitacao->id}/documentos", [
            'tipo' => 'plano_individualizado',
            'solicitacao_item_id' => $itens[0]->id,
            'arquivo' => UploadedFile::fake()->create('plano-a.pdf', 32, 'application/pdf'),
        ])->assertCreated();
        $planoOutro = $this->postJson("/api/solicitacoes/{$solicitacao->id}/documentos", [
            'tipo' => 'plano_individualizado',
            'solicitacao_item_id' => $itens[1]->id,
            'arquivo' => UploadedFile::fake()->create('plano-b.pdf', 32, 'application/pdf'),
        ])->assertCreated();

        $idPorNome = fn (array $documentos, string $nome) => collect($documentos)
            ->firstWhere('nome_original', $nome)['id'];

        Guia::query()->create([
            'tenant_id' => $solicitacao->tenant_id,
            'solicitacao_id' => $solicitacao->id,
            'solicitacao_item_id' => $itens[0]->id,
            'convenio_id' => $solicitacao->convenio_id,
            'paciente_id' => $solicitacao->paciente_id,
            'profissional_id' => $itens[0]->profissional_id,
            'especialidade_id' => $itens[0]->especialidade_id,
            'numero_guia' => 'GUIA-TRAVA-1',
            'tipo_terapia' => 'especializada',
            'status' => 'under_review',
            'data_solicitacao' => today(),
        ]);

        // Anexo da especialidade que já virou guia: travado.
        $this->deleteJson("/api/solicitacoes/{$solicitacao->id}/documentos/".$idPorNome($plano->json('data.documentos'), 'plano-a.pdf'))
            ->assertStatus(422)
            ->assertJsonValidationErrors('documento');

        // Anexo do pedido inteiro: travado assim que qualquer item tem guia.
        $this->deleteJson("/api/solicitacoes/{$solicitacao->id}/documentos/".$idPorNome($pedido->json('data.documentos'), 'pedido.pdf'))
            ->assertStatus(422)
            ->assertJsonValidationErrors('documento');

        // A especialidade sem guia continua editável.
        $this->deleteJson("/api/solicitacoes/{$solicitacao->id}/documentos/".$idPorNome($planoOutro->json('data.documentos'), 'plano-b.pdf'))
            ->assertOk();
    }

    public function test_cadastro_rapido_exige_carteirinha_valida_para_convenio_unimed(): void
    {
        $this->autenticar();
        $convenio = Convenio::query()->where('nome', 'Unimed')->firstOrFail();
        $convenio->update(['connector_type' => 'scraping', 'connector_driver' => 'unimed_rda']);

        $this->postJson('/api/solicitacoes/pacientes-rapido', [
            'nome' => 'Paciente Sem Carteirinha',
            'convenio_id' => $convenio->id,
        ])->assertStatus(422)->assertJsonValidationErrors('carteirinha');

        $this->postJson('/api/solicitacoes/pacientes-rapido', [
            'nome' => 'Paciente Carteirinha Curta',
            'convenio_id' => $convenio->id,
            'carteirinha' => '123',
        ])->assertStatus(422)->assertJsonValidationErrors('carteirinha');

        $this->postJson('/api/solicitacoes/pacientes-rapido', [
            'nome' => 'Paciente Carteirinha Ok',
            'convenio_id' => $convenio->id,
            'carteirinha' => '0012 3456 789012 34 5',
        ])->assertCreated()->assertJsonPath('data.carteirinha', '00123456789012345');

        $this->assertDatabaseMissing('pacientes', ['nome' => 'Paciente Sem Carteirinha']);
    }

    private function criarSolicitacaoComDoisItens(): Solicitacao
    {
        $itens = $this->itens();

        $id = $this->postJson('/api/solicitacoes', $this->payload([
            'itens' => [
                ['especialidade_id' => $itens[0]['especialidade_id'], 'profissional_id' => $itens[0]['profissional_id']],
                ['especialidade_id' => $itens[1]['especialidade_id'], 'profissional_id' => $itens[1]['profissional_id']],
            ],
        ]))->assertCreated()->json('data.id');

        return Solicitacao::query()->findOrFail($id);
    }

    /** @return array<int, array{especialidade_id: int, profissional_id: int}> */
    private function itens(): array
    {
        return Especialidade::query()
            ->orderBy('id')
            ->get()
            ->map(fn (Especialidade $especialidade) => [
                'especialidade_id' => $especialidade->id,
                'profissional_id' => Profissional::query()
                    ->where('especialidade_id', $especialidade->id)
                    ->value('id'),
            ])
            ->filter(fn (array $item) => $item['profissional_id'] !== null)
            ->values()
            ->all();
    }

    private function payload(array $extra = []): array
    {
        $convenio = Convenio::query()->where('nome', 'Unimed')->firstOrFail();
        $paciente = Paciente::query()->where('convenio_id', $convenio->id)->firstOrFail();
        $medico = Medico::query()->firstOrFail();

        return [
            'paciente_id' => $paciente->id,
            'convenio_id' => $convenio->id,
            'medico_id' => $medico->id,
            'cid_ids' => [Cid::query()->where('codigo', 'F84.0')->firstOrFail()->id],
            'solicitado_em' => '2026-08-06',
        ] + $extra;
    }

    private function autenticar(): User
    {
        $user = User::query()->where('email', 'admin@clinica-exemplo.test')->firstOrFail();
        Sanctum::actingAs($user);

        return $user;
    }
}
