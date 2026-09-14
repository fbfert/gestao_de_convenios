<?php

namespace Tests\Feature;

use App\Models\Cid;
use App\Models\Convenio;
use App\Models\Especialidade;
use App\Models\Medico;
use App\Models\Paciente;
use App\Models\PacienteArquivo;
use App\Models\Profissional;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Anexos e sessão: o que o cliente manda não decide onde o arquivo está, nem
 * como o navegador vai tratá-lo, nem por quanto tempo o crachá vale.
 */
class AnexosESessaoSegurancaApiTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    /**
     * O `upload_id` vem do cliente. A checagem era `str_starts_with` sobre a
     * string crua, e prefixo literal cai com um `../`: o Flysystem colapsa o
     * `..` ao verificar existência, enquanto `Storage::path()` entrega a string
     * sem normalizar na hora de servir.
     */
    public function test_recusa_upload_id_com_travessia_de_caminho(): void
    {
        $user = $this->autenticar();
        $tenantId = (int) $user->tenant_id;

        Storage::disk('local')->put('pedidos-medicos/pendentes/'.$tenantId.'/valido.pdf', 'conteudo');
        Storage::disk('local')->put('alvo-de-outra-pasta.pdf', 'segredo');

        $solicitacao = $this->criarSolicitacao(
            "pedidos-medicos/pendentes/{$tenantId}/../../../alvo-de-outra-pasta.pdf"
        );

        // A solicitação é criada, mas SEM anexo: o caminho foi recusado.
        $solicitacao->assertCreated();
        $this->assertCount(0, $solicitacao->json('data.documentos') ?? []);

        // E o arquivo visado continua onde estava, intacto.
        $this->assertTrue(Storage::disk('local')->exists('alvo-de-outra-pasta.pdf'));
        $this->assertSame('segredo', Storage::disk('local')->get('alvo-de-outra-pasta.pdf'));
    }

    public function test_recusa_upload_id_com_subpasta(): void
    {
        $user = $this->autenticar();
        $tenantId = (int) $user->tenant_id;

        Storage::disk('local')->put("pedidos-medicos/pendentes/{$tenantId}/sub/arquivo.pdf", 'conteudo');

        $solicitacao = $this->criarSolicitacao("pedidos-medicos/pendentes/{$tenantId}/sub/arquivo.pdf");

        $solicitacao->assertCreated();
        $this->assertCount(0, $solicitacao->json('data.documentos') ?? []);
    }

    /**
     * O MIME voltava cru no `Content-Type` do download. O front abre o anexo com
     * `URL.createObjectURL`, e a URL `blob:` herda a origem do SPA — onde o
     * token de sessão vive no `localStorage`. Declarar `text/html` num anexo
     * fazia o arquivo rodar como página nessa origem.
     */
    public function test_mime_do_anexo_vem_do_arquivo_e_nao_do_que_o_cliente_declara(): void
    {
        $this->autenticar();
        $paciente = Paciente::query()->firstOrFail();

        $resposta = $this->postJson("/api/pacientes/{$paciente->id}/arquivos", [
            'tipo' => 'pedido_medico',
            'arquivo' => UploadedFile::fake()->create('inocente.pdf', 32, 'application/pdf'),
        ])->assertCreated();

        $arquivo = PacienteArquivo::query()->findOrFail($resposta->json('data.id'));

        // O MIME gravado e o do arquivo no disco, e nao o que veio no header
        // multipart. Nao da para forjar o header com `UploadedFile::fake()`
        // sem que a regra `mimes` reprove antes — entao o que se afirma aqui e
        // a propriedade que a correcao garante: o valor vem do disco.
        $this->assertSame(
            Storage::disk('local')->mimeType($arquivo->path),
            $arquivo->mime,
        );

        $download = $this->get("/api/pacientes/{$paciente->id}/arquivos/{$arquivo->id}")->assertOk();
        $this->assertNotSame('text/html', $download->headers->get('Content-Type'));
        $this->assertSame('nosniff', $download->headers->get('X-Content-Type-Options'));
        $this->assertStringStartsWith('attachment;', (string) $download->headers->get('Content-Disposition'));
    }

    /**
     * `ativo` só era conferido no login, e nada apagava os tokens ao desativar
     * alguém — o crachá continuava abrindo a porta. Com `sessao_minutos = 0`,
     * que desliga a expiração, valia para sempre.
     */
    public function test_usuario_desativado_perde_o_acesso_na_requisicao_seguinte(): void
    {
        $user = User::query()->where('email', 'admin@clinica-exemplo.test')->firstOrFail();
        $token = $user->createToken('teste')->plainTextToken;

        $this->withToken($token)->getJson('/api/user')->assertOk();

        $user->forceFill(['ativo' => false])->save();

        /*
         * Esquece o guard entre as duas requisições. Dentro de um teste o
         * Laravel reaproveita o mesmo container, e o guard guarda o usuário já
         * resolvido — sem isto a segunda requisição enxergaria a instância da
         * primeira, ainda ativa, e o teste passaria a medir o cache do harness
         * em vez do middleware. Em produção cada requisição relê do banco.
         */
        $this->app['auth']->forgetGuards();

        $this->withToken($token)->getJson('/api/user')->assertUnauthorized();

        // Revogado, e não só recusado: reativar a conta não pode ressuscitar o
        // token que estava na mão de quem saiu.
        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_desativar_pela_api_revoga_o_token_na_hora(): void
    {
        $this->autenticar();

        $alvo = User::query()->where('email', 'funcionario@clinica-exemplo.test')->firstOrFail();
        $alvo->createToken('sessao-aberta');
        $this->assertSame(1, $alvo->tokens()->count());

        $this->patchJson("/api/usuarios/{$alvo->id}", ['ativo' => false])->assertOk();

        $this->assertSame(0, $alvo->fresh()->tokens()->count());
    }

    public function test_trocar_a_senha_derruba_as_sessoes_abertas(): void
    {
        $this->autenticar();

        $alvo = User::query()->where('email', 'funcionario@clinica-exemplo.test')->firstOrFail();
        $alvo->createToken('sessao-aberta');

        $this->patchJson("/api/usuarios/{$alvo->id}", ['password' => 'outra-senha-forte'])->assertOk();

        $this->assertSame(0, $alvo->fresh()->tokens()->count());
    }

    /** O profissional não tem `solicitacoes.view`: CID é diagnóstico. */
    public function test_profissional_nao_le_solicitacoes(): void
    {
        $user = User::query()->where('email', 'profissional@clinica-exemplo.test')->firstOrFail();
        Sanctum::actingAs($user);

        $this->getJson('/api/solicitacoes')->assertForbidden();
    }

    private function autenticar(): User
    {
        $user = User::query()->where('email', 'admin@clinica-exemplo.test')->firstOrFail();
        Sanctum::actingAs($user);

        return $user;
    }

    private function criarSolicitacao(string $uploadId): TestResponse
    {
        $convenio = Convenio::query()->where('nome', 'Unimed')->firstOrFail();
        $paciente = Paciente::query()->where('convenio_id', $convenio->id)->firstOrFail();
        $medico = Medico::query()->firstOrFail();
        $especialidade = Especialidade::query()->firstOrFail();
        $profissional = Profissional::query()
            ->where('especialidade_id', $especialidade->id)->firstOrFail();

        return $this->postJson('/api/solicitacoes', [
            'paciente_id' => $paciente->id,
            'convenio_id' => $convenio->id,
            'medico_id' => $medico->id,
            'cid_ids' => [Cid::query()->firstOrFail()->id],
            'solicitado_em' => today()->toDateString(),
            'itens' => [[
                'especialidade_id' => $especialidade->id,
                'profissional_id' => $profissional->id,
                'quantidade' => 10,
            ]],
            'pedido_medico_upload_id' => $uploadId,
            'pedido_medico_nome_original' => 'pedido.pdf',
        ]);
    }
}
