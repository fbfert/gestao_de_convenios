<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rastro de unificação de cadastros duplicados (ver PacienteMergeService).
 * Perdedor nunca é apagado — fica ativo=false e aponta pra quem o absorveu,
 * mesmo padrão de "inativo" que a tela de Pacientes já usa pra não apagar
 * histórico de verdade.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pacientes', function (Blueprint $table) {
            $table->foreignId('mesclado_em_id')->nullable()->after('clinica_status')
                ->constrained('pacientes')->nullOnDelete();
            $table->timestamp('mesclado_em')->nullable()->after('mesclado_em_id');
        });
    }

    public function down(): void
    {
        Schema::table('pacientes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('mesclado_em_id');
            $table->dropColumn('mesclado_em');
        });
    }
};
