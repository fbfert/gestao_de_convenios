<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marca quem pode administrar tenants.
 *
 * Por que uma coluna e nao uma permissao do PermissionCatalog: o papel `admin`
 * de qualquer tenant tem `permissoes.manage` e edita as atribuicoes na tela de
 * Permissoes. Se "gerenciar tenants" fosse uma permissao do catalogo, o
 * administrador de uma clinica poderia conceder a si mesmo e passar a criar e
 * alterar as outras clinicas. A capacidade precisa viver fora do catalogo, e
 * fora do escopo de tenant, exatamente como o proprio User (ver ADR-11).
 *
 * A flag nao e editavel por nenhuma tela. Conceder e revogar e operacao de
 * banco, deliberadamente.
 */
return new class extends Migration
{
    private const EMAIL_INICIAL = 'fbfert@gmail.com';

    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('super_admin')->default(false)->after('ativo');
        });

        // Mesmo criterio da migration 2026_08_07_100000, que provisiona o
        // administrador inicial: sem isto ninguem alcanca a tela nova.
        User::query()
            ->where('email', self::EMAIL_INICIAL)
            ->update(['super_admin' => true]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('super_admin');
        });
    }
};
