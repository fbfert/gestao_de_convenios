<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Prepara `solicitacao_documentos` para virar vínculo (ver migration
 * seguinte, que derruba as colunas de arquivo): cada linha existente ganha um
 * `paciente_arquivos` correspondente, e passa a apontar pra ele.
 *
 * Dois lotes:
 *  1) toda linha de solicitacao_documentos hoje;
 *  2) solicitações de antes da tabela existir, que só têm o pedido médico
 *     nas colunas legadas de `solicitacoes` (pedido_medico_path) e nenhuma
 *     linha correspondente — sem isto, esse anexo ficaria inacessível assim
 *     que as colunas legadas forem derrubadas.
 *
 * Precisa rodar antes da migration que remove tipo/nome_original/mime/path
 * de solicitacao_documentos: os dois lotes ainda leem essas colunas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('solicitacao_documentos', function (Blueprint $table) {
            $table->foreignId('paciente_arquivo_id')->nullable()
                ->after('solicitacao_item_id')
                ->constrained('paciente_arquivos')->restrictOnDelete();
        });

        $this->migrarDocumentosExistentes();
        $this->backfillPedidosMedicosOrfaos();
    }

    public function down(): void
    {
        Schema::table('solicitacao_documentos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('paciente_arquivo_id');
        });
    }

    /**
     * Cada linha de solicitacao_documentos vira um paciente_arquivos (dono: o
     * paciente da solicitação), e a linha original passa a apontar pra ele.
     */
    private function migrarDocumentosExistentes(): void
    {
        DB::table('solicitacao_documentos as sd')
            ->join('solicitacoes as s', 's.id', '=', 'sd.solicitacao_id')
            ->select([
                'sd.id', 'sd.tenant_id', 'sd.tipo', 'sd.nome_original',
                'sd.mime', 'sd.path', 'sd.metadata', 'sd.created_at', 'sd.updated_at',
                's.paciente_id',
            ])
            ->orderBy('sd.id')
            ->chunkById(200, function ($linhas) {
                foreach ($linhas as $linha) {
                    $arquivoId = DB::table('paciente_arquivos')->insertGetId([
                        'tenant_id' => $linha->tenant_id,
                        'paciente_id' => $linha->paciente_id,
                        'tipo' => $linha->tipo,
                        'nome_original' => $linha->nome_original,
                        'mime' => $linha->mime,
                        'path' => $linha->path,
                        'metadata' => $linha->metadata,
                        'created_at' => $linha->created_at ?? now(),
                        'updated_at' => $linha->updated_at ?? now(),
                    ]);

                    DB::table('solicitacao_documentos')
                        ->where('id', $linha->id)
                        ->update(['paciente_arquivo_id' => $arquivoId]);
                }
            }, 'sd.id', 'id');
    }

    /**
     * Solicitações anteriores à tabela solicitacao_documentos: têm
     * pedido_medico_path preenchido e nenhuma linha correspondente.
     */
    private function backfillPedidosMedicosOrfaos(): void
    {
        DB::table('solicitacoes')
            ->whereNotNull('pedido_medico_path')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('solicitacao_documentos')
                    ->whereColumn('solicitacao_documentos.solicitacao_id', 'solicitacoes.id')
                    ->where('solicitacao_documentos.tipo', 'pedido_medico');
            })
            ->orderBy('id')
            ->chunkById(200, function ($orfas) {
                foreach ($orfas as $solicitacao) {
                    $nomeOriginal = $solicitacao->pedido_medico_nome_original
                        ?? basename($solicitacao->pedido_medico_path);

                    $arquivoId = DB::table('paciente_arquivos')->insertGetId([
                        'tenant_id' => $solicitacao->tenant_id,
                        'paciente_id' => $solicitacao->paciente_id,
                        'tipo' => 'pedido_medico',
                        'nome_original' => $nomeOriginal,
                        'mime' => $solicitacao->pedido_medico_mime,
                        'path' => $solicitacao->pedido_medico_path,
                        'metadata' => $solicitacao->pedido_medico_ai_result,
                        'created_at' => $solicitacao->created_at ?? now(),
                        'updated_at' => $solicitacao->updated_at ?? now(),
                    ]);

                    DB::table('solicitacao_documentos')->insert([
                        'tenant_id' => $solicitacao->tenant_id,
                        'solicitacao_id' => $solicitacao->id,
                        'solicitacao_item_id' => null,
                        'paciente_arquivo_id' => $arquivoId,
                        'tipo' => 'pedido_medico',
                        'nome_original' => $nomeOriginal,
                        'mime' => $solicitacao->pedido_medico_mime,
                        'path' => $solicitacao->pedido_medico_path,
                        'metadata' => $solicitacao->pedido_medico_ai_result,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            });
    }
};
