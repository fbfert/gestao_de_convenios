<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clinica_sync_execucoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->enum('origem', ['agendado', 'manual']);
            $table->enum('status', ['ok', 'error'])->nullable();
            $table->timestamp('iniciado_em');
            $table->timestamp('finalizado_em')->nullable();
            // Contagens + pendências por entidade (profissionais/pacientes), formato
            // livre em JSON — é só pra tela de status, nunca lido por código.
            $table->longText('resumo')->nullable();
            $table->text('erro_mensagem')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'iniciado_em']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinica_sync_execucoes');
    }
};
