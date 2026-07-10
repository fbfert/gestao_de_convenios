<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profissionais', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignId('especialidade_id')->constrained('especialidades')->restrictOnDelete();
            $table->string('nome');
            $table->string('conselho_registro')->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'ativo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profissionais');
    }
};
