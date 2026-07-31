<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guias', function (Blueprint $table) {
            $table->foreignId('solicitacao_item_id')
                ->nullable()
                ->after('solicitacao_id')
                ->constrained('solicitacao_itens')
                ->nullOnDelete();

            $table->index(['tenant_id', 'solicitacao_item_id']);
        });
    }

    public function down(): void
    {
        Schema::table('guias', function (Blueprint $table) {
            $table->dropForeign(['solicitacao_item_id']);
            $table->dropIndex(['tenant_id', 'solicitacao_item_id']);
            $table->dropColumn('solicitacao_item_id');
        });
    }
};
