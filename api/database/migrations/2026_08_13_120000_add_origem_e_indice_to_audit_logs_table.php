<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            // Só os eventos de acesso (login, logout, recusa por permissão)
            // preenchem estas colunas: nas demais ações o autor já identifica
            // quem foi, e guardar navegação de todo mundo o tempo todo seria
            // dado pessoal a mais sem uso previsto.
            $table->string('ip', 45)->nullable()->after('payload');
            $table->string('user_agent', 255)->nullable()->after('ip');

            // O índice existente é (tenant_id, entidade, entidade_id), que não
            // serve para o filtro por período nem para o expurgo diário —
            // ambos varrem por data dentro do tenant.
            $table->index(['tenant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'created_at']);
            $table->dropColumn(['ip', 'user_agent']);
        });
    }
};
