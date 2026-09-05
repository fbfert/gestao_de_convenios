<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `solicitacao_documentos` vira vínculo puro: o arquivo em si (tipo, nome,
 * mime, path, metadata) já foi migrado pra `paciente_arquivos` na migration
 * anterior. Sem doctrine/dbal no projeto, a troca de NULL->NOT NULL usa SQL
 * cru (só no MySQL — o app não depende disso em tempo de teste no SQLite).
 */
return new class extends Migration
{
    public function up(): void
    {
        // Por este ponto todas as linhas (originais + backfill de órfãos) já
        // têm paciente_arquivo_id. Uma falha aqui indica bug no backfill —
        // melhor travar o deploy do que seguir com vínculo sem arquivo.
        $semArquivo = DB::table('solicitacao_documentos')->whereNull('paciente_arquivo_id')->count();

        if ($semArquivo > 0) {
            throw new RuntimeException("{$semArquivo} solicitacao_documentos sem paciente_arquivo_id após o backfill.");
        }

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE solicitacao_documentos MODIFY paciente_arquivo_id BIGINT UNSIGNED NOT NULL');
        }

        Schema::table('solicitacao_documentos', function (Blueprint $table) {
            // O índice em `tipo` (migration original) impede o drop da coluna.
            $table->dropIndex(['tenant_id', 'tipo']);
            $table->dropColumn(['tipo', 'nome_original', 'mime', 'path', 'metadata']);
        });
    }

    public function down(): void
    {
        Schema::table('solicitacao_documentos', function (Blueprint $table) {
            $table->string('tipo')->nullable();
            $table->string('nome_original')->nullable();
            $table->string('mime')->nullable();
            $table->string('path')->nullable();
            $table->json('metadata')->nullable();
            $table->index(['tenant_id', 'tipo']);
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE solicitacao_documentos MODIFY paciente_arquivo_id BIGINT UNSIGNED NULL');
        }
    }
};
