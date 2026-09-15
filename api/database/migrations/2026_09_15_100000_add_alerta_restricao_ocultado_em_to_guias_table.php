<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guias', function (Blueprint $table) {
            // Mesma semântica de `alerta_negacao_ocultado_em`, para o status
            // `needs_verification` (Verificar Restrição): nulo = a guia ainda
            // aparece no alerta de Guias e no card do Dashboard; preenchido
            // quando alguém confirma uma das três ações do diálogo.
            //
            // Coluna própria, e não um campo genérico de "alerta ocultado":
            // uma guia pode ser negada depois de ter tido restrição tratada, e
            // nesse caso o alerta de negação precisa aparecer do mesmo jeito.
            $table->timestamp('alerta_restricao_ocultado_em')->nullable()->after('alerta_negacao_ocultado_em');
        });
    }

    public function down(): void
    {
        Schema::table('guias', function (Blueprint $table) {
            $table->dropColumn('alerta_restricao_ocultado_em');
        });
    }
};
