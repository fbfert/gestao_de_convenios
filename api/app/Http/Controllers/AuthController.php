<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Models\User;
use App\Support\Auditoria;
use App\Support\AuthPayload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;

class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::query()
            ->with('tenant')
            ->where('email', $request->validated()['email'])
            ->first();

        if (! $user || ! Hash::check($request->validated()['password'], $user->password) || ! $user->ativo || ! $user->tenant?->ativo) {
            // E-mail desconhecido nao gera evento: nao ha tenant a quem
            // atribui-lo, e a trilha e por clinica. Com o usuario encontrado,
            // a tentativa recusada e justamente o que interessa registrar.
            if ($user) {
                Auditoria::registrar(
                    acao: 'acesso.login_recusado',
                    entidade: 'users',
                    entidadeId: (int) $user->id,
                    payload: ['email' => $user->email],
                    tenantId: (int) $user->tenant_id,
                    userId: $user->id,
                    comOrigem: true,
                );
            }

            return response()->json([
                'message' => 'Credenciais inválidas.',
            ], 401);
        }

        app(PermissionRegistrar::class)->setPermissionsTeamId($user->tenant_id);

        $token = $user->createToken('api')->plainTextToken;

        Auditoria::registrar(
            acao: 'acesso.login',
            entidade: 'users',
            entidadeId: (int) $user->id,
            tenantId: (int) $user->tenant_id,
            userId: $user->id,
            comOrigem: true,
        );

        return response()->json([
            'token' => $token,
            'user' => AuthPayload::paraUsuario($user),
        ]);
    }

    public function logout(Request $request): Response
    {
        $usuario = $request->user();
        $token = $request->bearerToken() ? PersonalAccessToken::findToken($request->bearerToken()) : null;
        $token?->delete();

        if ($usuario) {
            Auditoria::registrar(
                acao: 'acesso.logout',
                entidade: 'users',
                entidadeId: (int) $usuario->id,
                tenantId: (int) $usuario->tenant_id,
                userId: $usuario->id,
                comOrigem: true,
            );
        }

        return response()->noContent();
    }
}
