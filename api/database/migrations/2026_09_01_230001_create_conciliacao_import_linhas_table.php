<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conciliacao_import_linhas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('conciliacao_import_lote_id')->constrained('conciliacao_import_lotes')->cascadeOnDelete();
            $table->unsignedInteger('linha');
            $table->string('status')->default('valida');
            $table->foreignId('matched_conciliacao_id')->nullable()->constrained('conciliacoes_financeiras')->nullOnDelete();
            $table->json('dados_json');
            $table->json('erros_json')->nullable();
            $table->timestamps();

            // Nome explicito e curto: o gerado pelo Laravel passaria de 64
            // caracteres e MySQL/MariaDB reprovam. Invisivel em SQLite, que e
            // onde a suite roda.
            $table->index(['tenant_id', 'conciliacao_import_lote_id'], 'cil_tenant_lote_index');
            $table->index(['tenant_id', 'matched_conciliacao_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conciliacao_import_linhas');
    }
};
