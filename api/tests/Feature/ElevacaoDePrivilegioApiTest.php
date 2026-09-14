<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Quem administra usuários não se promove, nem promove alguém acima do próprio
 * nível.
 *
 * `usuarios.manage` é do `admin` por padrão, mas a tela de Perfis e Permissões
 * permite concedê-la a qualquer papel — o próprio `RoleCatalog` documenta isso
 * como esperado. Bastava a secretária ganhar a permissão para cadastrar
 * usuários e, na requisição seguinte, atribuir `admin` a si mesma.
 */
class ElevacaoDePrivilegioApiTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_nao_muda_o_proprio_papel(): void
    {
        $user = $this->funcionarioQuePodeGerirUsuarios();

        $this->patchJson("/api/usuarios/{$user->id}", ['role' => 'admin'])
            ->assertJsonValidationErrors('role');

        $this->assertSame('funcionario', $user->fresh()->roles->first()?->name);
    }

    public function test_nao_concede_papel_com_permissao_que_nao_possui(): void
    {
        $this->funcionarioQuePodeGerirUsuarios();

        $alvo = User::query()->where('email', 'profissional@clinica-exemplo.test')->firstOrFail();

        $this->patchJson("/api/usuarios/{$alvo->id}", ['role' => 'admin'])
            ->assertJsonValidationErrors('role');

        $this->assertSame('profissional', $alvo->fresh()->roles->first()?->name);
    }

    /**
     * O caminho legítimo continua aberto — senão os dois testes acima passariam
     * por qualquer quebra da rota, e não pela trava.
     */
    public function test_admin_ainda_troca_o_papel_de_outra_pessoa(): void
    {
        $admin = User::query()->where('email', 'admin@clinica-exemplo.test')->firstOrFail();
        Sanctum::actingAs($admin);
        app(PermissionRegistrar::class)->setPermissionsTeamId($admin->tenant_id);

        $alvo = User::query()->where('email', 'profissional@clinica-exemplo.test')->firstOrFail();

        $this->patchJson("/api/usuarios/{$alvo->id}", ['role' => 'funcionario'])->assertOk();

        $this->assertSame('funcionario', $alvo->fresh()->roles->first()?->name);
    }

    /** Papel de nível igual ou menor continua podendo ser atribuído. */
    public function test_concede_papel_dentro_do_proprio_nivel(): void
    {
        $this->funcionarioQuePodeGerirUsuarios();

        $alvo = User::query()->where('email', 'profissional@clinica-exemplo.test')->firstOrFail();

        $this->patchJson("/api/usuarios/{$alvo->id}", ['role' => 'funcionario'])->assertOk();

        $this->assertSame('funcionario', $alvo->fresh()->roles->first()?->name);
    }

    /**
     * O resource da execução devolve o payload inteiro enviado ao portal:
     * paciente, carteirinha, CID, médico/CRM e o caminho do arquivo no
     * servidor. O item de menu correspondente era o único do arquivo de
     * navegação sem permissão declarada — aparecia até para o profissional.
     */
    public function test_profissional_nao_alcanca_automacoes_nem_analiticos(): void
    {
        $user = User::query()->where('email', 'profissional@clinica-exemplo.test')->firstOrFail();
        Sanctum::actingAs($user);

        $this->getJson('/api/automacoes')->assertForbidden();
        $this->getJson('/api/analiticos')->assertForbidden();
    }

    /**
     * Reproduz o cenário que o `RoleCatalog` descreve: a clínica concede
     * `usuarios.manage` ao funcionário para ele cadastrar gente.
     */
    private function funcionarioQuePodeGerirUsuarios(): User
    {
        $user = User::query()->where('email', 'funcionario@clinica-exemplo.test')->firstOrFail();

        app(PermissionRegistrar::class)->setPermissionsTeamId($user->tenant_id);

        Role::query()
            ->where('tenant_id', $user->tenant_id)
            ->where('name', 'funcionario')
            ->firstOrFail()
            ->givePermissionTo('usuarios.manage');

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Sanctum::actingAs($user->fresh());

        return $user;
    }
}
