<?php

namespace Tests\Feature;

use App\Models\Convenio;
use App\Models\Guia;
use App\Models\Paciente;
use App\Models\PacienteArquivo;
use App\Models\Solicitacao;
use App\Models\SolicitacaoDocumento;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PacienteArquivoApiTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_upload_solto_lista_baixa_e_exclui_sem_vinculo(): void
    {
        Storage::fake('local');
        $this->autenticar();
        $paciente = $this->paciente();

        $upload = $this->postJson("/api/pacientes/{$paciente->id}/arquivos", [
            'tipo' => 'laudo_medico',
            'arquivo' => UploadedFile::fake()->create('laudo.pdf', 32, 'application/pdf'),
        ])->assertCreated();

        $this->assertSame('laudo_medico', $upload->json('data.tipo'));
        $this->assertSame([], $upload->json('data.vinculos'));
        $arquivoId = $upload->json('data.id');

        $lista = $this->getJson("/api/pacientes/{$paciente->id}/arquivos")->assertOk();
        $this->assertCount(1, $lista->json('data'));

        $this->getJson("/api/pacientes/{$paciente->id}/arquivos/{$arquivoId}")->assertOk();

        $this->deleteJson("/api/pacientes/{$paciente->id}/arquivos/{$arquivoId}")->assertStatus(204);
        $this->assertDatabaseMissing('paciente_arquivos', ['id' => $arquivoId]);
    }

    public function test_recusa_exclusao_de_arquivo_vinculado_a_solicitacao(): void
    {
        Storage::fake('local');
        $this->autenticar();
        $paciente = $this->paciente();
        $arquivo = PacienteArquivo::query()->create([
            'tenant_id' => $paciente->tenant_id,
            'paciente_id' => $paciente->id,
            'tipo' => 'laudo_medico',
            'nome_original' => 'laudo.pdf',
            'mime' => 'application/pdf',
            'path' => 'pacientes/teste.pdf',
        ]);
        $solicitacao = $this->solicitacaoDoPaciente($paciente);

        $solicitacao->documentos()->create([
            'tenant_id' => $paciente->tenant_id,
            'solicitacao_item_id' => null,
            'paciente_arquivo_id' => $arquivo->id,
        ]);

        $this->deleteJson("/api/pacientes/{$paciente->id}/arquivos/{$arquivo->id}")
            ->assertStatus(422)
            ->assertJsonValidationErrors('arquivo');

        $this->assertDatabaseHas('paciente_arquivos', ['id' => $arquivo->id]);
    }

    public function test_arquivo_da_pasta_pode_ser_vinculado_a_mais_de_uma_solicitacao(): void
    {
        Storage::fake('local');
        $this->autenticar();
        $paciente = $this->paciente();
        $arquivo = PacienteArquivo::query()->create([
            'tenant_id' => $paciente->tenant_id,
            'paciente_id' => $paciente->id,
            'tipo' => 'laudo_medico',
            'nome_original' => 'laudo.pdf',
            'mime' => 'application/pdf',
            'path' => 'pacientes/teste.pdf',
        ]);

        $solicitacaoA = $this->solicitacaoDoPaciente($paciente);
        $solicitacaoB = $this->solicitacaoDoPaciente($paciente);

        $vinculoA = $this->postJson("/api/solicitacoes/{$solicitacaoA->id}/documentos/vincular", [
            'paciente_arquivo_id' => $arquivo->id,
        ])->assertCreated();

        $this->postJson("/api/solicitacoes/{$solicitacaoB->id}/documentos/vincular", [
            'paciente_arquivo_id' => $arquivo->id,
        ])->assertCreated();

        $documentoIdA = collect($vinculoA->json('data.documentos'))->first()['id'];

        // A guia gerada na solicitação A trava só o vínculo dela.
        Guia::query()->create([
            'tenant_id' => $solicitacaoA->tenant_id,
            'solicitacao_id' => $solicitacaoA->id,
            'solicitacao_item_id' => $solicitacaoA->itens()->value('id'),
            'convenio_id' => $solicitacaoA->convenio_id,
            'paciente_id' => $solicitacaoA->paciente_id,
            'profissional_id' => $solicitacaoA->profissional_id,
            'especialidade_id' => $solicitacaoA->especialidade_id,
            'numero_guia' => 'GUIA-COMPARTILHADO-A',
            'tipo_terapia' => 'especializada',
            'status' => 'under_review',
            'data_solicitacao' => today(),
        ]);

        $this->deleteJson("/api/solicitacoes/{$solicitacaoA->id}/documentos/{$documentoIdA}")
            ->assertStatus(422);

        $vinculoBId = SolicitacaoDocumento::query()
            ->where('solicitacao_id', $solicitacaoB->id)
            ->where('paciente_arquivo_id', $arquivo->id)
            ->value('id');

        $this->deleteJson("/api/solicitacoes/{$solicitacaoB->id}/documentos/{$vinculoBId}")
            ->assertOk();

        $this->assertDatabaseHas('paciente_arquivos', ['id' => $arquivo->id]);
        $this->assertDatabaseHas('solicitacao_documentos', ['id' => $documentoIdA]);
        $this->assertDatabaseMissing('solicitacao_documentos', ['id' => $vinculoBId]);

        // O arquivo nunca poderá ser excluído da pasta enquanto A existir —
        // comportamento permanente e esperado, não um bug.
        $this->deleteJson("/api/pacientes/{$paciente->id}/arquivos/{$arquivo->id}")
            ->assertStatus(422);
    }

    public function test_contexto_de_arquivo_nunca_usado_vem_vazio(): void
    {
        Storage::fake('local');
        $this->autenticar();
        $paciente = $this->paciente();
        $arquivo = PacienteArquivo::query()->create([
            'tenant_id' => $paciente->tenant_id,
            'paciente_id' => $paciente->id,
            'tipo' => 'pedido_medico',
            'nome_original' => 'pedido.pdf',
            'mime' => 'application/pdf',
            'path' => 'pacientes/pedido.pdf',
        ]);

        $resposta = $this->getJson("/api/pacientes/{$paciente->id}/arquivos/{$arquivo->id}/contexto")
            ->assertOk()
            ->json('data');

        $this->assertNull($resposta['solicitacao_id']);
        $this->assertNull($resposta['medico']);
        $this->assertSame([], $resposta['cid_ids']);
        $this->assertSame([], $resposta['itens']);
    }

    public function test_contexto_traz_medico_cid_e_itens_da_solicitacao_mais_recente(): void
    {
        Storage::fake('local');
        $this->autenticar();
        $paciente = $this->paciente();
        $arquivo = PacienteArquivo::query()->create([
            'tenant_id' => $paciente->tenant_id,
            'paciente_id' => $paciente->id,
            'tipo' => 'pedido_medico',
            'nome_original' => 'pedido.pdf',
            'mime' => 'application/pdf',
            'path' => 'pacientes/pedido.pdf',
        ]);

        $antiga = $this->solicitacaoDoPaciente($paciente);
        $antiga->documentos()->create([
            'tenant_id' => $paciente->tenant_id,
            'solicitacao_item_id' => null,
            'paciente_arquivo_id' => $arquivo->id,
        ]);
        $antiga->cidCadastros()->sync([\App\Models\Cid::query()->firstOrFail()->id]);

        // Segunda solicitação, médico diferente: o contexto tem que trazer
        // ESTA, não a mais antiga.
        $recente = $this->solicitacaoDoPaciente($paciente);
        $outroMedico = \App\Models\Medico::query()->where('id', '!=', $recente->medico_id)->first()
            ?? \App\Models\Medico::factory()->create(['tenant_id' => $paciente->tenant_id]);
        $recente->update(['medico_id' => $outroMedico->id]);
        $recente->documentos()->create([
            'tenant_id' => $paciente->tenant_id,
            'solicitacao_item_id' => null,
            'paciente_arquivo_id' => $arquivo->id,
        ]);
        $cidRecente = \App\Models\Cid::query()->where('id', '!=', \App\Models\Cid::query()->firstOrFail()->id)->first()
            ?? \App\Models\Cid::factory()->create(['tenant_id' => $paciente->tenant_id]);
        $recente->cidCadastros()->sync([$cidRecente->id]);

        $resposta = $this->getJson("/api/pacientes/{$paciente->id}/arquivos/{$arquivo->id}/contexto")
            ->assertOk()
            ->json('data');

        $this->assertSame($recente->id, $resposta['solicitacao_id']);
        $this->assertSame($outroMedico->id, $resposta['medico']['id']);
        $this->assertSame([$cidRecente->id], $resposta['cid_ids']);
        $this->assertCount(1, $resposta['itens']);
        $this->assertSame($recente->especialidade_id, $resposta['itens'][0]['especialidade_id']);
        $this->assertSame($recente->profissional_id, $resposta['itens'][0]['profissional_id']);
    }

    private function autenticar(): User
    {
        $user = User::query()->where('email', 'admin@clinica-exemplo.test')->firstOrFail();
        Sanctum::actingAs($user);

        return $user;
    }

    private function paciente(): Paciente
    {
        $convenio = Convenio::query()->where('nome', 'Unimed')->firstOrFail();

        return Paciente::query()->where('convenio_id', $convenio->id)->firstOrFail();
    }

    private function solicitacaoDoPaciente(Paciente $paciente): Solicitacao
    {
        $especialidade = \App\Models\Especialidade::query()->orderBy('id')->firstOrFail();
        $profissional = \App\Models\Profissional::query()->where('especialidade_id', $especialidade->id)->firstOrFail();
        $medico = \App\Models\Medico::query()->firstOrFail();

        $solicitacao = Solicitacao::query()->create([
            'tenant_id' => $paciente->tenant_id,
            'paciente_id' => $paciente->id,
            'profissional_id' => $profissional->id,
            'especialidade_id' => $especialidade->id,
            'convenio_id' => $paciente->convenio_id,
            'medico_id' => $medico->id,
            'status' => 'under_review',
            'solicitado_em' => today(),
        ]);

        $solicitacao->itens()->create([
            'tenant_id' => $solicitacao->tenant_id,
            'especialidade_id' => $especialidade->id,
            'profissional_id' => $profissional->id,
            'quantidade' => 10,
            'status_operacional' => 'pending',
        ]);

        return $solicitacao;
    }
}
