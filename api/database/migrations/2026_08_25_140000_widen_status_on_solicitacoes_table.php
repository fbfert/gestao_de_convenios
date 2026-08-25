<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Abre espaço pro novo status 'ready_for_automation' e muda o significado de
 * 'approved': antes era "liberado internamente", agora é "guias dos itens
 * todas aprovadas/finalizadas pela operadora" (ver SolicitacaoService::
 * sincronizarStatusComGuias). SQLite já grava 'status' como varchar livre
 * (sem CHECK), então só o MySQL precisa da troca de tipo de coluna.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE solicitacoes MODIFY status VARCHAR(50) NOT NULL DEFAULT 'under_review'");
        }

        $this->reclassificarAprovadas();
    }

    public function down(): void
    {
        DB::table('solicitacoes')->where('status', 'ready_for_automation')->update(['status' => 'approved']);

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE solicitacoes MODIFY status ENUM('under_review','approved','denied') NOT NULL DEFAULT 'under_review'");
        }
    }

    /**
     * Quem estava 'approved' sob o significado antigo (liberado internamente)
     * e não tem todas as guias dos itens aprovadas/finalizadas volta a ser
     * 'ready_for_automation' — preserva o comportamento de hoje (habilita
     * "Enviar para Unimed" / geração manual de guia).
     */
    private function reclassificarAprovadas(): void
    {
        $solicitacoes = DB::table('solicitacoes')->where('status', 'approved')->pluck('id');

        foreach ($solicitacoes as $solicitacaoId) {
            $totalItens = DB::table('solicitacao_itens')->where('solicitacao_id', $solicitacaoId)->count();

            $guiasAprovadas = $totalItens === 0 ? 0 : DB::table('solicitacao_itens')
                ->join('guias', 'guias.solicitacao_item_id', '=', 'solicitacao_itens.id')
                ->where('solicitacao_itens.solicitacao_id', $solicitacaoId)
                ->whereIn('guias.status', ['approved', 'finalized'])
                ->count();

            if ($totalItens === 0 || $guiasAprovadas < $totalItens) {
                DB::table('solicitacoes')->where('id', $solicitacaoId)->update(['status' => 'ready_for_automation']);
            }
        }
    }
};
