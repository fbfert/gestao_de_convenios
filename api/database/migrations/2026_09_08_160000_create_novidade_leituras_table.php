<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Quem ja leu o que.
 *
 * E o que faz o card mostrar "2 nao lidas" e parar de aparecer depois. Sem isso
 * o card vira paisagem em duas semanas — o mesmo defeito do digest vazio.
 *
 * A chave e o SLUG do arquivo, e nao um id: novidade nao tem id de banco, e o
 * nome do arquivo e o identificador estavel.
 *
 * Sem `tenant_id`: leitura e de PESSOA. Dois usuarios do mesmo tenant leem em
 * momentos diferentes, e marcar por tenant esconderia a novidade de quem ainda
 * nao viu.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('novidade_leituras', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('slug');
            $table->timestamp('lido_em');

            $table->unique(['user_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('novidade_leituras');
    }
};
