<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('movimentos_financeiros', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignId('conciliacao_financeira_id')->constrained('conciliacoes_financeiras')->cascadeOnDelete();
            $table->foreignId('guia_id')->constrained('guias')->restrictOnDelete();
            $table->foreignId('profissional_informado_id')->nullable()->constrained('profissionais')->restrictOnDelete();
            $table->foreignId('profissional_executor_id')->nullable()->constrained('profissionais')->restrictOnDelete();
            $table->enum('tipo', ['entrada', 'saida']);
            $table->enum('origem', ['analitico', 'repasse']);
            $table->unsignedInteger('quantidade');
            $table->decimal('valor_unitario', 10, 2)->nullable();
            $table->decimal('valor_total', 10, 2);
            $table->string('referencia_analitico_convenio')->nullable();
            $table->string('descricao')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'tipo']);
            $table->index(['conciliacao_financeira_id', 'tipo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movimentos_financeiros');
    }
};
