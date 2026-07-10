<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('solicitacoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignId('paciente_id')->constrained('pacientes')->restrictOnDelete();
            $table->foreignId('profissional_id')->constrained('profissionais')->restrictOnDelete();
            $table->foreignId('especialidade_id')->constrained('especialidades')->restrictOnDelete();
            $table->foreignId('convenio_id')->constrained('convenios')->restrictOnDelete();
            $table->string('medico_solicitante');
            $table->enum('status', ['under_review', 'approved', 'denied'])->default('under_review');
            $table->date('solicitado_em');
            $table->text('observacoes')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('solicitacoes');
    }
};
