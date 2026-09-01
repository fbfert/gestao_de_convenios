<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lancamento_import_linhas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('lancamento_import_lote_id')->constrained('lancamento_import_lotes')->cascadeOnDelete();
            $table->unsignedInteger('linha');
            $table->string('status')->default('valida');
            $table->foreignId('matched_lancamento_id')->nullable()->constrained('lancamentos')->nullOnDelete();
            $table->json('dados_json');
            $table->json('erros_json')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'lancamento_import_lote_id']);
            $table->index(['tenant_id', 'matched_lancamento_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lancamento_import_linhas');
    }
};
