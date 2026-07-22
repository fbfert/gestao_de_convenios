<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('solicitacoes', function (Blueprint $table) {
            $table->string('pedido_medico_path')->nullable()->after('observacoes');
            $table->string('pedido_medico_nome_original')->nullable()->after('pedido_medico_path');
            $table->string('pedido_medico_mime')->nullable()->after('pedido_medico_nome_original');
            $table->json('pedido_medico_ai_result')->nullable()->after('pedido_medico_mime');
        });
    }

    public function down(): void
    {
        Schema::table('solicitacoes', function (Blueprint $table) {
            $table->dropColumn([
                'pedido_medico_path',
                'pedido_medico_nome_original',
                'pedido_medico_mime',
                'pedido_medico_ai_result',
            ]);
        });
    }
};
