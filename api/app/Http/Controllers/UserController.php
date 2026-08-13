<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUsuarioRequest;
use App\Http\Requests\UpdateUsuarioRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Support\OrdenaListagem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Spatie\Permission\PermissionRegistrar;

class UserController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $busca = trim((string) $request->string('busca'));

        return UserResource::collection(
            User::query()
                ->with(['profissional', 'tenant', 'roles'])
                ->where('tenant_id', $request->user()?->tenant_id)
                ->when($busca !== '', function ($query) use ($busca) {
                    $query->where(function ($nested) use ($busca) {
                        $nested->where('name', 'like', "%{$busca}%")
                            ->orWhere('email', 'like', "%{$busca}%");
                    });
                })
                ->tap(fn ($query) => OrdenaListagem::aplicar(
                    $query,
                    $request->only(['ordenar_por', 'direcao']),
                    [
                        'nome' => 'name',
                        'email' => 'email',
                        'status' => 'ativo',
                    ],
                    padrao: 'name',
                    desempate: 'name',
                ))
                ->paginate((int) $request->integer('per_page', 15))
        );
    }

    public function store(StoreUsuarioRequest $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $validated = $request->validated();

        $usuario = User::query()->create([
            'tenant_id' => $tenantId,
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'profissional_id' => $validated['role'] === 'profissional'
                ? ($validated['profissional_id'] ?? null)
                : null,
            'ativo' => $request->boolean('ativo', true),
        ]);

        app(PermissionRegistrar::class)->setPermissionsTeamId($tenantId);
        $usuario->syncRoles([$validated['role']]);
        $usuario->load(['profissional', 'roles']);

        return (new UserResource($usuario))->response()->setStatusCode(201);
    }

    public function update(UpdateUsuarioRequest $request, User $usuario): UserResource
    {
        $validated = $request->validated();

        if (array_key_exists('name', $validated)) {
            $usuario->name = $validated['name'];
        }

        if (array_key_exists('email', $validated)) {
            $usuario->email = $validated['email'];
        }

        if (array_key_exists('password', $validated) && $validated['password'] !== null) {
            $usuario->password = $validated['password'];
        }

        if (array_key_exists('ativo', $validated)) {
            $usuario->ativo = (bool) $validated['ativo'];
        }

        if (array_key_exists('role', $validated)) {
            $usuario->profissional_id = $validated['role'] === 'profissional'
                ? ($validated['profissional_id'] ?? $usuario->profissional_id)
                : null;
        } elseif (array_key_exists('profissional_id', $validated)) {
            $usuario->profissional_id = $validated['profissional_id'];
        }

        $usuario->save();

        if (array_key_exists('role', $validated)) {
            app(PermissionRegistrar::class)->setPermissionsTeamId($usuario->tenant_id);
            $usuario->syncRoles([$validated['role']]);
        }

        $usuario->load(['profissional', 'roles']);

        return new UserResource($usuario);
    }
}
