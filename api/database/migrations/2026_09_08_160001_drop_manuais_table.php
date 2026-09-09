<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * O manual deixou de ser conteudo do tenant.
 *
 * PASSO OBRIGATORIO, JA EXECUTADO ANTES DESTA MIGRATION: o conteudo dos
 * registros foi exportado para `resources/manual/manual.html` e
 * `resources/manual/mapa-mental.html`. A conferencia mostrou que a clinica havia
 * EDITADO os dois documentos (manual alterado em 01/09/2026, mapa mental em
 * 31/08/2026), entao dropar sem exportar teria perdido texto de cliente.
 *
 * O `down()` recria a ESTRUTURA, e nao o conteudo — nenhum down() faria isso. O
 * conteudo sobrevive porque esta versionado no repositorio, que e onde ele
 * resiste a qualquer rollback.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('manuais');
    }

    public function down(): void
    {
        Schema::create('manuais', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->string('tipo');
            $table->longText('conteudo_html');
            $table->foreignId('atualizado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['tenant_id', 'tipo']);
        });
    }
};
