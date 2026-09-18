> Blocos na ordem dos prompts 1 a 6 de `docs/plano-relatorios.md`. Cada bloco
> fecha em commit próprio, e nenhum avança com teste vermelho.

## 1. Permissões, filtros e esqueleto da API

- [x] 1.1 Permissões `relatorios.operacao`, `relatorios.financeiro`, `relatorios.automacoes` e `relatorios.uso` no catálogo, e migration `sync_relatorios_permissions_to_existing_roles` no padrão das `sync_*` anteriores (a mais recente é `2026_09_15_100002_sync_convenios_manage_permission_to_existing_roles`).
- [x] 1.2 `RelatorioFiltros` (DTO imutável) e `RelatorioPeriodo`: presets, período anterior de mesmo tamanho, granularidade automática (≤31 dias dia, ≤120 semana, acima mês), teto de 366 dias, tudo em America/Sao_Paulo.
- [x] 1.3 `RelatorioFiltrosRequest`. `tenant_id` só é aceito de super admin (`users.super_admin`, ver `User::ehSuperAdmin()`); dos demais, 403 mesmo que enviado.
- [x] 1.4 `RelatorioController::show` e `::export`, com cada service devolvendo o contrato vazio, para a rota existir e ser testável.
- [x] 1.5 Resolução de tenant no service base, com o comentário explicando por que o `withoutGlobalScope` vive só ali.
- [x] 1.6 Cache de 5 min com chave `relatorios:{tenant|todos}:{aba}:{sha1 dos filtros}`, e o campo `cache` na resposta.
- [x] 1.7 Rotas com `middleware('permission:relatorios.{aba}')`.
- [x] 1.8 Testes: 403 sem permissão; 422 para período inválido, invertido e acima de 366 dias; 403 para `tenant_id` de usuário comum; super admin com `todos` não filtra por tenant; período anterior correto nos presets; granularidade automática nos três limites.

## 2. Cálculo das quatro abas

- [x] 2.1 Helper de truncamento de data por dia/semana/mês que funcione em SQLite e MariaDB — é o defeito mais fácil de deixar passar, porque a suíte roda no primeiro e a produção no segundo.
- [x] 2.2 `RelatorioOperacaoService`, reaproveitando de `DashboardGuiasCardService` o cálculo por transição. Antecipações entram como geradas × ignoradas com % de dispensa (o KPI de cota saiu — ver proposal).
- [x] 2.3 `RelatorioFinanceiroService`. Apresentado = `total_pago + total_glosado` dos lotes; valores em centavos inteiros; sem lote no período, indicador ausente e não zero.
- [x] 2.4 `RelatorioAutomacoesService`, incluindo o tempo fora do ar a partir do histórico do bloco 2.6.
- [x] 2.5 `RelatorioUsoService`. Acessos saem de `acesso.login` em `audit_logs`, que **já é gravado** por `AuthController`.
- [x] 2.6 Tabela `saude_componente_eventos` e gravação em `SaudeService::registrarHeartbeat`, só na MUDANÇA de estado.
- [x] 2.7 Agregação em SQL (`groupBy`/`selectRaw`), uma query por série — nunca uma por ponto, nunca agregação em coleção PHP.
- [x] 2.8 Todo KPI devolve `anterior` quando a comparação for pedida.
- [x] 2.9 Testes: um arquivo por service, com números conhecidos por factory (ex.: 3 aprovadas + 1 negada ⇒ 75%); período anterior; isolamento de tenant; e cada aba rodando contra o `DemoDataSeeder` sem erro.

## 3. Export e seed com histórico

- [x] 3.1 `RelatorioExportService`: CSV nativo (UTF-8 com BOM, separador `;`, data dd/mm/aaaa, decimal com vírgula) e XLSX em streaming via `openspout/openspout`. Nome `relatorio-{aba}-{tabela}-{de}-{ate}.{ext}`.
- [x] 3.2 Mesma permissão da aba, e registro em `audit_logs` com ação `relatorio.exportado`.
- [x] 3.3 `DemoDataSeeder` com ~90 dias determinísticos (seed fixo): transições em `guia_status_historico`, lançamentos com faltas, execuções de automação ok/erro com durações variadas, `audit_logs` em horários variados de vários usuários, analíticos com glosa. Só acrescenta — os cenários existentes ficam.
- [x] 3.4 Testes: conteúdo e cabeçalho do CSV; XLSX abre e tem as linhas; 403 sem permissão; auditoria gravada; `migrate:fresh --seed` sem erro.

## 4. Menu em grupo, filtros e aba Operação

- [x] 4.1 `navigation.ts`: `/dashboard` vira grupo "Gestão de Convênios" com Painel e Relatórios. Relatórios aparece com QUALQUER uma das quatro permissões — estender o tipo do item de menu e tratar na filtragem, com comentário explicando a escolha.
- [x] 4.2 Página de grupo em `/inicio` reusando `features/grupos/GrupoPage.tsx`, que é o mesmo componente de Cadastros e Operação.
- [x] 4.3 `RelatoriosPage` com abas (só as permitidas; nenhuma ⇒ redireciona ao painel), `FiltrosRelatorio`, estado na URL, `api.ts` com TanStack Query (`staleTime` 5 min), `KpiTile` (valor, variação, ausência ≠ zero) e `TabelaRelatorio` (ordenável, com exportar).
- [x] 4.4 `recharts` instalado; `web/src/lib/graficos.ts` montando a paleta a partir de CSS vars; wrappers de linha, área, barras, funil e pizza. Nenhum hex literal. Estados de vazio e de carregamento em todo gráfico. Os dois temas conferidos.
- [x] 4.5 Aba Operação completa. As outras três ficam em "em construção" neste bloco.
- [x] 4.6 `npm run lint` (inclui `ds:check`) e `npm run build`. Se o `ds:check` reprovar algo do Recharts, resolver pelo design system — token ou classe —, nunca por exceção na regra.

## 5. Abas Financeiro, Automações e Uso

- [x] 5.1 Financeiro: BRL, glosa percentual com uma casa, pizza das conciliações, pareto de motivos (o campo `motivo` existe em `analitico_unimed_linhas`).
- [x] 5.2 Automações: ok × erro por dia, barras por operação, pareto de código de erro, histograma de duração, e a linha da tabela levando a `/automacoes` já filtrado.
- [x] 5.3 Uso: barras por hora do dia, por entidade e por usuário; tabela por usuário com último acesso.
- [x] 5.4 Responsividade: KPIs em 2 colunas no estreito, gráficos empilhados, tabelas rolando na horizontal.
- [x] 5.5 E2E: admin abre `/relatorios`, troca as quatro abas, muda preset, aplica filtro de convênio, confere que a URL muda e que cada aba rende ao menos um KPI e um gráfico; e um papel sem `relatorios.financeiro` não enxerga a aba.
- [x] 5.6 **Atenção ao e2e**: a suíte roda com um worker e banco compartilhado (`playwright.config.ts`, commit `29da298`). Spec de relatório que mexer em dado de semente precisa desfazer no fim, como `adicionar-sessoes` faz com o convênio Unimed.

## 6. Fechamento

- [ ] 6.1 Revisar a spec contra o código: cada requisito tem teste? O que ficou de fora vai para Non-goals com o motivo.
- [ ] 6.2 Seção "Relatórios" no manual do produto e uma novidade em `api/resources/novidades/` anunciando a tela.
- [ ] 6.3 `docs/resumo-entregas-<data>.md` no formato dos anteriores, com os comandos de deploy: `composer install` (openspout), `migrate` (permissões e `saude_componente_eventos`), `npm ci`/build.
- [ ] 6.4 ADR em `docs/decisoes-arquitetura.md` para o grupo novo no menu e para o `withoutGlobalScope` restrito aos relatórios.
- [ ] 6.5 `php artisan test`, `npm run lint`, `npm run build`, e2e, `openspec validate`, e então `openspec archive relatorios-de-uso`.

## 7. Em aberto

- [ ] 7.1 Medir o tempo de resposta de cada aba com dado real da NeuroKids. Passando de 1,5 s sem cache, é aí que entra o snapshot pré-agregado — não antes.
- [ ] 7.2 O histórico de saúde só enxerga do deploy em diante. Conferir, depois de algumas semanas, se o volume de eventos justifica expurgo.
