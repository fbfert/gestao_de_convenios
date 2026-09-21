<?php

namespace App\Services\Automation;

use App\Models\AutomacaoExecucao;
use App\Models\Guia;
use App\Models\Lancamento;
use App\Repositories\ConvenioCredencialRepository;
use App\Services\Sessoes\FolhasDeRegistroService;
use App\Services\Sessoes\SessaoCandidata;
use App\Services\Sessoes\AvaliadorDeAgenda;
use App\Support\GuiaStatus;

/**
 * Tudo o que precisa ser verdade antes de abrir o navegador.
 *
 * Existe porque decisão humana e execução de robô não se misturam bem: se a
 * quantidade de sessões não bate com a autorizada, quem decide é o operador —
 * e decidir com um Playwright aberto esperando significaria manter uma sessão
 * de portal viva enquanto alguém lê a tela. Aqui tudo isso é resolvido antes,
 * e o worker recebe uma lista pronta.
 *
 * Separa três coisas que a tela precisa distinguir:
 *
 * - `impedimentos`: não há o que fazer pela interface (guia errada, sem
 *   credencial, sem sessão). O botão nem deveria estar lá.
 * - `conflitos`: sessões que se contradizem. Só sai corrigindo — não existe
 *   "confirmar assim mesmo" para isto.
 * - `decisoes`: o que depende de alguém dizer sim (finalizar com menos
 *   sessões, cortar no autorizado, finalizar sem folha anexada).
 */
class FinalizarGuiaPreVoo
{
    /** Decisões que o operador pode tomar, e o nome com que voltam no disparo. */
    public const DECISAO_MENOS_SESSOES = 'confirmar_menos_sessoes';

    public const DECISAO_LIMITAR_AO_AUTORIZADO = 'limitar_ao_autorizado';

    public const DECISAO_SEM_ANEXO = 'confirmar_sem_anexo';

    public function __construct(
        private readonly ConvenioCredencialRepository $credenciais,
        private readonly FolhasDeRegistroService $folhas,
        private readonly AvaliadorDeAgenda $avaliador,
    ) {}

    /**
     * @return array{
     *   pode_finalizar: bool,
     *   impedimentos: array<int, string>,
     *   conflitos: array<int, array<string, mixed>>,
     *   decisoes: array<int, array<string, mixed>>,
     *   sessoes: array<int, array<string, string|null>>,
     *   sessoes_autorizadas: int|null,
     *   folhas: int,
     *   simular: bool,
     * }
     */
    public function avaliar(Guia $guia): array
    {
        $guia->loadMissing(['convenio', 'especialidade', 'paciente']);

        $impedimentos = $this->impedimentos($guia);
        $sessoes = $this->sessoesDaGuia($guia);
        $conflitos = $this->conflitos($guia, $sessoes);
        $folhas = $this->folhas->daGuia($guia)->count();
        $autorizadas = $guia->sessoes_autorizadas;

        $decisoes = $this->decisoes($guia, $sessoes->count(), $autorizadas, $folhas);

        return [
            'pode_finalizar' => $impedimentos === [] && $conflitos === [],
            'impedimentos' => $impedimentos,
            'conflitos' => $conflitos,
            'decisoes' => $decisoes,
            'sessoes' => $this->paraOWorker($sessoes),
            'sessoes_autorizadas' => $autorizadas,
            'folhas' => $folhas,
            'simular' => $this->simulacaoAtiva($guia),
        ];
    }

    public function simulacaoAtiva(Guia $guia): bool
    {
        return (bool) \App\Models\ConfiguracaoGlobal::doTenant((int) $guia->tenant_id)
            ->automacao_finalizar_guia_simulacao_ativo;
    }

    /** @return array<int, string> */
    private function impedimentos(Guia $guia): array
    {
        $impedimentos = [];

        if ($guia->convenio?->connector_driver !== 'unimed_rda') {
            $impedimentos[] = 'A guia não é de convênio com automação Unimed.';
        }

        if (blank($guia->numero_guia)) {
            $impedimentos[] = 'A guia precisa ter número da operadora.';
        }

        if (! in_array($guia->status, [GuiaStatus::UNDER_REVIEW, GuiaStatus::APPROVED], true)) {
            $impedimentos[] = 'A guia não está em situação que aceita finalização.';
        }

        if ($guia->lancamentos()->count() === 0) {
            $impedimentos[] = 'A guia precisa ter ao menos uma sessão registrada.';
        }

        if ($guia->ehHistorica()) {
            $impedimentos[] = 'A guia pertence a uma solicitação histórica e não entra em automação.';
        }

        /*
         * Pedir ao portal que finalize o que ele já deu por finalizado não é
         * uma operação que faça sentido: a guia nem chegaria à tela de execução
         * que o robô espera, porque saiu dos exames em aberto.
         */
        if ($guia->finalizadaNaOperadora()) {
            $impedimentos[] = 'A operadora já deu esta guia por finalizada — não há o que finalizar de novo.';
        }

        if (! $this->credenciais->ativa((int) $guia->tenant_id, (int) $guia->convenio_id)) {
            $impedimentos[] = 'A credencial Unimed ativa não está configurada.';
        }

        $emAndamento = AutomacaoExecucao::query()
            ->where('tenant_id', $guia->tenant_id)
            ->where('guia_id', $guia->id)
            ->where('operacao', FinalizarGuiaUnimedService::OPERATION)
            ->whereIn('status', AutomacaoExecucao::STATUS_ATIVOS)
            ->exists();

        if ($emAndamento) {
            $impedimentos[] = 'Já existe uma finalização desta guia em andamento.';
        }

        return $impedimentos;
    }

    /**
     * As sessões que vão para o portal, da mais antiga para a mais recente.
     *
     * Só as realizadas: cancelada e não comparecida não aconteceram, e
     * declará-las à operadora seria cobrar o que não foi feito.
     *
     * @return \Illuminate\Support\Collection<int, Lancamento>
     */
    private function sessoesDaGuia(Guia $guia)
    {
        return $guia->lancamentos()
            ->where('status', 'completed')
            ->orderBy('data_sessao')
            ->orderBy('hora_inicio')
            ->orderBy('id')
            ->get();
    }

    /** @return array<int, array<string, mixed>> */
    private function conflitos(Guia $guia, $sessoes): array
    {
        if ($sessoes->isEmpty()) {
            return [];
        }

        $candidatas = $sessoes
            ->map(fn (Lancamento $lancamento) => SessaoCandidata::deLancamento($lancamento->setRelation('guia', $guia)))
            ->all();

        return array_map(
            fn ($conflito) => $conflito->toArray(),
            $this->avaliador->avaliarIsolado($candidatas)->conflitos,
        );
    }

    /**
     * O que só uma pessoa pode decidir.
     *
     * Cada decisão traz os números que a sustentam, porque a tela precisa
     * dizer "7 registradas contra 10 autorizadas", não "confirma?".
     *
     * @return array<int, array<string, mixed>>
     */
    private function decisoes(Guia $guia, int $registradas, ?int $autorizadas, int $folhas): array
    {
        $decisoes = [];

        if ($autorizadas !== null && $registradas < $autorizadas) {
            $decisoes[] = [
                'chave' => self::DECISAO_MENOS_SESSOES,
                'mensagem' => "A guia autoriza {$autorizadas} sessões e há {$registradas} registradas. Finalizar assim encerra a guia com menos sessões do que o autorizado.",
                'registradas' => $registradas,
                'autorizadas' => $autorizadas,
            ];
        }

        if ($autorizadas !== null && $registradas > $autorizadas) {
            $decisoes[] = [
                'chave' => self::DECISAO_LIMITAR_AO_AUTORIZADO,
                'mensagem' => "Há {$registradas} sessões registradas e a guia autoriza {$autorizadas}. Enviar apenas as {$autorizadas} mais antigas, ou revisar as sessões?",
                'registradas' => $registradas,
                'autorizadas' => $autorizadas,
            ];
        }

        if ($folhas === 0) {
            $exigeFolha = $this->folhas->regiaoExigeFolha($guia->paciente?->carteirinha);

            $decisoes[] = [
                'chave' => self::DECISAO_SEM_ANEXO,
                'mensagem' => $exigeFolha
                    ? 'Não há folha de registro anexada, e a regional desta carteirinha exige o envio da folha. Finalizar assim mesmo?'
                    : 'Não há folha de registro anexada a esta guia. Finalizar assim mesmo?',
                'regional_exige' => $exigeFolha,
            ];
        }

        return $decisoes;
    }

    /**
     * Formato que o worker consome: data e hora, nada mais.
     *
     * @return array<int, array<string, string|null>>
     */
    private function paraOWorker($sessoes): array
    {
        return $sessoes->map(fn (Lancamento $lancamento) => [
            'data' => $lancamento->data_sessao?->toDateString(),
            'hora' => SessaoCandidata::normalizarHora($lancamento->hora_inicio),
        ])->values()->all();
    }
}
