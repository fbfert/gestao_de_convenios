<?php

namespace App\Services\Automation;

use App\Exceptions\AutomationConcurrencyException;
use App\Jobs\ExecutarAutomacaoUnimedJob;
use App\Models\AutomacaoExecucao;
use App\Models\Guia;
use App\Models\PacienteArquivo;
use App\Repositories\ConvenioCredencialRepository;
use App\Services\GuiaService;
use App\Services\Sessoes\FolhasDeRegistroService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Finalização de guia na Unimed, do lado da API.
 *
 * O último passo do ciclo da guia: as sessões estão lançadas, a folha assinada
 * está anexada, e o que faltava era alguém digitar tudo no portal.
 *
 * Fluxo: o pré-voo (FinalizarGuiaPreVoo) resolve antes o que depende de gente,
 * este serviço enfileira a execução com a lista pronta, o worker a executa, e
 * `aplicarResultado` decide o que acontece com a guia. Só um sucesso REAL —
 * fora do modo simulação — finaliza a guia no Gescon.
 */
class FinalizarGuiaUnimedService
{
    public const OPERATION = 'finalizar_guia';

    public function __construct(
        private readonly AutomacaoService $automacoes,
        private readonly ConvenioCredencialRepository $credenciais,
        private readonly FinalizarGuiaPreVoo $preVoo,
        private readonly FolhasDeRegistroService $folhas,
        private readonly GuiaService $guias,
    ) {}

    /**
     * @param  array<string, bool>  $confirmacoes  decisões que o operador tomou na tela
     */
    public function enviar(Guia $guia, array $confirmacoes = [], bool $dispatch = true): AutomacaoExecucao
    {
        $avaliacao = $this->preVoo->avaliar($guia);

        if ($avaliacao['impedimentos'] !== []) {
            throw ValidationException::withMessages(['guia' => $avaliacao['impedimentos']]);
        }

        if ($avaliacao['conflitos'] !== []) {
            throw ValidationException::withMessages([
                'sessoes' => array_map(fn (array $conflito) => $conflito['mensagem'], $avaliacao['conflitos']),
            ]);
        }

        /*
         * Cada decisão do pré-voo precisa voltar confirmada.
         *
         * Sem esta guarda, um POST direto na API driblaria os diálogos da tela
         * — e o que os diálogos protegem não é a interface, é a guia: uma vez
         * finalizada na operadora, não há desfazer.
         */
        foreach ($avaliacao['decisoes'] as $decisao) {
            if (! ($confirmacoes[$decisao['chave']] ?? false)) {
                throw ValidationException::withMessages([
                    $decisao['chave'] => [$decisao['mensagem']],
                ]);
            }
        }

        $sessoes = $this->sessoesAEnviar($avaliacao, $confirmacoes);

        try {
            // Uma falha anterior vira `parent` da nova tentativa, como nas
            // outras operações: é o que permite reacionar sem a chave de
            // idempotência devolver a execução velha.
            $parent = AutomacaoExecucao::query()
                ->where('tenant_id', $guia->tenant_id)
                ->where('guia_id', $guia->id)
                ->where('operacao', self::OPERATION)
                ->whereNotIn('status', AutomacaoExecucao::STATUS_ATIVOS)
                ->latest('id')
                ->first();

            $execucao = $this->automacoes->enfileirar(
                (int) $guia->tenant_id,
                self::OPERATION,
                guia: $guia,
                payload: [
                    'guia_id' => $guia->id,
                    'numero_guia' => $guia->numero_guia,
                    'sessoes' => $sessoes,
                    'sessoes_autorizadas' => $avaliacao['sessoes_autorizadas'],
                    'anexos' => $this->anexosPersistidos($guia),
                    'simular' => $avaliacao['simular'],
                ],
                parent: $parent,
            );
        } catch (AutomationConcurrencyException $exception) {
            throw ValidationException::withMessages([
                'guia' => ["Já existe execução Unimed ativa para este tenant ({$exception->execucaoId})."],
            ]);
        }

        if ($dispatch) {
            ExecutarAutomacaoUnimedJob::dispatch($execucao->id);
        }

        return $execucao;
    }

    /**
     * As sessões que realmente vão para o portal.
     *
     * Quando há mais sessões que o autorizado e o operador escolheu cortar, o
     * corte é nas MAIS RECENTES — as mais antigas são as que a folha assinada
     * cobre primeiro, e são elas que a operadora espera ver na série.
     *
     * @param  array<string, mixed>  $avaliacao
     * @param  array<string, bool>  $confirmacoes
     * @return array<int, array<string, string|null>>
     */
    private function sessoesAEnviar(array $avaliacao, array $confirmacoes): array
    {
        $sessoes = $avaliacao['sessoes'];
        $autorizadas = $avaliacao['sessoes_autorizadas'];

        if (
            $autorizadas !== null
            && count($sessoes) > $autorizadas
            && ($confirmacoes[FinalizarGuiaPreVoo::DECISAO_LIMITAR_AO_AUTORIZADO] ?? false)
        ) {
            return array_slice($sessoes, 0, (int) $autorizadas);
        }

        return $sessoes;
    }

    /**
     * As folhas da guia, com o caminho que o worker consegue abrir.
     *
     * `local_path` é absoluto no volume compartilhado; `path` sozinho só faz
     * sentido dentro do Laravel. Mesmo par que `gerarGuia` usa para o pedido
     * médico.
     *
     * @return array<int, array<string, mixed>>
     */
    private function anexosPersistidos(Guia $guia): array
    {
        return $this->folhas->daGuia($guia)->map(fn (PacienteArquivo $folha) => [
            'tipo' => 'registro_sessoes',
            'nome_original' => $folha->nome_original,
            'path' => $folha->path,
            'local_path' => Storage::disk('local')->path($folha->path),
        ])->values()->all();
    }

    public function payloadParaWorker(AutomacaoExecucao $execucao): array
    {
        $credential = $this->credenciais->ativaParaExecucao($execucao);

        return ($execucao->payload ?? []) + [
            'credential' => [
                'login' => $credential->campo('login'),
                'password' => $credential->campo('password'),
                'base_url' => $credential->campo('base_url'),
            ],
        ];
    }

    /**
     * O que o resultado do worker faz com a guia.
     *
     * Só um caminho finaliza a guia: sucesso real, fora da simulação. Falha e
     * simulação deixam a guia exatamente como estava — na falha porque nada
     * aconteceu na operadora, na simulação porque de propósito nada aconteceu.
     */
    public function aplicarResultado(AutomacaoExecucao $execucao, array $resultado): AutomacaoExecucao
    {
        $execucao = $this->automacoes->concluir($execucao, $resultado);
        $guia = $execucao->guia()->first();

        if (! $guia) {
            return $execucao->refresh();
        }

        if (($resultado['status'] ?? null) !== 'succeeded') {
            $this->automacoes->registrarEvento($execucao, 'finalizacao_recusada', $execucao->status, [
                'codigo' => $resultado['error_code'] ?? null,
                'mensagem' => $resultado['message'] ?? 'A finalização na Unimed não foi concluída.',
            ]);

            return $execucao->refresh();
        }

        if ($resultado['simulado'] ?? false) {
            $this->automacoes->registrarEvento($execucao, 'finalizacao_simulada', $execucao->status, [
                'mensagem' => 'Simulação concluída: a guia foi preenchida no portal, mas NÃO foi gravada nem finalizada.',
            ]);

            return $execucao->refresh();
        }

        $this->guias->finalizarPelaAutomacao($guia);

        return $execucao->refresh();
    }
}
