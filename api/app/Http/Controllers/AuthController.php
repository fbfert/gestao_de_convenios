<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::query()
            ->with('tenant')
            ->where('email', $request->validated()['email'])
            ->first();

        if (! $user || ! Hash::check($request->validated()['password'], $user->password) || ! $user->ativo || ! $user->tenant?->ativo) {
            return response()->json([
                'message' => 'Credenciais inválidas.',
            ], 401);
        }

        $token = $user->createToken('api')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'tenant' => [
                    'id' => $user->tenant->id,
                    'nome' => $user->tenant->nome,
                    'slug' => $user->tenant->slug,
                ],
            ],
        ]);
    }

    public function logout(Request $request): Response
    {
        $token = $request->bearerToken() ? PersonalAccessToken::findToken($request->bearerToken()) : null;
        $token?->delete();

        return response()->noContent();
    }
}
