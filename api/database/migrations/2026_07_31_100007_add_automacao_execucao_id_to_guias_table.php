<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guias', function (Blueprint $table) {
            $table->foreignId('automacao_execucao_id')
                ->nullable()
                ->after('solicitacao_item_id')
                ->constrained('automacao_execucoes')
                ->nullOnDelete();

            $table->index(['tenant_id', 'automacao_execucao_id']);
        });
    }

    public function down(): void
    {
        Schema::table('guias', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'automacao_execucao_id']);
            $table->dropConstrainedForeignId('automacao_execucao_id');
        });
    }
};
