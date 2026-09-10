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
            // FK antes do índice: no MySQL o índice composto sustenta a
            // constraint de FK, e ele recusa (erro 1553) dropar o índice
            // enquanto a FK ainda depender dele. Drop tem que ser
            // FK -> índice -> coluna, nessa ordem, nos dois motores.
            $table->dropForeign(['antecipacao_id']);
            $table->dropIndex(['antecipacao_id', 'status']);
            $table->dropColumn('antecipacao_id');
            $table->foreignId('guia_id')->after('tenant_id')->constrained('guias')->restrictOnDelete();
            $table->index(['guia_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('lancamentos', function (Blueprint $table) {
            $table->dropForeign(['guia_id']);
            $table->dropIndex(['guia_id', 'status']);
            $table->dropColumn('guia_id');
            $table->foreignId('antecipacao_id')->after('tenant_id')->constrained('antecipacoes')->restrictOnDelete();
            $table->index(['antecipacao_id', 'status']);
        });
    }
};
