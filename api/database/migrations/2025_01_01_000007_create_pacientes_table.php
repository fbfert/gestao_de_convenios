<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pacientes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->string('nome');
            $table->string('cpf')->nullable();
            $table->string('carteirinha');
            $table->foreignId('convenio_id')->constrained('convenios')->restrictOnDelete();
            $table->string('telefone')->nullable();
            $table->string('clinica_agil_id')->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'convenio_id']);
            $table->index('carteirinha');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pacientes');
    }
};
