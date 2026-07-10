<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('antecipacoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignId('guia_id')->constrained('guias')->restrictOnDelete();
            $table->foreignId('paciente_id')->constrained('pacientes')->restrictOnDelete();
            $table->foreignId('convenio_id')->constrained('convenios')->restrictOnDelete();
            $table->date('ciclo_inicio');
            $table->date('ciclo_fim');
            $table->unsignedInteger('qtd_autorizada');
            $table->unsignedInteger('qtd_utilizada')->default(0);
            $table->enum('status', ['open', 'closed'])->default('open');
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['paciente_id', 'ciclo_inicio', 'ciclo_fim']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('antecipacoes');
    }
};
