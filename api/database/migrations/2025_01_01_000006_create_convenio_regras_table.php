<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('convenio_regras', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignId('convenio_id')->constrained('convenios')->restrictOnDelete();
            $table->enum('tipo_terapia', ['especializada', 'convencional', 'outro']);
            $table->enum('frequencia_lancamento', ['diaria', 'semanal', 'mensal']);
            $table->unsignedInteger('qtd_autorizada_por_ciclo');
            $table->unsignedInteger('validade_senha_dias')->nullable();
            $table->text('observacoes')->nullable();
            $table->date('vigente_desde');
            $table->date('vigente_ate')->nullable();
            $table->timestamps();

            $table->index(['convenio_id', 'tipo_terapia']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('convenio_regras');
    }
};
