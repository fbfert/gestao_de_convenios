<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guias', function (Blueprint $table) {
            // Nulo = guia negada ainda aparece no alerta (Guias/Dashboard).
            // Preenchido quando alguem confirma um dos tres botoes do modal
            // (Nova Solicitação / Já solicitei / Pode ocultar) — os tres so
            // diferem em navegar ou não pro formulário de nova solicitação,
            // nenhum grava motivo separado.
            $table->timestamp('alerta_negacao_ocultado_em')->nullable()->after('observacoes');
        });
    }

    public function down(): void
    {
        Schema::table('guias', function (Blueprint $table) {
            $table->dropColumn('alerta_negacao_ocultado_em');
        });
    }
};
