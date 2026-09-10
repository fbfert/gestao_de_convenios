<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Antecipação (balde de cota por guia: qtd_autorizada/qtd_utilizada/ciclo)
 * deixa de existir — reformulada para outra coisa (gerar o próximo ciclo de
 * guias, fora do escopo desta migration). A cota de sessões vira conta ao
 * vivo (Guia.sessoes_autorizadas vs. contagem de Lancamentos), sem tabela.
 *
 * Só existiam 4 linhas em produção, todas 'open' e sem nenhum Lancamento
 * vinculado (lancamentos.antecipacao_id já saiu na migration anterior) —
 * nada de histórico real se perde aqui.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('antecipacao_import_linhas');
        Schema::dropIfExists('antecipacao_import_lotes');
        Schema::dropIfExists('antecipacoes');
    }

    public function down(): void
    {
        Schema::create('antecipacoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignId('guia_id')->constrained('guias')->restrictOnDelete();
            $table->foreignId('paciente_id')->constrained('pacientes')->restrictOnDelete();
            $table->foreignId('convenio_id')->constrained('convenios')->restrictOnDelete();
            $table->date('ciclo_inicio');
            $table->date('ciclo_fim');
            $table->unsignedInteger('qtd_autorizada');
            $table->unsignedInteger('qtd_utilizada')->default(0);
            $table->enum('status', ['open', 'closed'])->default('open');
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['paciente_id', 'ciclo_inicio', 'ciclo_fim']);
        });

        Schema::create('antecipacao_import_lotes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->string('arquivo_nome_original');
            $table->string('arquivo_path')->nullable();
            $table->string('status')->default('previsualizado');
            $table->timestamp('confirmado_em')->nullable();
            $table->unsignedInteger('total_linhas')->default(0);
            $table->unsignedInteger('total_validas')->default(0);
            $table->unsignedInteger('total_invalidas')->default(0);
            $table->unsignedInteger('total_importados')->default(0);
            $table->unsignedInteger('total_atualizados')->default(0);
            $table->unsignedInteger('total_ignorados')->default(0);
            $table->timestamps();
        });

        Schema::create('antecipacao_import_linhas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignId('antecipacao_import_lote_id')->constrained('antecipacao_import_lotes')->cascadeOnDelete();
            $table->unsignedInteger('linha');
            $table->string('status')->default('valida');
            $table->foreignId('matched_antecipacao_id')->nullable()->constrained('antecipacoes')->nullOnDelete();
            $table->longText('dados_json');
            $table->longText('erros_json')->nullable();
            $table->timestamps();
        });
    }
};
