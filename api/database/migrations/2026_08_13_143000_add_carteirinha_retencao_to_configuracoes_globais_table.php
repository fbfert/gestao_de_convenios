<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configuracoes_globais', function (Blueprint $table) {
            // Imagem de documento pessoal: o padrão curto é proposital.
            $table->unsignedSmallInteger('carteirinha_retencao_dias')->default(30)->after('auditoria_retencao_meses');
        });
    }

    public function down(): void
    {
        Schema::table('configuracoes_globais', function (Blueprint $table) {
            $table->dropColumn('carteirinha_retencao_dias');
        });
    }
};
