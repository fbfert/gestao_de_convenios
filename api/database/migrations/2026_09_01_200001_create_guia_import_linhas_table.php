<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guia_import_linhas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('guia_import_lote_id')->constrained('guia_import_lotes')->cascadeOnDelete();
            $table->unsignedInteger('linha');
            $table->string('status')->default('valida');
            $table->foreignId('matched_guia_id')->nullable()->constrained('guias')->nullOnDelete();
            $table->json('dados_json');
            $table->json('erros_json')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'guia_import_lote_id']);
            $table->index(['tenant_id', 'matched_guia_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guia_import_linhas');
    }
};
