<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRoleRequest;
use App\Http\Requests\UpdateRoleRequest;
use App\Http\Resources\RoleResource;
use App\Support\Auditoria;
use App\Support\GuardaAdministracao;
use App\Support\RoleCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return RoleResource::collection(
            Role::query()
                ->select('roles.*')
                ->where('tenant_id', request()->user()?->tenant_id)
                ->where('guard_name', 'web')
                ->withCount('permissions')
                // Subconsulta em vez de withCount('users'): a relacao users()
                // do Spatie resolve o model pelo guard_name do proprio papel, e
                // o withCount monta a relacao a partir de uma instancia vazia,
                // sem guard_name — o que estoura com "Class name must be a
                // valid object or a string".
                ->addSelect([
                    'users_count' => DB::table('model_has_roles')
                        ->selectRaw('count(*)')
                        ->whereColumn('model_has_roles.role_id', 'roles.id'),
                ])
                ->orderBy('name')
                ->get()
        );
    }

    public function store(StoreRoleRequest $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $dados = $request->validated();

        $papel = DB::transaction(function () use ($dados, $tenantId) {
            // tenant_id explicito em vez de deixar o Spatie resolver: e o
            // mesmo cuidado do TenantController, senao nasce um papel global
            // com tenant_id nulo, que sombreia o papel de todos os tenants.
            $papel = Role::create([
                'name' => $dados['name'],
                'guard_name' => 'web',
                'tenant_id' => $tenantId,
            ]);

            if (! empty($dados['copiar_de'])) {
                $origem = Role::query()
                    ->where('tenant_id', $tenantId)
                    ->where('guard_name', 'web')
                    ->where('name', $dados['copiar_de'])
                    ->firstOrFail();

                $papel->syncPermissions($origem->permissions);
            }

            return $papel;
        });

        // Evento explicito: Role e model do Spatie e nao carrega o trait
        // Auditable, entao papel fora da trilha passaria despercebido.
        Auditoria::registrar('papel.criado', 'roles', (int) $papel->getKey(), [
            'nome' => $papel->name,
            'copiado_de' => $dados['copiar_de'] ?? null,
        ]);

        return response()->json([
            'data' => (new RoleResource($this->comContagens($papel)))->toArray($request),
        ], 201);
    }

    public function update(UpdateRoleRequest $request, Role $role): JsonResponse
    {
        $this->recusarPapelDeSistema($role, 'renomeado');

        $anterior = $role->name;
        $role->update(['name' => $request->validated()['name']]);

        Auditoria::registrar('papel.renomeado', 'roles', (int) $role->getKey(), [
            'antes' => ['nome' => $anterior],
            'depois' => ['nome' => $role->name],
        ]);

        return response()->json([
            'data' => (new RoleResource($this->comContagens($role)))->toArray($request),
        ]);
    }

    public function destroy(Role $role): JsonResponse
    {
        $this->recusarPapelDeSistema($role, 'excluído');

        $vinculados = $this->usuariosCom($role);

        if ($vinculados > 0) {
            throw ValidationException::withMessages([
                'name' => $vinculados === 1
                    ? 'Há 1 usuário com este papel. Troque o papel dele antes de excluir.'
                    : "Há {$vinculados} usuários com este papel. Troque o papel deles antes de excluir.",
            ]);
        }

        GuardaAdministracao::aoExcluirPapel($role);

        $role->delete();

        Auditoria::registrar('papel.excluido', 'roles', (int) $role->getKey(), [
            'nome' => $role->name,
        ]);

        return response()->json(['data' => ['name' => $role->name]]);
    }

    private function comContagens(Role $papel): Role
    {
        $papel->setAttribute('permissions_count', $papel->permissions()->count());
        $papel->setAttribute('users_count', $this->usuariosCom($papel));

        return $papel;
    }

    /** Conta pela tabela pivô pelo mesmo motivo do index(): users() depende do guard_name. */
    private function usuariosCom(Role $papel): int
    {
        return DB::table('model_has_roles')->where('role_id', $papel->getKey())->count();
    }

    private function recusarPapelDeSistema(Role $role, string $acao): void
    {
        if (RoleCatalog::ehDeSistema($role->name)) {
            throw ValidationException::withMessages([
                'name' => "O papel \"{$role->name}\" é do sistema e não pode ser {$acao}. As permissões dele continuam editáveis.",
            ]);
        }
    }
}
