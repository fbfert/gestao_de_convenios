<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateRolePermissionsRequest;
use App\Http\Resources\PermissionResource;
use App\Http\Resources\RoleResource;
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
        $permissions = Permission::query()
            ->whereIn('name', $request->validated()['permissions'])
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
