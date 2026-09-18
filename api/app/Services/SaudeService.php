<?php

namespace App\Services;

use App\Models\SaudeComponente;
use App\Models\SaudeComponenteEvento;
use App\Scopes\TenantScope;
use App\Support\Auditoria;
use App\Support\TenantContext;
use DateTimeInterface;
use Illuminate\Support\Collection;

/**
 * Heartbeat e derivacao de estado dos componentes de saude.
 *
 * Ver openspec/changes/saude-componentes/design.md para o racional das
 * decisoes; aqui ficam so as consequencias delas.
 */
class SaudeService
{
    /**
     * Registra que o componente trabalhou com sucesso agora.
     *
     * O tenant e resolvido em tres passos, e cada um tem um motivo:
     *
     * - `$tenantId` explicito: quem chama roda fora de requisicao HTTP e sabe de
     *   quem e o trabalho. E o caso do job da automacao, que roda no queue:work
     *   sem TenantContext e carrega o tenant_id da execucao.
     * - TenantContext: chamada dentro de requisicao autenticada, como o envio de
     *   e-mail de teste.
     * - Nenhum dos dois: infraestrutura compartilhada, um processo so servindo
     *   todos os tenants — o agendador. Nesse caso o heartbeat vale para todas as
     *   linhas daquela chave, o que e literalmente verdade: o agendador rodou
     *   para todo mundo.
     *
     * Nao cria componente que nao existe. Registrar componente novo e inserir
     * linha (a spec diz isso), e criar aqui obrigaria o codigo a inventar
     * `intervalo_esperado_segundos` — exatamente o tipo de regra que o projeto
     * mantem como dado (ADR-03).
     *
     * @return int quantas linhas foram carimbadas
     */
    public function registrarHeartbeat(
        string $chave,
        string $status = 'ok',
        ?string $mensagem = null,
        ?int $tenantId = null,
    ): int {
        $tenantId ??= TenantContext::get();

        $query = SaudeComponente::query()->where('chave', $chave);

        if ($tenantId !== null) {
            // Sem o global scope: o job da automacao roda sem TenantContext, e
            // deixar o scope decidir faria a escrita depender de um estado que
            // ali nao existe.
            $query->withoutGlobalScope(TenantScope::class)->where('tenant_id', $tenantId);
        } else {
            $query->withoutGlobalScope(TenantScope::class);
        }

        $componentes = $query->get();

        // O heartbeat de componente desativado tambem e gravado: desativar e
        // decisao de exibicao, e descartar a escrita faria o componente parecer
        // morto no dia em que fosse reativado.
        foreach ($componentes as $componente) {
            // Sem isto, o carimbo de cada minuto viraria uma linha na trilha de
            // auditoria — 1.440 por dia, por componente.
            Auditoria::semRegistroAutomatico(function () use ($componente, $status, $mensagem) {
                $componente->forceFill([
                    'ultimo_heartbeat_em' => now(),
                    'ultimo_status' => $status,
                    'ultima_mensagem' => $mensagem,
                ])->save();
            });

            // Quem manda sinal de vida esta, por definicao, no ar. Se o ultimo
            // evento ja dizia isso, nada e gravado — e este e o caminho comum,
            // um por minuto por componente.
            $this->registrarMudancaDeEstado($componente, SaudeComponente::ESTADO_SAUDAVEL, now(), $mensagem);
        }

        return $componentes->count();
    }

    /**
     * Passa os componentes em revista e registra quem MUDOU de estado.
     *
     * Existe porque a queda nao tem quem a observe. O heartbeat so acontece
     * quando o componente esta vivo, entao ele enxerga a VOLTA ao ar, nunca a
     * saida — um worker que morre para de mandar sinal, e a ausencia de sinal
     * nao chama codigo nenhum. E a passagem do tempo que derruba o estado, e so
     * uma varredura periodica percebe isso.
     *
     * Roda a cada minuto, junto do carimbo do agendador (ver routes/console.php).
     * Uma consulta sobre uma tabela de poucas dezenas de linhas, e escrita so
     * quando algo muda.
     *
     * @return int quantas mudancas foram registradas
     */
    public function sincronizarEstados(): int
    {
        $componentes = SaudeComponente::query()
            ->withoutGlobalScope(TenantScope::class)
            ->get();

        $registradas = 0;

        foreach ($componentes as $componente) {
            $registradas += $this->registrarMudancaDeEstado(
                $componente,
                $this->estadoDe($componente),
                now(),
                $componente->ultima_mensagem,
            ) ? 1 : 0;
        }

        return $registradas;
    }

    /**
     * Grava o evento SE o estado for diferente do ultimo registrado.
     *
     * O primeiro evento de um componente e sempre gravado, qualquer que seja o
     * estado: e ele que marca onde o historico comeca. Sem essa linha, um
     * componente que sempre esteve no ar nao teria evento algum, e o relatorio
     * nao saberia distinguir "esteve no ar o periodo todo" de "nao tenho
     * registro deste periodo" — que e justamente a distincao que a tabela existe
     * para preservar.
     */
    private function registrarMudancaDeEstado(
        SaudeComponente $componente,
        string $estado,
        DateTimeInterface $ocorridoEm,
        ?string $mensagem = null,
    ): bool {
        $ultimo = SaudeComponenteEvento::query()
            ->withoutGlobalScope(TenantScope::class)
            ->where('saude_componente_id', $componente->id)
            ->orderByDesc('ocorrido_em')
            ->orderByDesc('id')
            ->first();

        if ($ultimo !== null && $ultimo->estado === $estado) {
            return false;
        }

        SaudeComponenteEvento::query()->create([
            'tenant_id' => $componente->tenant_id,
            'saude_componente_id' => $componente->id,
            'estado' => $estado,
            'ocorrido_em' => $ocorridoEm,
            'mensagem' => $mensagem,
        ]);

        return true;
    }

    /**
     * Deriva o estado a partir do tempo desde o ultimo heartbeat.
     *
     * Sem heartbeat nenhum e `down`, e nao um quarto estado "desconhecido":
     * componente que nunca deu sinal de vida e indistinguivel, para quem opera,
     * de componente morto — e um estado a mais so empurra a decisao para a tela.
     */
    public function estadoDe(SaudeComponente $componente): string
    {
        if ($componente->ultimo_heartbeat_em === null) {
            return SaudeComponente::ESTADO_FORA;
        }

        // Diferenca por timestamp, e nao por diffInSeconds(): o sinal e o tipo
        // de retorno dos diffIn* mudaram entre versoes do Carbon, e aqui a
        // comparacao decide se a tela fica verde ou vermelha.
        $segundos = now()->getTimestamp() - $componente->ultimo_heartbeat_em->getTimestamp();
        $intervalo = max(1, $componente->intervalo_esperado_segundos);

        if ($segundos <= $intervalo) {
            return SaudeComponente::ESTADO_SAUDAVEL;
        }

        if ($segundos <= $intervalo * SaudeComponente::MULTIPLICADOR_FORA) {
            return SaudeComponente::ESTADO_ATENCAO;
        }

        return SaudeComponente::ESTADO_FORA;
    }

    /**
     * Componentes ativos do tenant atual, com o estado derivado.
     *
     * Lida pelo dashboard a cada 30s: uma leitura da tabela do tenant, sem
     * count() e sem join. O estado sai em PHP, e nao em SQL, para a mesma regra
     * valer no card, no /api/health e em qualquer consumidor futuro.
     */
    public function componentesDoTenant(): Collection
    {
        return SaudeComponente::query()
            ->where('ativo', true)
            ->orderBy('nome')
            ->get()
            ->map(fn (SaudeComponente $c) => [
                'id' => $c->id,
                'chave' => $c->chave,
                'nome' => $c->nome,
                'tipo' => $c->tipo,
                'estado' => $this->estadoDe($c),
                'ultimo_heartbeat_em' => $c->ultimo_heartbeat_em?->toIso8601String(),
                'ultimo_status' => $c->ultimo_status,
                'ultima_mensagem' => $c->ultima_mensagem,
                'intervalo_esperado_segundos' => $c->intervalo_esperado_segundos,
            ]);
    }

    /**
     * Resumo para o GET /api/health, que e PUBLICO.
     *
     * So chave e estado. Nome de componente pode carregar o nome do convenio, e
     * o id denuncia volume — nenhum dos dois entra num endpoint sem autenticacao
     * (ADR-24). Atravessa todos os tenants de proposito: quem le e um monitor
     * externo, que nao tem nem pode ter tenant.
     *
     * Agrupa por chave, e o pior estado vence. Isso nao e cosmetico: sem
     * agrupar, `scheduler` apareceria uma vez por tenant e a contagem de linhas
     * entregaria quantas clinicas existem — justamente o tipo de dado que este
     * endpoint nao pode expor. Como consequencia, o monitor externo ve "a fila
     * esta fora" sem saber de quem, que e o suficiente para acordar alguem.
     *
     * @return array<int, array{chave: string, estado: string}>
     */
    public function resumoPublico(): array
    {
        $severidade = [
            SaudeComponente::ESTADO_SAUDAVEL => 0,
            SaudeComponente::ESTADO_ATENCAO => 1,
            SaudeComponente::ESTADO_FORA => 2,
        ];

        return SaudeComponente::query()
            ->withoutGlobalScope(TenantScope::class)
            ->where('ativo', true)
            ->get()
            ->groupBy('chave')
            ->map(fn (Collection $doGrupo) => $doGrupo
                ->map(fn (SaudeComponente $c) => $this->estadoDe($c))
                ->sortByDesc(fn (string $estado) => $severidade[$estado])
                ->first())
            ->sortKeys()
            ->map(fn (string $estado, string $chave) => [
                'chave' => $chave,
                'estado' => $estado,
            ])
            ->values()
            ->all();
    }
}
