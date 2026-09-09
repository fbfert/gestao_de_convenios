<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cada peca do sistema que pode estar viva ou morta e uma linha aqui.
 *
 * A tabela guarda o ultimo heartbeat, e nao o estado: componente que morre para
 * de escrever, entao um estado gravado diria "ok" para sempre. Derivar de
 * `now() - ultimo_heartbeat_em` faz o silencio ser a evidencia. Ver
 * openspec/changes/saude-componentes/design.md.
 *
 * Componente e linha de tabela, e nao coluna nem classe, porque a automacao nao
 * vai ser so Unimed (ADR-02): conector novo entra por seed e aparece no card sem
 * alterar o frontend.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saude_componentes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->string('chave');
            $table->string('nome');
            $table->string('tipo'); // worker|scheduler|queue|smtp|connector
            $table->foreignId('convenio_id')->nullable()->constrained('convenios')->nullOnDelete();
            $table->timestamp('ultimo_heartbeat_em')->nullable();
            $table->string('ultimo_status')->nullable(); // ok|erro
            $table->text('ultima_mensagem')->nullable();
            // Dado por componente, nunca constante: o intervalo precisa refletir
            // a frequencia do agendamento daquela peca, e nao a do trabalho da
            // clinica. Ate 1x = healthy, ate 3x = warning, acima = down.
            $table->unsignedInteger('intervalo_esperado_segundos');
            $table->boolean('ativo')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'chave']);
            // O card do dashboard le os ativos do tenant, e e o unico caminho
            // de leitura quente (polling de 30s).
            $table->index(['tenant_id', 'ativo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saude_componentes');
    }
};
