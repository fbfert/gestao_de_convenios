<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * O que exige acao agora.
 *
 * A coluna `aberto_dedupe` e a peca menos obvia: o alvo de producao e MariaDB,
 * que nao tem indice unico PARCIAL. Ela vale 1 enquanto o alerta esta aberto e
 * NULL quando resolvido; como os dois bancos tratam NULL como distinto num
 * indice unico, varios resolvidos da mesma chave convivem e so um aberto e
 * possivel. Coluna gerada resolveria tambem, mas exigiria sintaxe diferente em
 * SQLite (testes) e MariaDB (producao).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alertas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->string('chave');
            $table->string('nivel'); // verde|amarelo|vermelho
            $table->string('titulo');
            $table->text('descricao')->nullable();
            // Polimorfico sem FK de proposito: o alvo e referencia de exibicao,
            // nao de integridade — se a entidade sumir, o avaliador resolve o
            // alerta na rodada seguinte por nao encontra-la mais.
            $table->string('entidade')->nullable();
            $table->unsignedBigInteger('entidade_id')->nullable();
            $table->json('dados')->nullable();
            $table->timestamp('aberto_em');
            $table->timestamp('resolvido_em')->nullable();
            $table->foreignId('reconhecido_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reconhecido_em')->nullable();
            $table->timestamp('silenciado_ate')->nullable();
            $table->unsignedTinyInteger('aberto_dedupe')->nullable();
            $table->timestamps();

            $table->unique(
                ['tenant_id', 'chave', 'entidade', 'entidade_id', 'aberto_dedupe'],
                'alertas_dedupe_aberto_unique',
            );
            // A listagem e o card filtram por "aberto" e ordenam por abertura.
            $table->index(['tenant_id', 'resolvido_em', 'aberto_em']);
            $table->index(['tenant_id', 'nivel', 'resolvido_em']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alertas');
    }
};
