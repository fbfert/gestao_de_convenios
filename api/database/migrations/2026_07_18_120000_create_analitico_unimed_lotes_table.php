<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analitico_unimed_lotes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('arquivo_nome_original');
            $table->string('arquivo_path')->nullable();
            $table->string('status')->default('importado');
            $table->timestamp('importado_em')->nullable();
            $table->unsignedInteger('total_linhas_analitico')->default(0);
            $table->unsignedInteger('total_linhas_glosa')->default(0);
            $table->unsignedInteger('total_linhas_conciliacao')->default(0);
            $table->decimal('total_pago', 12, 2)->default(0);
            $table->decimal('total_glosado', 12, 2)->default(0);
            $table->decimal('saldo_total', 12, 2)->default(0);
            $table->json('cabecalho_json')->nullable();
            $table->json('planilhas_json')->nullable();
            $table->json('totais_json')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'importado_em']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analitico_unimed_lotes');
    }
};
