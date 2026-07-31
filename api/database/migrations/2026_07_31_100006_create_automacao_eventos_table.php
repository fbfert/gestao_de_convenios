<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('automacao_eventos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignId('automacao_execucao_id')->constrained('automacao_execucoes')->cascadeOnDelete();
            $table->string('tipo');
            $table->string('status')->nullable();
            $table->json('payload')->nullable();
            $table->json('evidencias')->nullable();
            $table->timestamp('registrado_em');
            $table->timestamps();

            $table->index(['tenant_id', 'automacao_execucao_id']);
            $table->index(['tenant_id', 'tipo', 'registrado_em']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automacao_eventos');
    }
};
