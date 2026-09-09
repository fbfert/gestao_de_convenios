<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Quem recebe alerta por e-mail.
 *
 * DUAS tabelas de proposito. O destinatario global (o suporte da Xiax) NAO pode
 * morar na mesma tabela com `tenant_id` nulo: o trait BelongsToTenant aplica um
 * global scope, e um registro com tenant nulo desaparece de qualquer consulta
 * que nao use withoutGlobalScopes(). No dia em que alguem esquecer isso num
 * refactor, o suporte para de receber — e ausencia de e-mail parece "sem
 * problemas", que e o pior modo de falhar. Tabela separada torna o esquecimento
 * impossivel.
 *
 * `niveis` e `chaves` sao JSON porque o filtro e o que faz a coisa sobreviver:
 * a recepcionista quer senha vencendo, o financeiro quer glosa, o suporte quer
 * worker offline. Mandar tudo para todos leva a pedirem para desligar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alerta_destinatarios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->string('email');
            $table->string('nome')->nullable();
            $table->json('niveis');            // ['amarelo','vermelho']
            $table->json('chaves')->nullable(); // null = todas
            $table->string('canal')->default('digest'); // digest|imediato|ambos
            $table->unsignedTinyInteger('horario_digest')->default(8); // hora do dia
            $table->boolean('ativo')->default(true);
            $table->timestamp('verificado_em')->nullable();
            $table->unsignedTinyInteger('falhas_consecutivas')->default(0);
            $table->timestamps();

            $table->unique(['tenant_id', 'email']);
        });

        Schema::create('alerta_destinatarios_globais', function (Blueprint $table) {
            $table->id();
            // Sem tenant_id, e o model correspondente NAO usa BelongsToTenant.
            $table->string('email')->unique();
            $table->string('nome')->nullable();
            $table->json('niveis');
            $table->json('chaves')->nullable();
            $table->string('canal')->default('digest');
            $table->unsignedTinyInteger('horario_digest')->default(8);
            $table->boolean('ativo')->default(true);
            $table->timestamp('verificado_em')->nullable();
            $table->unsignedTinyInteger('falhas_consecutivas')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alerta_destinatarios_globais');
        Schema::dropIfExists('alerta_destinatarios');
    }
};
