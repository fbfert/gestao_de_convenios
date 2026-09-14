<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUsuarioRequest;
use App\Http\Requests\UpdateUsuarioRequest;
use App\Http\Resources\UserResource;
use App\Models\ConfiguracaoGlobal;
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
                ->paginate($request->integer('per_page') ?: ConfiguracaoGlobal::itensPorPagina())
        );
    }

    /**
     * Um usuário só, pelo id.
     *
     * Existe porque a tela de edição hidratava o formulário procurando o
     * usuário na página já carregada da listagem. Quem chegasse em
     * `/usuarios/{id}/editar` por link direto, ou de alguém fora da página
     * atual, recebia `null` — e o formulário abria em branco no modo "Novo
     * usuário", de modo que salvar criava um usuário novo em vez de editar o
     * pretendido. O `User` não usa `BelongsToTenant` (ver a nota no model), por
     * isso o escopo por tenant é explícito aqui.
     */
    public function show(Request $request, User $usuario): UserResource
    {
        abort_unless((int) $usuario->tenant_id === (int) $request->user()?->tenant_id, 404);

        return new UserResource($usuario->load(['profissional', 'tenant', 'roles']));
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

        $desativou = array_key_exists('ativo', $validated) && ! $usuario->ativo;
        $trocouSenha = array_key_exists('password', $validated) && $validated['password'] !== null;

        $usuario->save();

        /*
         * Desativar ou trocar a senha derruba as sessões abertas.
         *
         * O middleware já barra na requisição seguinte, mas revogar aqui fecha
         * a janela: desativar alguém tem de valer no ato, e trocar a senha de
         * uma conta comprometida só serve se expulsar quem está dentro — senão
         * o invasor segue com o token antigo, que a senha nova não invalida.
         */
        if ($desativou || $trocouSenha) {
            $usuario->tokens()->delete();
        }

        if (array_key_exists('role', $validated)) {
            app(PermissionRegistrar::class)->setPermissionsTeamId($usuario->tenant_id);
            $usuario->syncRoles([$validated['role']]);
        }

        $usuario->load(['profissional', 'roles']);

        return new UserResource($usuario);
    }
}
