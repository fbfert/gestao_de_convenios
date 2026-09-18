<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Histórico de estado dos componentes monitorados.
 *
 * `saude_componentes` guarda um retrato do agora (`ultimo_heartbeat_em`,
 * `ultimo_status`) — suficiente para o card do painel, inútil para a pergunta
 * "quanto tempo a fila ficou fora em setembro?".
 *
 * Grava a MUDANÇA de estado, e não cada sinal de vida: o heartbeat é de minuto
 * em minuto, e uma linha por heartbeat seriam milhões para responder uma
 * pergunta sobre transições. Só a mudança dá a mesma resposta com três ordens
 * de grandeza menos linha.
 *
 * Consequência assumida: o relatório só enxerga do deploy em diante. Período
 * anterior à primeira linha desta tabela fica sem resposta — e a aba diz isso,
 * em vez de mostrar zero como se fosse "sempre no ar".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saude_componente_eventos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('saude_componente_id')->constrained('saude_componentes')->cascadeOnDelete();
            // healthy | warning | down, os mesmos de SaudeComponente.
            $table->string('estado');
            $table->timestamp('ocorrido_em');
            $table->string('mensagem')->nullable();
            $table->timestamp('created_at')->nullable();

            // A consulta do relatório é sempre "os eventos deste componente,
            // nesta janela, em ordem" — o índice cobre os três de uma vez.
            $table->index(['tenant_id', 'saude_componente_id', 'ocorrido_em'], 'saude_eventos_componente_periodo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saude_componente_eventos');
    }
};
