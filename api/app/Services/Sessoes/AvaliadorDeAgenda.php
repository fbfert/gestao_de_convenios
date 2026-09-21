<?php

namespace App\Services\Sessoes;

use App\Models\Lancamento;
use Illuminate\Support\Collection;

/**
 * As regras de quando uma sessão pode ter acontecido.
 *
 * São três, e elas se sustentam mutuamente: cinquenta minutos entre os
 * inícios de duas sessões do mesmo paciente; no máximo oito sessões da mesma
 * especialidade ABA no dia; no máximo uma quando a especialidade não é ABA.
 * Oito sessões ABA num dia só cabem porque o intervalo as espalha — as duas
 * regras são a mesma restrição vista de dois ângulos, e é por isso que moram
 * juntas aqui em vez de espalhadas por cada tela que grava sessão.
 *
 * O avaliador não grava nem recusa nada: devolve o que encontrou. Quem chamou
 * decide o que fazer — a confirmação da folha recusa, o pré-voo da finalização
 * recusa, o alerta do dashboard só conta.
 *
 * Duas escolhas que valem explicar:
 *
 * - Sessão sem hora de início não entra na conta de intervalo. Não dá para
 *   afirmar conflito entre coisas que não têm horário, e chutar "meia-noite"
 *   criaria conflito onde não há.
 * - O limite diário é por especialidade, não por paciente. O paciente pode
 *   ter fono e psicologia no mesmo dia; o que ele não pode é estar nas duas
 *   ao mesmo tempo — e disso cuida o intervalo, que é por paciente.
 */
class AvaliadorDeAgenda
{
    public const INTERVALO_MINIMO_MINUTOS = 50;

    public const LIMITE_DIARIO_ABA = 8;

    public const LIMITE_DIARIO_PADRAO = 1;

    /**
     * @param  array<int, SessaoCandidata>  $candidatas
     */
    public function avaliar(array $candidatas): ResultadoDeAgenda
    {
        if ($candidatas === []) {
            return new ResultadoDeAgenda([], []);
        }

        $gravadas = $this->sessoesJaGravadas($candidatas);

        return new ResultadoDeAgenda(
            conflitos: $this->conflitos($candidatas, $gravadas),
            avisos: $this->avisos($candidatas, $gravadas),
        );
    }

    /**
     * Avalia sessões já gravadas contra elas mesmas, sem ir buscar mais nada
     * no banco. É o que o alerta do dashboard precisa: a pergunta ali é se o
     * que está gravado se contradiz, não se um lote novo cabe.
     *
     * @param  array<int, SessaoCandidata>  $sessoes
     */
    public function avaliarIsolado(array $sessoes): ResultadoDeAgenda
    {
        return new ResultadoDeAgenda(
            conflitos: $this->conflitos($sessoes, collect()),
            avisos: [],
        );
    }

    /**
     * Só as sessões que podem ocupar horário: as realizadas. Cancelada e
     * não comparecida não aconteceram, então não disputam a agenda.
     *
     * A busca é restrita aos pacientes e aos dias que as candidatas tocam —
     * com uma folga de um dia de cada lado, porque uma sessão às 23:40 e
     * outra às 00:10 do dia seguinte estão a trinta minutos uma da outra.
     *
     * @param  array<int, SessaoCandidata>  $candidatas
     * @return Collection<int, SessaoCandidata>
     */
    private function sessoesJaGravadas(array $candidatas): Collection
    {
        $pacienteIds = collect($candidatas)->pluck('pacienteId')->unique()->values();
        $datas = collect($candidatas)->map(fn (SessaoCandidata $c) => $c->data);

        $ignorar = collect($candidatas)
            ->map(fn (SessaoCandidata $c) => $c->lancamentoId)
            ->filter()
            ->values()
            ->all();

        $query = Lancamento::query()
            ->where('status', 'completed')
            ->whereBetween('data_sessao', [
                $datas->min()->subDay()->toDateString(),
                $datas->max()->addDay()->toDateString(),
            ])
            ->whereHas('guia', fn ($q) => $q->whereIn('paciente_id', $pacienteIds))
            ->with(['guia.especialidade']);

        // Editar uma sessão não pode fazer ela conflitar consigo mesma.
        if ($ignorar !== []) {
            $query->whereNotIn('id', $ignorar);
        }

        return $query->get()->map(
            fn (Lancamento $lancamento) => SessaoCandidata::deLancamento($lancamento)
        );
    }

    /**
     * @param  array<int, SessaoCandidata>  $candidatas
     * @param  Collection<int, SessaoCandidata>  $gravadas
     * @return array<int, ConflitoDeAgenda>
     */
    private function conflitos(array $candidatas, Collection $gravadas): array
    {
        $conflitos = [];

        foreach ($this->intervalo($candidatas, $gravadas) as $conflito) {
            $conflitos[] = $conflito;
        }

        foreach ($this->limiteDiario($candidatas, $gravadas) as $conflito) {
            $conflitos[] = $conflito;
        }

        return $conflitos;
    }

    /**
     * Intervalo mínimo entre sessões do mesmo paciente, candidatas contra
     * candidatas e candidatas contra gravadas.
     *
     * Cada par é comparado uma vez só: duas sessões coladas rendem um
     * conflito, não dois relatos do mesmo problema.
     *
     * @param  array<int, SessaoCandidata>  $candidatas
     * @param  Collection<int, SessaoCandidata>  $gravadas
     * @return array<int, ConflitoDeAgenda>
     */
    private function intervalo(array $candidatas, Collection $gravadas): array
    {
        $conflitos = [];
        $total = count($candidatas);

        for ($i = 0; $i < $total; $i++) {
            $atual = $candidatas[$i];

            for ($j = $i + 1; $j < $total; $j++) {
                $conflito = $this->compararPar($atual, $candidatas[$j]);

                if ($conflito !== null) {
                    $conflitos[] = $conflito;
                }
            }

            foreach ($gravadas as $gravada) {
                if ($gravada->pacienteId !== $atual->pacienteId) {
                    continue;
                }

                $conflito = $this->compararPar($atual, $gravada);

                if ($conflito !== null) {
                    $conflitos[] = $conflito;
                }
            }
        }

        return $conflitos;
    }

    private function compararPar(SessaoCandidata $uma, SessaoCandidata $outra): ?ConflitoDeAgenda
    {
        if ($uma->pacienteId !== $outra->pacienteId) {
            return null;
        }

        $inicioUma = $uma->inicio();
        $inicioOutra = $outra->inicio();

        if ($inicioUma === null || $inicioOutra === null) {
            return null;
        }

        $minutos = (int) round(abs($inicioUma->diffInMinutes($inicioOutra)));

        if ($minutos >= self::INTERVALO_MINIMO_MINUTOS) {
            return null;
        }

        if ($minutos === 0) {
            return new ConflitoDeAgenda(
                tipo: ConflitoDeAgenda::TIPO_MESMO_HORARIO,
                referencia: $uma->referencia,
                referenciaOutra: $outra->referencia,
                mensagem: sprintf(
                    'Duas sessões do paciente em %s. O paciente não pode estar em dois atendimentos ao mesmo tempo.',
                    $outra->descricao(),
                ),
                guiaId: $outra->guiaId,
                guiaNumero: $outra->guiaNumero,
            );
        }

        /*
         * O conflito é reportado contra a sessão MAIS TARDE do par, e a
         * mensagem lê na ordem do relógio. As duas coisas pela mesma razão:
         * "a de 08:30 começa 30 minutos depois da de 08:00" é o que
         * aconteceu, e é a de 08:30 que o operador vai mover.
         */
        [$antes, $depois] = $inicioUma->lessThanOrEqualTo($inicioOutra)
            ? [$uma, $outra]
            : [$outra, $uma];

        return new ConflitoDeAgenda(
            tipo: ConflitoDeAgenda::TIPO_INTERVALO,
            referencia: $depois->referencia,
            referenciaOutra: $antes->referencia,
            mensagem: sprintf(
                'Sessão de %s começa %d minutos depois da sessão de %s. O mínimo é %d minutos entre os inícios.',
                $depois->descricao(),
                $minutos,
                $antes->descricao(),
                self::INTERVALO_MINIMO_MINUTOS,
            ),
            guiaId: $antes->guiaId,
            guiaNumero: $antes->guiaNumero,
        );
    }

    /**
     * Limite de sessões por especialidade por dia, contando candidatas e
     * gravadas juntas.
     *
     * O conflito é reportado contra as candidatas que estouram o limite — as
     * que já estavam gravadas não têm o que corrigir na tela de quem lança.
     *
     * @param  array<int, SessaoCandidata>  $candidatas
     * @param  Collection<int, SessaoCandidata>  $gravadas
     * @return array<int, ConflitoDeAgenda>
     */
    private function limiteDiario(array $candidatas, Collection $gravadas): array
    {
        $conflitos = [];
        $porGrupo = [];

        foreach ($candidatas as $candidata) {
            $porGrupo[$this->chaveDoDia($candidata)][] = $candidata;
        }

        foreach ($porGrupo as $chave => $doGrupo) {
            $primeira = $doGrupo[0];
            $limite = $primeira->ehAba() ? self::LIMITE_DIARIO_ABA : self::LIMITE_DIARIO_PADRAO;

            $jaGravadas = $gravadas
                ->filter(fn (SessaoCandidata $g) => $this->chaveDoDia($g) === $chave)
                ->count();

            $total = $jaGravadas + count($doGrupo);

            if ($total <= $limite) {
                continue;
            }

            // Passou do limite: as que sobraram são as últimas do grupo. Marcar
            // todas as candidatas do dia diria ao operador para corrigir
            // sessões que estão certas.
            $excedentes = array_slice($doGrupo, max(0, $limite - $jaGravadas));

            foreach ($excedentes as $excedente) {
                $conflitos[] = new ConflitoDeAgenda(
                    tipo: ConflitoDeAgenda::TIPO_LIMITE_DIARIO,
                    referencia: $excedente->referencia,
                    referenciaOutra: null,
                    mensagem: sprintf(
                        '%s em %s: o limite é de %d %s por dia nessa especialidade, e o dia ficaria com %d.',
                        $excedente->especialidadeNome ?? 'Especialidade',
                        $excedente->data->format('d/m/Y'),
                        $limite,
                        $limite === 1 ? 'sessão' : 'sessões',
                        $total,
                    ),
                    guiaId: $excedente->guiaId,
                    guiaNumero: $excedente->guiaNumero,
                );
            }
        }

        return $conflitos;
    }

    /**
     * Especialidade sem id cai no nome, e sem nenhum dos dois vira grupo
     * próprio por guia — sem isso, sessões de especialidades desconhecidas
     * seriam somadas como se fossem a mesma.
     */
    private function chaveDoDia(SessaoCandidata $sessao): string
    {
        $especialidade = $sessao->especialidadeId !== null
            ? 'id:'.$sessao->especialidadeId
            : 'nome:'.($sessao->especialidadeNome ?? 'guia:'.($sessao->guiaId ?? '?'));

        return $sessao->pacienteId.'|'.$especialidade.'|'.$sessao->dataString();
    }

    /**
     * Choque de agenda do executante: mesmo profissional, mesmo instante,
     * paciente diferente. Aviso, nunca bloqueio — ver AvisoDeAgenda.
     *
     * @param  array<int, SessaoCandidata>  $candidatas
     * @param  Collection<int, SessaoCandidata>  $gravadas
     * @return array<int, AvisoDeAgenda>
     */
    private function avisos(array $candidatas, Collection $gravadas): array
    {
        $comProfissional = array_values(array_filter(
            $candidatas,
            fn (SessaoCandidata $c) => $c->profissionalId !== null && $c->horaInicio !== null
        ));

        if ($comProfissional === []) {
            return [];
        }

        // As gravadas trazidas por sessoesJaGravadas() são do mesmo paciente,
        // e choque de profissional é justamente com OUTRO paciente — por isso
        // esta consulta é própria, por profissional e dia.
        //
        // O recorte de data é por faixa, não por igualdade: no SQLite dos
        // testes uma coluna `date` guarda '2026-09-21 00:00:00', e comparar
        // com '2026-09-21' não casa. A conferência exata do dia acontece
        // abaixo, comparando `dataString()` dos dois lados.
        $datas = collect($comProfissional)->map->data;

        $doProfissional = Lancamento::query()
            ->where('status', 'completed')
            ->whereIn('profissional_id', collect($comProfissional)->pluck('profissionalId')->unique()->values())
            ->whereBetween('data_sessao', [
                $datas->min()->toDateString(),
                $datas->max()->addDay()->toDateString(),
            ])
            ->whereNotIn('id', collect($candidatas)->pluck('lancamentoId')->filter()->values()->all() ?: [0])
            ->with(['guia.paciente', 'guia.especialidade'])
            ->get();

        $avisos = [];

        foreach ($comProfissional as $candidata) {
            foreach ($doProfissional as $lancamento) {
                if ((int) $lancamento->profissional_id !== $candidata->profissionalId) {
                    continue;
                }

                if ((int) $lancamento->guia->paciente_id === $candidata->pacienteId) {
                    continue;
                }

                $outra = SessaoCandidata::deLancamento($lancamento);

                if ($outra->horaInicio !== $candidata->horaInicio || $outra->dataString() !== $candidata->dataString()) {
                    continue;
                }

                $avisos[] = new AvisoDeAgenda(
                    tipo: AvisoDeAgenda::TIPO_CHOQUE_PROFISSIONAL,
                    referencia: $candidata->referencia,
                    mensagem: sprintf(
                        'O executante já tem sessão com %s em %s. Confira se a agenda está certa.',
                        $lancamento->guia->paciente?->nome ?? 'outro paciente',
                        $outra->descricao(),
                    ),
                );
            }
        }

        return $avisos;
    }
}
