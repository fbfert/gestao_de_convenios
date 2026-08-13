<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('paciente_documentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            // Nullable: a leitura acontece antes de o paciente existir. O
            // documento nasce solto, e a gravação do cadastro o adota. O que
            // ficar sem dono expira junto com o resto.
            $table->foreignId('paciente_id')->nullable()->constrained('pacientes')->cascadeOnDelete();
            $table->string('tipo', 30)->default('carteirinha');
            $table->string('path');
            $table->string('mime', 100)->nullable();
            $table->string('nome_original')->nullable();
            $table->timestamp('expira_em');
            $table->timestamps();

            $table->index(['tenant_id', 'expira_em']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paciente_documentos');
    }
};
