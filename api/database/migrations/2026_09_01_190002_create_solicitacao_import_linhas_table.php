<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('solicitacao_import_linhas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('solicitacao_import_lote_id')->constrained('solicitacao_import_lotes')->cascadeOnDelete();
            $table->unsignedInteger('linha');
            // Linhas com o mesmo "grupo" (protocolo, ou a própria linha
            // quando não há protocolo) viram itens da mesma Solicitação.
            $table->string('grupo');
            $table->string('status')->default('valida');
            $table->foreignId('matched_solicitacao_id')->nullable()->constrained('solicitacoes')->nullOnDelete();
            $table->json('dados_json');
            $table->json('erros_json')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'solicitacao_import_lote_id']);
            $table->index(['tenant_id', 'matched_solicitacao_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('solicitacao_import_linhas');
    }
};
