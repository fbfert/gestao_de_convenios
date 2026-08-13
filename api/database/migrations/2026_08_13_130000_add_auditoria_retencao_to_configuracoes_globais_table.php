<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configuracoes_globais', function (Blueprint $table) {
            // 12 meses cobrem um ciclo fiscal inteiro. Sem prazo, auditar a
            // operação faria a tabela crescer para sempre.
            $table->unsignedSmallInteger('auditoria_retencao_meses')->default(12)->after('itens_por_pagina');
        });
    }

    public function down(): void
    {
        Schema::table('configuracoes_globais', function (Blueprint $table) {
            $table->dropColumn('auditoria_retencao_meses');
        });
    }
};
