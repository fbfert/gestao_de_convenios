<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('automacao_execucoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignId('solicitacao_item_id')->nullable()->constrained('solicitacao_itens')->nullOnDelete();
            $table->foreignId('guia_id')->nullable()->constrained('guias')->nullOnDelete();
            $table->string('operacao');
            $table->string('status')->default('queued');
            $table->string('idempotency_key');
            $table->json('payload')->nullable();
            $table->json('resultado')->nullable();
            $table->string('erro_codigo')->nullable();
            $table->text('erro_mensagem')->nullable();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->foreignId('parent_id')->nullable()->constrained('automacao_execucoes')->nullOnDelete();
            $table->timestamps();

            $table->unique(['tenant_id', 'idempotency_key']);
            $table->index(['tenant_id', 'operacao', 'status']);
            $table->index(['tenant_id', 'solicitacao_item_id']);
            $table->index(['tenant_id', 'guia_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automacao_execucoes');
    }
};
