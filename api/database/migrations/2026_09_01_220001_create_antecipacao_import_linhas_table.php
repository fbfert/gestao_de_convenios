<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('antecipacao_import_linhas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('antecipacao_import_lote_id')->constrained('antecipacao_import_lotes')->cascadeOnDelete();
            $table->unsignedInteger('linha');
            $table->string('status')->default('valida');
            $table->foreignId('matched_antecipacao_id')->nullable()->constrained('antecipacoes')->nullOnDelete();
            $table->json('dados_json');
            $table->json('erros_json')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'antecipacao_import_lote_id']);
            $table->index(['tenant_id', 'matched_antecipacao_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('antecipacao_import_linhas');
    }
};
