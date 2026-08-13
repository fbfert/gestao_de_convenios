<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateRolePermissionsRequest;
use App\Http\Resources\PermissionResource;
use App\Http\Resources\RoleResource;
use App\Support\GuardaAdministracao;
use App\Support\PermissionCatalog;
use Illuminate\Http\JsonResponse;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolePermissionController extends Controller
{
    public function show(Role $role): JsonResponse
    {
        return response()->json([
            'data' => [
                'role' => (new RoleResource($role))->toArray(request()),
                'permissions' => PermissionResource::collection(
                    $role->permissions()->whereIn('name', PermissionCatalog::all())->orderBy('name')->get()
                )->resolve(),
            ],
        ]);
    }

    public function update(UpdateRolePermissionsRequest $request, Role $role): JsonResponse
    {
        $nomes = $request->validated()['permissions'];

        GuardaAdministracao::aoSincronizarPermissoes($role, $nomes, $request->user());

        $permissions = Permission::query()
            ->whereIn('name', $nomes)
            ->get();

        $role->syncPermissions($permissions);

        return response()->json([
            'data' => [
                'role' => (new RoleResource($role->fresh(['permissions'])))->toArray($request),
                'permissions' => PermissionResource::collection(
                    $role->permissions()->orderBy('name')->get()
                )->resolve(),
            ],
        ]);
    }
}
