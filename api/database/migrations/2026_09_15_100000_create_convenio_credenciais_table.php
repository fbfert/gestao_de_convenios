<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Credencial de automação por convênio.
 *
 * A chave é `tenant_id + convenio_id`, e não `tenant_id` sozinho como em
 * `unimed_rda_credentials`. O `unique('tenant_id')` de lá é exatamente o
 * defeito que este change existe para corrigir: em 14/09/2026 um
 * WORKER_INTERNAL_FATAL num único item fez o disjuntor pausar a credencial do
 * tenant inteiro, porque só havia uma. Com um segundo convênio automatizado,
 * derrubaria também o que não tinha problema nenhum.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('convenio_credenciais', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignId('convenio_id')->constrained('convenios')->restrictOnDelete();

            // Driver da CREDENCIAL, não `convenios.connector_driver`. Aquele é o
            // interruptor da automação, com doze consumidores; este só diz quais
            // campos a credencial tem, conforme o ConvenioDriverCatalog.
            $table->string('driver');

            // JSON cifrado inteiro, e não coluna por campo: perde-se a busca por
            // login — que ninguém faz — e ganha-se campo novo de driver futuro
            // sem migration, inclusive o arquivo de um certificado digital, se o
            // WebService do SC Saúde exigir.
            $table->text('credenciais')->nullable();

            $table->boolean('ativo')->default(true);
            $table->timestamp('automation_paused_at')->nullable();
            $table->string('automation_paused_reason')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'convenio_id']);
            $table->index(['tenant_id', 'driver']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('convenio_credenciais');
    }
};
