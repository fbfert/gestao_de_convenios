<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('paciente_telefones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignId('paciente_id')->constrained('pacientes')->cascadeOnDelete();
            // Só dígitos, como o CPF: máscara é assunto de exibição, e guardar
            // formatado impediria procurar o paciente pelo telefone.
            $table->string('numero', 20);
            $table->string('rotulo', 20)->default('celular');
            $table->string('contato_nome')->nullable();
            $table->boolean('principal')->default(false);
            $table->unsignedSmallInteger('ordem')->default(0);
            $table->timestamps();

            $table->index(['tenant_id', 'numero']);
            $table->index(['paciente_id', 'ordem']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paciente_telefones');
    }
};
