<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Modelo padrão da conexão OpenAI.
 *
 * Antes só existia `ai_prompt_templates.model_id`, e quando o prompt vinha sem
 * modelo o PedidoMedicoAiService caía num literal no código
 * (`?: 'gpt-5.6-luna'`). Trocar o modelo de todo o tenant exigia editar prompt
 * por prompt, ou alterar código e fazer deploy.
 *
 * A ordem de resolução passa a ser:
 *   modelo do prompt  →  modelo padrão da conexão  →  literal do código
 *
 * O literal continua como último recurso para uma instalação que nunca
 * configurou nada não quebrar sozinha.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_openai_settings', function (Blueprint $table) {
            $table->string('model_id')->nullable()->after('project_id');
        });
    }

    public function down(): void
    {
        Schema::table('ai_openai_settings', function (Blueprint $table) {
            $table->dropColumn('model_id');
        });
    }
};
