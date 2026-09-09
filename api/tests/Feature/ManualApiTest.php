<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * O manual virou conteúdo do PRODUTO: servido de arquivo versionado, igual para
 * todos os tenants e sem edição pela interface.
 *
 * Os testes de edição e de isolamento por tenant que existiam aqui foram
 * removidos porque testavam comportamento que deixou de existir — não porque
 * passaram a falhar.
 */
class ManualApiTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private function autenticar(string $email = 'admin@clinica-exemplo.test'): User
    {
        $user = User::query()->where('email', $email)->firstOrFail();
        Sanctum::actingAs($user);

        return $user;
    }

    public function test_qualquer_usuario_logado_pode_ler_o_manual(): void
    {
        $this->autenticar('profissional@clinica-exemplo.test');

        $this->getJson('/api/manual')
            ->assertOk()
            ->assertJsonStructure(['data' => ['tipo', 'conteudo_html', 'atualizado_em']]);
    }

    public function test_manual_vem_do_arquivo_versionado(): void
    {
        $this->autenticar();

        $doArquivo = file_get_contents(resource_path('manual/manual.html'));

        $this->getJson('/api/manual')
            ->assertOk()
            ->assertJsonPath('data.conteudo_html', $doArquivo);
    }

    public function test_mapa_mental_e_um_documento_separado(): void
    {
        $this->autenticar();

        $mapa = $this->getJson('/api/manual/mapa-mental')
            ->assertOk()
            ->assertJsonPath('data.tipo', 'mapa-mental');

        $manual = $this->getJson('/api/manual')->assertOk()->assertJsonPath('data.tipo', 'manual');

        $this->assertNotEquals(
            $manual->json('data.conteudo_html'),
            $mapa->json('data.conteudo_html'),
        );
    }

    public function test_nao_existe_endpoint_de_edicao(): void
    {
        $this->autenticar();

        // 405: a rota existe para GET, e o método não é aceito. É a resposta
        // correta para "isto não se edita mais".
        $this->putJson('/api/manual', ['conteudo_html' => '<p>x</p>'])
            ->assertStatus(405);
    }

    public function test_tipo_invalido_retorna_404(): void
    {
        $this->autenticar();

        $this->getJson('/api/manual/inexistente')->assertNotFound();
    }

    public function test_a_permissao_de_editar_manual_nao_existe_mais(): void
    {
        $this->autenticar();

        $permissoes = $this->getJson('/api/permissions')->assertOk()->json('data');

        $nomes = collect($permissoes)->pluck('name')->all();

        $this->assertNotContains('manual.manage', $nomes);
    }
}
