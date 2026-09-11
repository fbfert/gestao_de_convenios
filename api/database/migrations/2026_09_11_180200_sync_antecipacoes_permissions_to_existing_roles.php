<?php

use App\Models\Tenant;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * `dashboard.antecipacoes`, `antecipacoes.view` e `antecipacoes.manage` já
 * existiam nas linhas de `permissions`/`role_has_permissions` de tenants
 * antigos — sobraram do CRUD de "Antecipação" (balde de cota) removido na
 * Fase 1, que nunca chegou a ser revogado das roles quando o RoleCatalog
 * mudou (mudança no catálogo PHP não reescreve grants já persistidos).
 * Coincidência de nome com o que a Fase 3 reintroduz fez esse resíduo
 * aparecer como se já estivesse certo — não estava: funcionario tinha só
 * `.view` (faltava `.manage`) e profissional ainda tinha `dashboard.antecipacoes`
 * (o app novo não dá esse acesso a profissional).
 *
 * Sincroniza os 3 papéis de sistema com o RoleCatalog atual, por nome
 * explícito (não por derivação de outra permissão) — é exatamente o que
 * queremos aqui: profissional PERDE o acesso que sobrou, não herda de outra
 * coisa que ele já tenha. Papéis customizados por tenant ficam de fora, como
 * as sincronizações anteriores já fazem (ex.: sync_profissionais_manage).
 *
 * `antecipacoes.viewOwn` não tem equivalente na Fase 3 (não há escopo "só
 * minhas antecipações") e não é referenciada em nenhum lugar do código atual
 * — é pura sobra, removida de vez.
 */
return new class extends Migration
{
    private const CONCEDER = ['dashboard.antecipacoes', 'antecipacoes.view', 'antecipacoes.manage'];

    public function up(): void
    {
        $registrar = app(PermissionRegistrar::class);

        foreach (self::CONCEDER as $nome) {
            Permission::findOrCreate($nome, 'web');
        }

        foreach (Tenant::query()->get() as $tenant) {
            $registrar->setPermissionsTeamId($tenant->id);

            Role::findOrCreate('admin', 'web')->givePermissionTo(self::CONCEDER);
            Role::findOrCreate('funcionario', 'web')->givePermissionTo(self::CONCEDER);

            $profissional = Role::findOrCreate('profissional', 'web');
            $profissional->revokePermissionTo(self::CONCEDER);

            $viewOwn = Permission::query()->where('name', 'antecipacoes.viewOwn')->where('guard_name', 'web')->first();
            if ($viewOwn) {
                foreach (Role::query()->where('tenant_id', $tenant->id)->where('guard_name', 'web')->get() as $papel) {
                    $papel->revokePermissionTo($viewOwn);
                }
            }
        }

        Permission::query()->where('name', 'antecipacoes.viewOwn')->where('guard_name', 'web')->delete();

        $registrar->setPermissionsTeamId(null);
        $registrar->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Irreversível de propósito: não há como saber quais tenants tinham
        // `antecipacoes.viewOwn` concedida antes desta migration, e devolver
        // dashboard.antecipacoes/antecipacoes.* ao estado anterior (resíduo
        // pré-Fase-1) não serve a nada — reverter aqui só reintroduziria o
        // mesmo resíduo que esta migration existe para limpar.
    }
};
