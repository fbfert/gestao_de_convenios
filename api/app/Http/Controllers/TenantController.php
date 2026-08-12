<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTenantRequest;
use App\Http\Requests\UpdateTenantRequest;
use App\Models\Tenant;
use App\Models\User;
use App\Support\PermissionCatalog;
use App\Support\RoleCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Gestão de clínicas (tenants). Restrito a `super_admin` pelo middleware
 * `super-admin` — ver a migration 2026_08_12_180000 para o motivo de não ser
 * uma permissão do PermissionCatalog.
 *
 * Não há `destroy`. Apagar um tenant significaria apagar pacientes, guias e
 * lançamentos junto, ou deixá-los órfãos apontando para um tenant inexistente.
 * Desativar já resolve o caso real: `AuthController::login` recusa quem
 * pertence a tenant inativo.
 */
class TenantController extends Controller
{
    public function index(): JsonResponse
    {
        $tenants = Tenant::query()
            ->withCount('users')
            ->orderBy('nome')
            ->get()
            ->map(fn (Tenant $tenant) => $this->paraArray($tenant));

        return response()->json(['data' => $tenants]);
    }

    public function store(StoreTenantRequest $request): JsonResponse
    {
        $dados = $request->validated();
        $registrar = app(PermissionRegistrar::class);
        $teamAnterior = $registrar->getPermissionsTeamId();

        try {
            $tenant = DB::transaction(function () use ($dados, $registrar) {
                $tenant = Tenant::query()->create([
                    'nome' => $dados['nome'],
                    'slug' => $dados['slug'],
                    'cnpj' => $dados['cnpj'] ?? null,
                    'ativo' => $dados['ativo'],
                ]);

                // A partir daqui tudo que o Spatie gravar precisa cair no
                // tenant novo, e não no de quem está criando.
                $registrar->setPermissionsTeamId($tenant->id);

                foreach (PermissionCatalog::all() as $permissao) {
                    Permission::findOrCreate($permissao, 'web');
                }

                // Consulta por tenant_id explícito em vez de
                // `Role::findOrCreate`: sem team id o Spatie criaria um papel
                // global com tenant_id nulo, que sombrearia o papel de todos os
                // tenants — o defeito corrigido na migration 2026_08_05_100000.
                foreach (RoleCatalog::all() as $nome => $permissoes) {
                    $papel = Role::query()
                        ->where('tenant_id', $tenant->id)
                        ->where('name', $nome)
                        ->where('guard_name', 'web')
                        ->first()
                        ?? Role::create([
                            'name' => $nome,
                            'guard_name' => 'web',
                            'tenant_id' => $tenant->id,
                        ]);

                    $papel->syncPermissions($permissoes);
                }

                $admin = User::query()->create([
                    'tenant_id' => $tenant->id,
                    'name' => $dados['admin']['name'],
                    'email' => $dados['admin']['email'],
                    'password' => $dados['admin']['password'],
                    'ativo' => true,
                ]);

                $admin->assignRole('admin');

                return $tenant;
            });
        } finally {
            // Restaura o contexto de quem chamou mesmo se a transação abortar,
            // senão o restante da requisição enxergaria o tenant errado.
            $registrar->setPermissionsTeamId($teamAnterior);
            $registrar->forgetCachedPermissions();
        }

        return response()->json([
            'data' => $this->paraArray($tenant->loadCount('users')),
        ], 201);
    }

    public function update(UpdateTenantRequest $request, Tenant $tenant): JsonResponse
    {
        $dados = $request->validated();

        // Desativar a própria clínica deixaria quem está logado sem conseguir
        // entrar de novo: o login recusa usuário de tenant inativo.
        if (! $dados['ativo'] && $tenant->id === (int) $request->user()->tenant_id) {
            throw ValidationException::withMessages([
                'ativo' => 'Você não pode desativar a clínica à qual a sua própria conta pertence.',
            ]);
        }

        $tenant->update($dados);

        return response()->json([
            'data' => $this->paraArray($tenant->loadCount('users')),
        ]);
    }

    private function paraArray(Tenant $tenant): array
    {
        return [
            'id' => $tenant->id,
            'nome' => $tenant->nome,
            'slug' => $tenant->slug,
            'cnpj' => $tenant->cnpj,
            'ativo' => $tenant->ativo,
            'usuarios_count' => $tenant->users_count ?? 0,
            'created_at' => $tenant->created_at?->toISOString(),
        ];
    }
}
