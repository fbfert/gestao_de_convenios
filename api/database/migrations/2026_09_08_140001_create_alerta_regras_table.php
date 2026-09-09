<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * O que dispara alerta, e com que limiar.
 *
 * Limiar em tabela por tenant e a regra de ouro do projeto (ADR-03): nenhuma
 * regra de negocio configuravel fica hardcoded em Service ou Controller.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alerta_regras', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->string('chave');
            $table->boolean('ativo')->default(true);
            $table->string('nivel_base'); // verde|amarelo|vermelho
            $table->integer('limiar_amarelo')->nullable();
            $table->integer('limiar_vermelho')->nullable();
            // Consumida pela fase de notificacoes: so regra critica dispara
            // e-mail imediato. Nasce aqui para a tabela nao mudar depois.
            $table->boolean('critica')->default(false);
            $table->timestamps();

            $table->unique(['tenant_id', 'chave']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alerta_regras');
    }
};
