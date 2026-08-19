<?php

namespace Database\Seeders;

use App\Models\Profissional;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::query()->where('slug', 'clinica-exemplo')->firstOrFail();
        $profissional = Profissional::query()->where('nome', 'Dra. Marina Tavares')->firstOrFail();

        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);

        $admin = User::query()->updateOrCreate(
            ['email' => 'admin@clinica-exemplo.test'],
            [
                'tenant_id' => $tenant->id,
                'name' => 'Admin Clínica Exemplo',
                'password' => 'password',
                'ativo' => true,
            ]
        );

        $funcionario = User::query()->updateOrCreate(
            ['email' => 'funcionario@clinica-exemplo.test'],
            [
                'tenant_id' => $tenant->id,
                'name' => 'Funcionário Clínica Exemplo',
                'password' => 'password',
                'ativo' => true,
            ]
        );

        $profissionalUser = User::query()->updateOrCreate(
            ['email' => 'profissional@clinica-exemplo.test'],
            [
                'tenant_id' => $tenant->id,
                'profissional_id' => $profissional->id,
                'name' => 'Profissional Clínica Exemplo',
                'password' => 'password',
                'ativo' => true,
            ]
        );

        // Administrador nominal do sistema. Fica aqui alem da migration
        // 2026_08_07_100000 porque `migrate:fresh --seed` derruba o schema:
        // sem isso a conta some do ambiente local e do banco de testes.
        // A senha nunca fica no código-fonte: vem de env, e sem ela esse
        // usuário simplesmente não é criado/atualizado por este seeder.
        $felipe = null;
        if ($senhaFelipe = env('SEED_ADMIN_PASSWORD')) {
            $felipe = User::query()->updateOrCreate(
                ['email' => 'fbfert@gmail.com'],
                [
                    'tenant_id' => $tenant->id,
                    'name' => 'Felipe B. Fert',
                    'password' => $senhaFelipe,
                    'ativo' => true,
                ]
            );
        }

        $admin->syncRoles(['admin']);
        $funcionario->syncRoles(['funcionario']);
        $profissionalUser->syncRoles(['profissional']);
        $felipe?->syncRoles(['admin']);
    }
}
