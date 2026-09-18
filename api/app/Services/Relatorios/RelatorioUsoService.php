<?php

namespace App\Services\Relatorios;

use App\Models\Alerta;
use App\Models\AuditLog;
use App\Support\AuditoriaCatalogo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Aba de Uso: quem usa o sistema, quando, e para quê.
 *
 * Tudo aqui sai de `audit_logs`, que já registra ação, entidade, usuário e
 * horário — inclusive `acesso.login`, gravado pelo `AuthController`. Nenhuma
 * coleta nova foi criada para esta aba: o dado existia e ninguém o lia.
 *
 * ── O que a trilha limita ───────────────────────────────────────────────────
 *
 * A trilha tem expurgo por retenção (`auditoria_retencao_meses`). Período
 * anterior ao corte volta vazio — o que a aba mostra como "sem dados no
 * período", e não como queda de uso.
 *
 * Os filtros de convênio, especialidade e profissional não valem aqui: a linha
 * da trilha guarda quem fez e sobre qual entidade, não sobre qual convênio. Por
 * isso `filtros_aplicados` volta vazio nesta aba.
 */
class RelatorioUsoService extends RelatorioService
{
    private const ACAO_LOGIN = 'acesso.login';

    public function aba(): string
    {
        return RelatorioAba::USO;
    }

    protected function kpisDeclarados(): array
    {
        return [
            'usuarios_ativos' => [
                'sentido' => self::SENTIDO_MAIOR_MELHOR,
                'label' => 'Usuários ativos',
                'formato' => self::FORMATO_INTEIRO,
                'hint' => 'Pessoas com ao menos uma ação registrada no período.',
            ],
            'acessos' => [
                'label' => 'Acessos',
                'formato' => self::FORMATO_INTEIRO,
                'hint' => 'Logins registrados na trilha de auditoria.',
            ],
            'acoes' => [
                'label' => 'Ações registradas',
                'formato' => self::FORMATO_INTEIRO,
            ],
            'acoes_por_dia' => [
                'label' => 'Ações por dia',
                'formato' => self::FORMATO_INTEIRO,
                'hint' => 'Média sobre os dias do período.',
            ],
            'importacoes' => [
                'label' => 'Importações confirmadas',
                'formato' => self::FORMATO_INTEIRO,
            ],
            'taxa_linhas_invalidas' => [
                'sentido' => self::SENTIDO_MENOR_MELHOR,
                'label' => '% de linhas com erro',
                'formato' => self::FORMATO_PERCENTUAL,
                'hint' => 'Linhas recusadas sobre o total lido nas importações do período.',
            ],
            'alertas_gerados' => [
                'sentido' => self::SENTIDO_MENOR_MELHOR,
                'label' => 'Alertas gerados',
                'formato' => self::FORMATO_INTEIRO,
            ],
            'alertas_reconhecidos' => [
                'label' => 'Alertas reconhecidos',
                'formato' => self::FORMATO_INTEIRO,
            ],
            'tempo_medio_reconhecimento' => [
                'sentido' => self::SENTIDO_MENOR_MELHOR,
                'label' => 'Tempo médio até reconhecer',
                'formato' => self::FORMATO_HORAS,
            ],
        ];
    }

    protected function metricas(RelatorioFiltros $filtros, bool $ehComparacao = false): array
    {
        $trilha = $this->totaisDaTrilha($filtros);
        $importacoes = $this->totaisDeImportacao($filtros);
        $alertas = $this->totaisDeAlerta($filtros);

        $acoes = (int) $trilha->acoes;

        return [
            'usuarios_ativos' => (int) $trilha->usuarios,
            'acessos' => (int) $trilha->acessos,
            'acoes' => $acoes,
            // Inteiro porque a tela mostra "ações por dia", não uma fração de
            // ação. A divisão é pelos dias do período, e não pelos dias em que
            // houve movimento: fim de semana parado faz parte da média.
            'acoes_por_dia' => (int) round($acoes / max(1, $filtros->periodo->dias())),
            'importacoes' => $importacoes['lotes'],
            'taxa_linhas_invalidas' => $this->percentual($importacoes['invalidas'], $importacoes['linhas']),
            'alertas_gerados' => (int) $alertas->gerados,
            'alertas_reconhecidos' => (int) $alertas->reconhecidos,
            'tempo_medio_reconhecimento' => $this->emHoras(
                $alertas->segundos_ate_reconhecer === null ? null : (float) $alertas->segundos_ate_reconhecer
            ),
        ];
    }

    protected function series(RelatorioFiltros $filtros): array
    {
        return [
            $this->serieAcoesNoTempo($filtros),
            $this->seriePorHoraDoDia($filtros),
            $this->seriePorEntidade($filtros),
            $this->seriePorUsuario($filtros),
            $this->serieImportacoesPorTipo($filtros),
        ];
    }

    protected function tabelas(RelatorioFiltros $filtros): array
    {
        return [
            $this->tabelaPorUsuario($filtros),
            $this->tabelaPorEntidade($filtros),
        ];
    }

    /** Ver o cabeçalho: a trilha não carrega convênio, especialidade nem profissional. */
    protected function filtrosAplicados(RelatorioFiltros $filtros): array
    {
        return [];
    }

    // ── KPIs ────────────────────────────────────────────────────────────────

    private function totaisDaTrilha(RelatorioFiltros $filtros): object
    {
        return $this->trilhaDoPeriodo($filtros)
            ->selectRaw(
                'COUNT(*) as acoes,'
                .' COUNT(DISTINCT audit_logs.user_id) as usuarios,'
                .' SUM(CASE WHEN audit_logs.acao = ? THEN 1 ELSE 0 END) as acessos',
                [self::ACAO_LOGIN],
            )
            ->first();
    }

    /**
     * Importações confirmadas no período, somando os cinco tipos que existem.
     *
     * As cinco tabelas de lote têm exatamente as mesmas colunas, então a soma é
     * um laço sobre os nomes — e não cinco consultas escritas à mão, que
     * divergiriam na primeira vez que alguém acrescentasse um tipo.
     *
     * `antecipacao_import_lotes` não está na lista de propósito: a tabela foi
     * derrubada junto com o modelo de cota (migration 2026_09_10_195509).
     *
     * @return array{lotes: int, linhas: int, invalidas: int}
     */
    private function totaisDeImportacao(RelatorioFiltros $filtros): array
    {
        $total = ['lotes' => 0, 'linhas' => 0, 'invalidas' => 0];

        foreach ($this->porTipoDeImportacao($filtros) as $linha) {
            $total['lotes'] += $linha['lotes'];
            $total['linhas'] += $linha['linhas'];
            $total['invalidas'] += $linha['invalidas'];
        }

        return $total;
    }

    private function totaisDeAlerta(RelatorioFiltros $filtros): object
    {
        $ateReconhecer = RelatorioSql::diferencaEmSegundos('alertas.aberto_em', 'alertas.reconhecido_em');

        return $this->naClinica(Alerta::query(), $filtros)
            ->whereBetween('alertas.aberto_em', $this->janela($filtros))
            ->selectRaw(
                'COUNT(*) as gerados,'
                .' SUM(CASE WHEN alertas.reconhecido_em IS NOT NULL THEN 1 ELSE 0 END) as reconhecidos,'
                ." AVG(CASE WHEN alertas.reconhecido_em IS NOT NULL THEN {$ateReconhecer} END) as segundos_ate_reconhecer"
            )
            ->first();
    }

    // ── Séries ──────────────────────────────────────────────────────────────

    private function serieAcoesNoTempo(RelatorioFiltros $filtros): array
    {
        $balde = RelatorioSql::truncarData('audit_logs.created_at', $filtros->periodo->granularidade);

        $linhas = $this->trilhaDoPeriodo($filtros)
            ->selectRaw("{$balde} as balde, COUNT(*) as total")
            ->groupByRaw($balde)
            ->get();

        return $this->serie(
            'acoes_por_dia',
            'Ações ao longo do tempo',
            'linha',
            $filtros,
            $linhas->mapWithKeys(fn ($linha) => [$linha->balde => ['total' => (int) $linha->total]])->all(),
            ['total' => 'Ações'],
        );
    }

    /**
     * As 24 horas do dia, sempre todas.
     *
     * Hora sem movimento vale zero aqui, e não some: é justamente o desenho do
     * dia de trabalho que interessa — saber que ninguém usa o sistema às 3h faz
     * parte da resposta.
     */
    private function seriePorHoraDoDia(RelatorioFiltros $filtros): array
    {
        $hora = RelatorioSql::horaDoDia('audit_logs.created_at');

        $porHora = $this->trilhaDoPeriodo($filtros)
            ->selectRaw("{$hora} as hora, COUNT(*) as total")
            ->groupByRaw($hora)
            ->pluck('total', 'hora');

        if ($porHora->isEmpty()) {
            return $this->serieCategorica('acoes_por_hora', 'Ações por hora do dia', 'barras', [], ['total' => 'Ações']);
        }

        $pontos = [];

        for ($hora = 0; $hora < 24; $hora++) {
            $pontos[] = [
                'x' => str_pad((string) $hora, 2, '0', STR_PAD_LEFT).'h',
                'total' => (int) ($porHora[$hora] ?? 0),
            ];
        }

        return $this->serieCategorica('acoes_por_hora', 'Ações por hora do dia', 'barras', $pontos, ['total' => 'Ações']);
    }

    private function seriePorEntidade(RelatorioFiltros $filtros): array
    {
        $linhas = $this->porEntidade($filtros);

        return $this->serieCategorica(
            'acoes_por_entidade',
            'Ações por entidade',
            'barras',
            $linhas->map(fn ($linha) => [
                'x' => AuditoriaCatalogo::rotuloEntidade($linha->entidade),
                'total' => (int) $linha->total,
            ])->all(),
            ['total' => 'Ações'],
        );
    }

    private function seriePorUsuario(RelatorioFiltros $filtros): array
    {
        $linhas = $this->porUsuario($filtros, limite: 10);

        return $this->serieCategorica(
            'acoes_por_usuario',
            'Quem mais usa o sistema',
            'barras',
            $linhas->map(fn ($linha) => [
                'x' => $linha->nome ?: 'Sistema',
                'total' => (int) $linha->total,
            ])->all(),
            ['total' => 'Ações'],
        );
    }

    private function serieImportacoesPorTipo(RelatorioFiltros $filtros): array
    {
        $pontos = [];

        foreach ($this->porTipoDeImportacao($filtros) as $linha) {
            if ($linha['lotes'] > 0) {
                $pontos[] = ['x' => $linha['rotulo'], 'total' => $linha['lotes']];
            }
        }

        return $this->serieCategorica('importacoes_por_tipo', 'Importações por tipo', 'barras', $pontos, ['total' => 'Lotes']);
    }

    // ── Tabelas ─────────────────────────────────────────────────────────────

    private function tabelaPorUsuario(RelatorioFiltros $filtros): array
    {
        $linhas = $this->porUsuario($filtros);

        return $this->tabela(
            'por_usuario',
            'Por usuário',
            [
                'nome' => 'Usuário',
                'acoes' => 'Ações',
                'acessos' => 'Acessos',
                'ultimo_acesso' => 'Última ação',
            ],
            $linhas->map(fn ($linha) => [
                'nome' => $linha->nome ?: 'Sistema',
                'acoes' => (int) $linha->total,
                'acessos' => (int) $linha->acessos,
                'ultimo_acesso' => $linha->ultimo,
            ])->all(),
            ['acoes' => self::FORMATO_INTEIRO, 'acessos' => self::FORMATO_INTEIRO, 'ultimo_acesso' => 'data_hora'],
        );
    }

    private function tabelaPorEntidade(RelatorioFiltros $filtros): array
    {
        $linhas = $this->porEntidade($filtros, limite: null);

        return $this->tabela(
            'por_entidade',
            'Por entidade',
            ['entidade' => 'Entidade', 'total' => 'Ações'],
            $linhas->map(fn ($linha) => [
                'entidade' => AuditoriaCatalogo::rotuloEntidade($linha->entidade),
                'total' => (int) $linha->total,
            ])->all(),
            ['total' => self::FORMATO_INTEIRO],
        );
    }

    // ── Consultas de base ───────────────────────────────────────────────────

    private function trilhaDoPeriodo(RelatorioFiltros $filtros): Builder
    {
        return $this->naClinica(AuditLog::query(), $filtros)
            ->whereBetween('audit_logs.created_at', $this->janela($filtros));
    }

    private function porEntidade(RelatorioFiltros $filtros, ?int $limite = 12): Collection
    {
        return $this->trilhaDoPeriodo($filtros)
            ->selectRaw('audit_logs.entidade as entidade, COUNT(*) as total')
            ->groupBy('audit_logs.entidade')
            ->orderByDesc('total')
            ->when($limite, fn (Builder $query, int $n) => $query->limit($n))
            ->get();
    }

    private function porUsuario(RelatorioFiltros $filtros, ?int $limite = null): Collection
    {
        return $this->trilhaDoPeriodo($filtros)
            ->leftJoin('users', 'users.id', '=', 'audit_logs.user_id')
            ->selectRaw('users.name as nome, COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN audit_logs.acao = ? THEN 1 ELSE 0 END) as acessos', [self::ACAO_LOGIN])
            ->selectRaw('MAX(audit_logs.created_at) as ultimo')
            ->groupBy('users.name')
            ->orderByDesc('total')
            ->when($limite, fn (Builder $query, int $n) => $query->limit($n))
            ->get();
    }

    /**
     * Uma linha por tipo de importação, com lotes, linhas lidas e recusadas.
     *
     * Só lotes CONFIRMADOS: a pré-visualização que ninguém confirmou não é uma
     * importação, é uma tentativa abandonada — contá-la inflaria o número com
     * trabalho que nunca chegou ao banco.
     *
     * @return array<int, array{rotulo: string, lotes: int, linhas: int, invalidas: int}>
     */
    private function porTipoDeImportacao(RelatorioFiltros $filtros): array
    {
        $tipos = [
            'paciente_import_lotes' => 'Pacientes',
            'solicitacao_import_lotes' => 'Solicitações',
            'guia_import_lotes' => 'Guias',
            'lancamento_import_lotes' => 'Sessões',
            'conciliacao_import_lotes' => 'Conciliações',
        ];

        [$de, $ate] = $this->janela($filtros);
        $resultado = [];

        foreach ($tipos as $tabela => $rotulo) {
            $linha = DB::table($tabela)
                ->whereNotNull('confirmado_em')
                ->whereBetween('confirmado_em', [$de, $ate])
                ->when(
                    ! $filtros->todasAsClinicas(),
                    fn ($query) => $query->where('tenant_id', $filtros->tenantId),
                )
                ->selectRaw('COUNT(*) as lotes, COALESCE(SUM(total_linhas), 0) as linhas, COALESCE(SUM(total_invalidas), 0) as invalidas')
                ->first();

            $resultado[] = [
                'rotulo' => $rotulo,
                'lotes' => (int) $linha->lotes,
                'linhas' => (int) $linha->linhas,
                'invalidas' => (int) $linha->invalidas,
            ];
        }

        return $resultado;
    }

    /** @return array{0: string, 1: string} */
    private function janela(RelatorioFiltros $filtros): array
    {
        return [
            $filtros->periodo->inicio()->format('Y-m-d H:i:s'),
            $filtros->periodo->fim()->format('Y-m-d H:i:s'),
        ];
    }
}
