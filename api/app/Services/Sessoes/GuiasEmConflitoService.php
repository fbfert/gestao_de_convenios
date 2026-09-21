<?php

namespace App\Services\Sessoes;

use App\Models\Lancamento;
use Illuminate\Support\Collection;

/**
 * Quais guias têm, entre as sessões já gravadas, alguma que contradiz as
 * regras de agenda.
 *
 * Existe por causa do passivo: as regras valem para o que é gravado de agora
 * em diante, então o que entrou antes delas pode estar em conflito e ninguém
 * vai descobrir isso até tentar finalizar a guia na operadora — tarde demais,
 * com a folha já arquivada. O alerta do dashboard é o que torna esse passivo
 * visível enquanto ainda dá para corrigir.
 *
 * Roda o mesmo avaliador das telas que gravam, e não uma tradução da regra em
 * SQL, porque duas versões da mesma regra divergem. O que o SQL faz é só
 * separar os dias que PODEM ter conflito: qualquer conflito exige ao menos
 * duas sessões do mesmo paciente no mesmo dia, e essa pergunta uma agregação
 * responde barato. O avaliador roda depois, sobre um punhado de linhas.
 */
class GuiasEmConflitoService
{
    public function __construct(
        private readonly AvaliadorDeAgenda $avaliador,
    ) {
    }

    public function contar(int $tenantId): int
    {
        return $this->guiaIds($tenantId)->count();
    }

    /**
     * Ids das guias com sessão em conflito.
     *
     * @return Collection<int, int>
     */
    public function guiaIds(int $tenantId): Collection
    {
        $diasSuspeitos = $this->diasComMaisDeUmaSessao($tenantId);

        if ($diasSuspeitos->isEmpty()) {
            return collect();
        }

        $sessoes = Lancamento::query()
            ->where('tenant_id', $tenantId)
            ->where('status', 'completed')
            ->whereIn('data_sessao', $diasSuspeitos->pluck('data_sessao')->unique()->values())
            ->whereHas('guia', fn ($query) => $query->whereIn('paciente_id', $diasSuspeitos->pluck('paciente_id')->unique()->values()))
            ->with(['guia.especialidade'])
            ->get()
            ->map(fn (Lancamento $lancamento) => SessaoCandidata::deLancamento($lancamento));

        $guias = collect();

        // Um paciente por vez: o avaliador compara sessões do MESMO paciente, e
        // avaliar a clínica inteira de uma vez seria uma comparação de todos
        // contra todos sem necessidade nenhuma.
        foreach ($sessoes->groupBy('pacienteId') as $doPaciente) {
            $resultado = $this->avaliador->avaliarIsolado($doPaciente->values()->all());

            foreach ($resultado->conflitos as $conflito) {
                // As DUAS guias do par entram: `guiaId` do conflito é a da
                // sessão mais antiga, e `referencia` aponta a mais recente.
                // Contar só uma delas esconderia metade do passivo.
                if ($conflito->guiaId !== null) {
                    $guias->push($conflito->guiaId);
                }

                $maisRecente = $doPaciente->firstWhere('referencia', $conflito->referencia);

                if ($maisRecente?->guiaId !== null) {
                    $guias->push($maisRecente->guiaId);
                }
            }
        }

        return $guias->unique()->values();
    }

    /**
     * Pares (paciente, dia) com mais de uma sessão realizada.
     *
     * É a condição necessária de qualquer conflito: sem duas sessões no mesmo
     * dia não há intervalo curto nem limite diário estourado. Só um par de
     * sessões atravessando a meia-noite escaparia daqui, e esse caso continua
     * coberto onde importa — na hora de gravar e na de finalizar.
     *
     * @return Collection<int, object{paciente_id: int, data_sessao: string}>
     */
    private function diasComMaisDeUmaSessao(int $tenantId): Collection
    {
        return Lancamento::query()
            ->where('lancamentos.tenant_id', $tenantId)
            ->where('lancamentos.status', 'completed')
            ->join('guias', 'guias.id', '=', 'lancamentos.guia_id')
            ->groupBy('guias.paciente_id', 'lancamentos.data_sessao')
            ->havingRaw('COUNT(*) > 1')
            ->select('guias.paciente_id', 'lancamentos.data_sessao')
            ->get();
    }
}
