<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Uma solicitação pode citar mais de um CID (comorbidades, por exemplo) —
 * até aqui era 1-pra-1 via `solicitacoes.cid_id`. Faz o backfill dos dados
 * reais antes de derrubar a coluna antiga na mesma migration: só 2 linhas em
 * produção neste momento (25/08/2026), sem risco de perda.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cid_solicitacao', function (Blueprint $table) {
            $table->id();
            $table->foreignId('solicitacao_id')->constrained('solicitacoes')->cascadeOnDelete();
            $table->foreignId('cid_id')->constrained('cids')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['solicitacao_id', 'cid_id']);
        });

        DB::table('solicitacoes')
            ->whereNotNull('cid_id')
            ->select('id', 'cid_id')
            ->orderBy('id')
            ->get()
            ->each(function ($solicitacao) {
                DB::table('cid_solicitacao')->insert([
                    'solicitacao_id' => $solicitacao->id,
                    'cid_id' => $solicitacao->cid_id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });

        Schema::table('solicitacoes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cid_id');
        });
    }

    public function down(): void
    {
        Schema::table('solicitacoes', function (Blueprint $table) {
            $table->foreignId('cid_id')->nullable()->after('cid')->constrained('cids')->restrictOnDelete();
        });

        DB::table('cid_solicitacao')
            ->orderBy('solicitacao_id')
            ->select('solicitacao_id', 'cid_id')
            ->get()
            ->groupBy('solicitacao_id')
            ->each(function ($linhas, $solicitacaoId) {
                DB::table('solicitacoes')
                    ->where('id', $solicitacaoId)
                    ->update(['cid_id' => $linhas->first()->cid_id]);
            });

        Schema::dropIfExists('cid_solicitacao');
    }
};
