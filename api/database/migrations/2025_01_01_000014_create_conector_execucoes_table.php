<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conector_execucoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignId('convenio_id')->constrained('convenios')->restrictOnDelete();
            $table->dateTime('executado_em');
            $table->enum('status', ['ok', 'error', 'pending_manual']);
            $table->json('detalhes')->nullable();
            $table->timestamps();

            $table->index(['convenio_id', 'executado_em']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conector_execucoes');
    }
};
