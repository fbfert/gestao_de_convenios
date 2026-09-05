<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Pasta do paciente": biblioteca de documentos do paciente (pedido médico,
 * laudo médico, plano individualizado, relatório de evolução, ...),
 * independente de solicitação. Uma solicitação passa a se vincular a um
 * arquivo aqui (ver migration seguinte) em vez de guardar o arquivo direto —
 * o mesmo documento pode servir a mais de uma solicitação.
 *
 * Não confundir com `paciente_documentos`: aquela é só a carteirinha, com
 * validade obrigatória e expurgo diário. Esta não expira.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('paciente_arquivos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignId('paciente_id')->constrained('pacientes')->cascadeOnDelete();
            $table->string('tipo');
            $table->string('nome_original');
            $table->string('mime')->nullable();
            $table->string('path');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'paciente_id']);
            $table->index(['tenant_id', 'tipo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paciente_arquivos');
    }
};
