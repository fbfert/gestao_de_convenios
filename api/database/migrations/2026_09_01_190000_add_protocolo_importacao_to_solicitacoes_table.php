<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('solicitacoes', function (Blueprint $table) {
            // Chave de correspondência da importação por planilha: o código
            // de protocolo que a própria clínica já usa no Excel. Reimportar
            // a mesma planilha atualiza a solicitação em vez de duplicar —
            // sem CPF/carteirinha aqui, não existe outra chave natural.
            $table->string('protocolo_importacao')->nullable()->after('observacoes');

            $table->index(['tenant_id', 'protocolo_importacao']);
        });
    }

    public function down(): void
    {
        Schema::table('solicitacoes', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'protocolo_importacao']);
            $table->dropColumn('protocolo_importacao');
        });
    }
};
