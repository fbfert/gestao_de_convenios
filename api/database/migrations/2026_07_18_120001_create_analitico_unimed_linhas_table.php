<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analitico_unimed_linhas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('analitico_unimed_lote_id')->constrained('analitico_unimed_lotes')->cascadeOnDelete();
            $table->unsignedInteger('linha')->nullable();
            $table->string('origem');
            $table->string('natureza');
            $table->boolean('processavel')->default(true);
            $table->string('numero_guia_operadora')->nullable();
            $table->string('numero_guia_prestador')->nullable();
            $table->string('codigo')->nullable();
            $table->string('usuario')->nullable();
            $table->string('data_autorizacao')->nullable();
            $table->string('data_realizacao')->nullable();
            $table->string('procedimento')->nullable();
            $table->string('descricao_procedimento')->nullable();
            $table->string('qtd')->nullable();
            $table->unsignedInteger('qtd_normalizada')->default(0);
            $table->string('tipo')->nullable();
            $table->string('motivo')->nullable();
            $table->string('valor')->nullable();
            $table->decimal('valor_normalizado', 12, 2)->default(0);
            $table->string('local_realizacao')->nullable();
            $table->string('chave_conciliacao')->nullable();
            $table->json('dados_json')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'analitico_unimed_lote_id']);
            $table->index(['tenant_id', 'numero_guia_operadora']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analitico_unimed_linhas');
    }
};
