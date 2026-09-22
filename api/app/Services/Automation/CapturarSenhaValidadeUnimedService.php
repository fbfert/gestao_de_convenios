<?php

namespace App\Services\Automation;

use App\Exceptions\AutomationConcurrencyException;
use App\Jobs\ExecutarAutomacaoUnimedJob;
use App\Models\AutomacaoExecucao;
use App\Models\ConfiguracaoGlobal;
use App\Models\Guia;
use App\Repositories\ConvenioCredencialRepository;
use Illuminate\Validation\ValidationException;

class CapturarSenhaValidadeUnimedService
{
    private const ACTIVE_STATUSES = ['queued', 'running'];

    public const OPERATION = 'capture_authorization_data_batch';

    public function __construct(
        private readonly AutomacaoService $automacoes,
        private readonly ConvenioCredencialRepository $credenciais,
    ) {}

    public function enviar(Guia $guia, bool $dispatch = true): AutomacaoExecucao
    {
        $avaliacao = $this->avaliar($guia);

        if (! $avaliacao['eligible']) {
            throw ValidationException::withMessages(['guia' => $avaliacao['motivos']]);
        }

        try {
            $parent = AutomacaoExecucao::query()
                ->where('tenant_id', $guia->tenant_id)
                ->where('guia_id', $guia->id)
                ->where('operacao', self::OPERATION)
                ->whereNotIn('status', self::ACTIVE_STATUSES)
                ->latest('id')
                ->first();

            $execucao = $this->automacoes->enfileirar(
                $guia->tenant_id,
                self::OPERATION,
                guia: $guia,
                payload: $this->payloadPersistido($guia),
                parent: $parent,
            );
        } catch (AutomationConcurrencyException $exception) {
            throw ValidationException::withMessages([
                'guia' => ["Já existe execução Unimed ativa para este tenant ({$exception->execucaoId})."],
            ]);
        }

        // Antes desta guia era reprocessada a cada tick de 30 min do job em
        // lote (sem cooldown proprio). Agora so volta a ficar elegivel apos
        // o intervalo configurado em /automacoes/configuracoes — se a
        // captura tiver sucesso a guia sai da query (senha+validade
        // preenchidas) antes mesmo deste prazo valer.
        $intervaloHoras = $this->intervaloHoras($guia->tenant_id);
        $guia->forceFill([
            'unimed_senha_validade_next_check_at' => now()->addHours($intervaloHoras),
        ])->save();

        if ($dispatch) {
            ExecutarAutomacaoUnimedJob::dispatch($execucao->id);
        }

        return $execucao;
    }

    private function intervaloHoras(int $tenantId): int
    {
        return ConfiguracaoGlobal::doTenant($tenantId)->unimed_captura_senha_validade_intervalo_horas ?: 6;
    }

    /**
     * Botão manual pra guias que ficaram presas no bug de 22/09/2026: senha e
     * validade já capturadas, mas sessões nunca vieram porque o worker
     * descartava esse dado nesta mesma tela. Elegibilidade é o espelho de
     * avaliar() — exige senha/validade já preenchidas (em vez de ausentes) e
     * sessões ainda zeradas.
     */
    public function enviarRecuperacaoSessoes(Guia $guia, bool $dispatch = true): AutomacaoExecucao
    {
        $avaliacao = $this->avaliarRecuperacaoSessoes($guia);

        if (! $avaliacao['eligible']) {
            throw ValidationException::withMessages(['guia' => $avaliacao['motivos']]);
        }

        // Reusa a mesma OPERATION de sempre (o job roteia só pelo nome da
        // operação), mas com o parent apontando pra ultima execucao desta
        // guia — sem isso a chave de idempotencia bateria com a execucao
        // succeeded anterior e enfileirar() so devolveria ela de volta, sem
        // rodar nada novo no portal.
        $parent = AutomacaoExecucao::query()
            ->where('tenant_id', $guia->tenant_id)
            ->where('guia_id', $guia->id)
            ->where('operacao', self::OPERATION)
            ->latest('id')
            ->first();

        try {
            $execucao = $this->automacoes->enfileirar(
                $guia->tenant_id,
                self::OPERATION,
                guia: $guia,
                payload: $this->payloadPersistido($guia),
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

    public function avaliarRecuperacaoSessoes(Guia $guia): array
    {
        $guia->loadMissing(['convenio']);
        $motivos = [];
        $credential = $this->credenciais->ativa((int) $guia->tenant_id, $guia->convenio_id);

        if ($guia->convenio?->connector_driver !== 'unimed_rda') {
            $motivos[] = 'A Guia não pertence a Convênio Unimed RDA.';
        }

        if ($guia->temDadosADefinir()) {
            $motivos[] = 'A Guia precisa ter Especialidade e Profissional definidos para entrar na automação.';
        }

        if ($guia->ehHistorica()) {
            $motivos[] = 'A Guia pertence a uma Solicitação histórica e não entra em automação.';
        }

        if (! $credential) {
            $motivos[] = 'A credencial Unimed ativa não está configurada.';
        }

        if ($guia->status !== 'approved') {
            $motivos[] = 'A Guia precisa estar aprovada para buscar sessões.';
        }

        if (blank($guia->numero_guia)) {
            $motivos[] = 'A Guia precisa ter número da operadora.';
        }

        if (blank($guia->senha) || blank($guia->validade_senha)) {
            $motivos[] = 'A Guia ainda não tem senha e validade capturadas — use "Buscar senha/validade" primeiro.';
        }

        // filled() trata 0 numerico como preenchido, entao a comparacao vai
        // direto no valor: so elegivel quando as duas sessoes ainda estao
        // zeradas/nulas (o estado travado pelo bug), nao quando ja tem
        // qualquer numero real, mesmo que baixo.
        $semSessoes = (int) ($guia->sessoes_solicitadas ?? 0) === 0 && (int) ($guia->sessoes_autorizadas ?? 0) === 0;
        if (! $semSessoes) {
            $motivos[] = 'A Guia já possui sessões registradas.';
        }

        $active = AutomacaoExecucao::query()
            ->where('tenant_id', $guia->tenant_id)
            ->where('guia_id', $guia->id)
            ->where('operacao', self::OPERATION)
            ->whereIn('status', self::ACTIVE_STATUSES)
            ->exists();

        if ($active) {
            $motivos[] = 'Já existe busca de senha/validade em andamento para esta Guia.';
        }

        return [
            'eligible' => $motivos === [],
            'motivos' => $motivos,
        ];
    }

    public function avaliar(Guia $guia): array
    {
        $guia->loadMissing(['convenio']);
        $motivos = [];
        $credential = $this->credenciais->ativa((int) $guia->tenant_id, $guia->convenio_id);

        if ($guia->convenio?->connector_driver !== 'unimed_rda') {
            $motivos[] = 'A Guia não pertence a Convênio Unimed RDA.';
        }

        if ($guia->temDadosADefinir()) {
            $motivos[] = 'A Guia precisa ter Especialidade e Profissional definidos para entrar na automação.';
        }

        if ($guia->ehHistorica()) {
            $motivos[] = 'A Guia pertence a uma Solicitação histórica e não entra em automação.';
        }

        // A credencial e a DO CONVENIO da guia, nao a do tenant.
        if (! $credential) {
            $motivos[] = 'A credencial Unimed ativa não está configurada.';
        }

        if ($guia->status !== 'approved') {
            $motivos[] = 'A Guia precisa estar aprovada para buscar senha e validade.';
        }

        if (blank($guia->numero_guia)) {
            $motivos[] = 'A Guia precisa ter número da operadora.';
        }

        if (filled($guia->senha) && filled($guia->validade_senha)) {
            $motivos[] = 'A Guia já possui senha e validade.';
        }

        $active = AutomacaoExecucao::query()
            ->where('tenant_id', $guia->tenant_id)
            ->where('guia_id', $guia->id)
            ->where('operacao', self::OPERATION)
            ->whereIn('status', self::ACTIVE_STATUSES)
            ->exists();

        if ($active) {
            $motivos[] = 'Já existe busca de senha/validade em andamento para esta Guia.';
        }

        return [
            'eligible' => $motivos === [],
            'motivos' => $motivos,
        ];
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

    public function aplicarResultado(AutomacaoExecucao $execucao, array $resultado): AutomacaoExecucao
    {
        $execucao = $this->automacoes->concluir($execucao, $resultado);
        $guia = $execucao->guia()->firstOrFail();

        if (($resultado['status'] ?? null) !== 'succeeded') {
            $this->automacoes->registrarEvento($execucao, 'autorizacao_indisponivel', $execucao->status, [
                'codigo' => $resultado['error_code'] ?? null,
                'mensagem' => $resultado['message'] ?? 'Senha ou validade não encontrada no portal.',
            ]);

            return $execucao->refresh();
        }

        $updates = [];
        if (filled($resultado['senha'] ?? null)) {
            $updates['senha'] = $resultado['senha'];
        }
        if (filled($resultado['validade_senha'] ?? null)) {
            $updates['validade_senha'] = $resultado['validade_senha'];
        }

        // A mesma tela de execucao do SP/SADT ja traz sessoes solicitadas/
        // autorizadas junto com senha/validade — o worker passou a repassar
        // (ver statusSenha.js). Sem isto, guias que so entram nesta captura
        // (ex.: ja "approved", fora da consulta de status) nunca preenchiam
        // sessoes (achado ao vivo 22/09/2026, guia 50144652656).
        foreach (['sessoes_solicitadas', 'sessoes_autorizadas'] as $campo) {
            if (filled($resultado[$campo] ?? null)) {
                $updates[$campo] = (int) $resultado[$campo];
            }
        }

        if ($updates !== []) {
            $guia->forceFill($updates)->save();
        }

        return $execucao->refresh();
    }

    private function payloadPersistido(Guia $guia): array
    {
        $guia->loadMissing(['paciente', 'convenio']);

        return [
            'guia_id' => $guia->id,
            'numero_guia' => $guia->numero_guia,
            'paciente' => [
                'id' => $guia->paciente_id,
                'nome' => $guia->paciente?->nome,
                'carteirinha' => $guia->paciente?->carteirinha,
            ],
            'convenio_id' => $guia->convenio_id,
        ];
    }
}
