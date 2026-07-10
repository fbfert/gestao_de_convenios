<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignId('solicitacao_id')->nullable()->constrained('solicitacoes')->restrictOnDelete();
            $table->foreignId('convenio_id')->constrained('convenios')->restrictOnDelete();
            $table->foreignId('paciente_id')->constrained('pacientes')->restrictOnDelete();
            $table->foreignId('profissional_id')->constrained('profissionais')->restrictOnDelete();
            $table->foreignId('especialidade_id')->constrained('especialidades')->restrictOnDelete();
            $table->string('numero_guia');
            $table->enum('tipo_terapia', ['especializada', 'convencional', 'outro']);
            $table->enum('status', ['under_review', 'finalized', 'denied'])->default('under_review');
            $table->date('data_solicitacao');
            $table->date('data_finalizacao')->nullable();
            $table->string('senha')->nullable();
            $table->date('validade_senha')->nullable();
            $table->text('observacoes')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['convenio_id', 'numero_guia']);
            $table->index('validade_senha');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guias');
    }
};
