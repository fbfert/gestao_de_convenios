<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\NovidadeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NovidadesApiTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    /** @var array<int, string> arquivos criados pelo teste, para limpar depois */
    private array $temporarios = [];

    protected function setUp(): void
    {
        parent::setUp();

        app(NovidadeService::class)->esquecerCache();
    }

    protected function tearDown(): void
    {
        foreach ($this->temporarios as $caminho) {
            if (is_file($caminho)) {
                unlink($caminho);
            }
        }

        app(NovidadeService::class)->esquecerCache();

        parent::tearDown();
    }

    private function autenticar(string $email = 'admin@clinica-exemplo.test'): User
    {
        $user = User::query()->where('email', $email)->firstOrFail();
        Sanctum::actingAs($user);

        return $user;
    }

    private function criarNovidade(string $nome, string $conteudo): void
    {
        $caminho = resource_path('novidades/'.$nome.'.md');
        file_put_contents($caminho, $conteudo);
        $this->temporarios[] = $caminho;
        app(NovidadeService::class)->esquecerCache();
    }

    public function test_lista_da_mais_recente_para_a_mais_antiga(): void
    {
        $this->autenticar();
        $this->criarNovidade('2020-01-01-antiga', "---\ntitulo: Antiga\ntipo: aviso\ndata: 2020-01-01\n---\ncorpo");
        $this->criarNovidade('2099-01-01-futura', "---\ntitulo: Futura\ntipo: aviso\ndata: 2099-01-01\n---\ncorpo");

        $lista = $this->getJson('/api/novidades')->assertOk()->json('data');

        $this->assertSame('Futura', $lista[0]['titulo']);
        $this->assertSame('Antiga', $lista[count($lista) - 1]['titulo']);
    }

    public function test_limite_corta_a_listagem(): void
    {
        $this->autenticar();
        $this->criarNovidade('2098-01-01-a', "---\ntitulo: A\ntipo: aviso\ndata: 2098-01-01\n---\nx");
        $this->criarNovidade('2098-01-02-b', "---\ntitulo: B\ntipo: aviso\ndata: 2098-01-02\n---\nx");

        $this->assertCount(1, $this->getJson('/api/novidades?limit=1')->assertOk()->json('data'));
    }

    public function test_arquivo_sem_frontmatter_valido_e_ignorado_sem_quebrar(): void
    {
        $this->autenticar();
        $this->criarNovidade('2097-01-01-quebrada', "sem frontmatter nenhum\n");
        $this->criarNovidade('2097-01-02-boa', "---\ntitulo: Boa\ntipo: melhoria\ndata: 2097-01-02\n---\nx");

        // Um arquivo mal escrito não pode derrubar o dashboard inteiro.
        $lista = $this->getJson('/api/novidades')->assertOk()->json('data');

        $slugs = collect($lista)->pluck('slug')->all();
        $this->assertContains('2097-01-02-boa', $slugs);
        $this->assertNotContains('2097-01-01-quebrada', $slugs);
    }

    public function test_tipo_invalido_tambem_e_ignorado(): void
    {
        $this->autenticar();
        $this->criarNovidade('2096-01-01-tipo', "---\ntitulo: X\ntipo: inventado\ndata: 2096-01-01\n---\nx");

        $slugs = collect($this->getJson('/api/novidades')->json('data'))->pluck('slug')->all();

        $this->assertNotContains('2096-01-01-tipo', $slugs);
    }

    public function test_marcar_como_lida_reduz_a_contagem_de_nao_lidas(): void
    {
        $this->autenticar();
        $this->criarNovidade('2095-01-01-lida', "---\ntitulo: Para ler\ntipo: aviso\ndata: 2095-01-01\n---\nx");

        $antes = $this->getJson('/api/novidades')->assertOk()->json('meta.nao_lidas');

        $this->postJson('/api/novidades/2095-01-01-lida/lida')->assertOk();

        $depois = $this->getJson('/api/novidades')->assertOk()->json('meta.nao_lidas');

        $this->assertSame($antes - 1, $depois);
    }

    public function test_marcar_de_novo_nao_duplica(): void
    {
        $this->autenticar();
        $this->criarNovidade('2094-01-01-dupla', "---\ntitulo: Dupla\ntipo: aviso\ndata: 2094-01-01\n---\nx");

        $this->postJson('/api/novidades/2094-01-01-dupla/lida')->assertOk();
        $this->postJson('/api/novidades/2094-01-01-dupla/lida')->assertOk();

        $this->assertDatabaseCount('novidade_leituras', 1);
    }

    public function test_leitura_e_por_usuario_e_nao_por_tenant(): void
    {
        $this->autenticar();
        $this->criarNovidade('2093-01-01-user', "---\ntitulo: User\ntipo: aviso\ndata: 2093-01-01\n---\nx");

        $this->postJson('/api/novidades/2093-01-01-user/lida')->assertOk();

        // Outro usuário do MESMO tenant continua com ela por ler.
        $this->autenticar('funcionario@clinica-exemplo.test');

        $lista = collect($this->getJson('/api/novidades')->assertOk()->json('data'));
        $novidade = $lista->firstWhere('slug', '2093-01-01-user');

        $this->assertFalse($novidade['lida']);
    }

    public function test_slug_inexistente_retorna_404(): void
    {
        $this->autenticar();

        $this->postJson('/api/novidades/nao-existe/lida')->assertNotFound();
    }
}
