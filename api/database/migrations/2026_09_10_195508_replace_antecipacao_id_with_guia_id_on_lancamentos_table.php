<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lancamento passa a pertencer direto à Guia — o balde de cota (Antecipacao)
 * sai de circulação (ver migration seguinte). 0 lançamentos reais no banco
 * até aqui, então isso é troca de coluna, não backfill.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lancamentos', function (Blueprint $table) {
            // Índice antes da FK/coluna: no SQLite (suite de testes) o drop de
            // coluna reconstrói a tabela, e ele tropeça se um índice ainda
            // referenciar a coluna que está saindo.
            $table->dropIndex(['antecipacao_id', 'status']);
            $table->dropForeign(['antecipacao_id']);
            $table->dropColumn('antecipacao_id');
            $table->foreignId('guia_id')->after('tenant_id')->constrained('guias')->restrictOnDelete();
            $table->index(['guia_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('lancamentos', function (Blueprint $table) {
            $table->dropIndex(['guia_id', 'status']);
            $table->dropForeign(['guia_id']);
            $table->dropColumn('guia_id');
            $table->foreignId('antecipacao_id')->after('tenant_id')->constrained('antecipacoes')->restrictOnDelete();
            $table->index(['antecipacao_id', 'status']);
        });
    }
};
