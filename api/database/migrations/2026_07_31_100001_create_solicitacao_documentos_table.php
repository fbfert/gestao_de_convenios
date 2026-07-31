<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('solicitacao_documentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignId('solicitacao_id')->constrained('solicitacoes')->cascadeOnDelete();
            $table->foreignId('solicitacao_item_id')->nullable()->constrained('solicitacao_itens')->cascadeOnDelete();
            $table->string('tipo');
            $table->string('nome_original');
            $table->string('mime')->nullable();
            $table->string('path');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'solicitacao_id']);
            $table->index(['tenant_id', 'solicitacao_item_id']);
            $table->index(['tenant_id', 'tipo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('solicitacao_documentos');
    }
};
