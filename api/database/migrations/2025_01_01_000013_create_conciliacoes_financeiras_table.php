<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conciliacoes_financeiras', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignId('guia_id')->constrained('guias')->restrictOnDelete();
            $table->foreignId('profissional_id')->constrained('profissionais')->restrictOnDelete();
            $table->unsignedInteger('quantidade');
            $table->decimal('valor_unitario', 10, 2)->nullable();
            $table->decimal('valor_total', 10, 2)->nullable();
            $table->string('referencia_analitico_convenio')->nullable();
            $table->enum('status', ['pending', 'reviewed', 'paid'])->default('pending');
            $table->dateTime('conferido_em')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['profissional_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conciliacoes_financeiras');
    }
};
