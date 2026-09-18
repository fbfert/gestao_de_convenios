# Plano — Relatórios no Gestão de Convênios

Data: 18/09/2026. Destino sugerido: `docs/plano-relatorios.md` no repo, para os prompts referenciarem com `@docs/plano-relatorios.md`.

## Decisões fechadas

| Tema | Decisão |
|---|---|
| Menu | "Gestão de Convênios" vira **grupo** no padrão de Cadastros/Operação: clicar abre página de cartões com dois filhos, **Painel** (`/dashboard`, o atual) e **Relatórios** (`/relatorios`). |
| Tela | Uma rota `/relatorios` com quatro abas: **Operação**, **Financeiro**, **Automações**, **Uso**. Filtros no topo valem para todas as abas. |
| Filtros | Período com presets (hoje, 7 dias, 30 dias, mês atual, mês anterior, intervalo livre) + comparação com o período anterior; convênio; especialidade; profissional. Super admin escolhe clínica ou "todas". |
| Permissão | Uma por aba: `relatorios.operacao`, `relatorios.financeiro`, `relatorios.automacoes`, `relatorios.uso`. Aba sem permissão não aparece; página some do menu se o papel não tem nenhuma. |
| Gráficos | **Recharts**, pintado só com tokens do design system (CSS vars), para passar no `ds:check`. |
| Cálculo | Direto no banco, agregado por query, cache de 5 min por tenant + filtros. Sem tabela de snapshot (só a NeuroKids em produção, < 1 mês de dados — pré-agregar agora é otimização prematura). |
| Export | CSV nativo em cada tabela. XLSX via `openspout/openspout` (leve, streaming) na mesma entrega, se couber; senão vira tarefa separada. |
| Processo | OpenSpec: change `relatorios-de-uso` com proposal/design/tasks/specs antes do código; `openspec validate` ao fim; archive na entrega. |

## Riscos e avisos antes de começar

- **Escopo de tenant.** Todos os models usam `BelongsToTenant` (escopo global). A visão "todas as clínicas" do super admin precisa de `withoutGlobalScope` explícito no service de relatórios e **em nenhum outro lugar**. Teste de isolamento obrigatório: usuário comum nunca vê número de outro tenant, mesmo passando `tenant_id` na query.
- **Datas de transição, não `created_at`.** Já é regra do card de guias (`dashboard-cards-por-linha`): taxa de negação, tempo em status e "guias aprovadas no período" saem de `guia_status_historico`, não da data de criação da guia.
- **Financeiro depende de campos que não confirmei.** Os models `ConciliacaoFinanceira`, `AnaliticoUnimedLinha` e `MovimentoFinanceiro` precisam ser lidos antes de fechar as métricas de glosa e valor pago. O prompt 0 manda fazer isso e registrar o que existe.
- **Login pode não estar auditado.** A aba Uso conta "usuários ativos" via `audit_logs`. Se `AuthController` não grava ação de login, incluir isso (é uma linha) — sem ela não existe métrica de acesso.
- **Uma clínica, um mês de dados.** Os gráficos vão parecer vazios no início. O `DemoDataSeeder` precisa gerar histórico de ~90 dias (status histórico, execuções, audit) para a tela ser avaliável e testável.
- **Peso no menu.** O grupo novo adiciona um clique para chegar ao dashboard (decisão consciente pela consistência). Se a operação reclamar, a `HomePage` pode continuar redirecionando para `/dashboard`.

## Métricas por aba

Toda métrica de KPI vem com o valor do período anterior de mesmo tamanho (`anterior`) para a variação. Toda série aceita `granularidade` = `dia | semana | mes` (escolhida automaticamente pelo tamanho do período, com override).

### Operação (`relatorios.operacao`)

KPIs: solicitações criadas; guias geradas; taxa de aprovação (`approved` + `finalized`) ÷ (`approved` + `finalized` + `denied`) contadas pela transição no período; taxa de negação; tempo médio e mediano de `under_review` → decisão (horas); tempo médio de `approved` → `finalized`; sessões realizadas (`lancamentos.status = completed`), faltas (`missed`) e canceladas; taxa de utilização das antecipações (`qtd_utilizada ÷ qtd_autorizada` dos ciclos que tocam o período); senhas vencendo em N dias (N = `configuracoes_globais.senha_alerta_dias`); antecipações manuais no período.

Gráficos: área empilhada de guias por status ao longo do tempo; funil `under_review → approved → finalized` com `denied` ao lado; barras de solicitações por especialidade; barras horizontais de sessões por profissional (realizadas × faltas); barras de guias por convênio; linha de tempo médio de decisão por semana.

Tabelas (exportáveis): por especialidade, por profissional e por convênio — solicitações, guias, aprovadas, negadas, % aprovação, sessões, faltas.

### Financeiro (`relatorios.financeiro`)

KPIs: valor executado (sessões `completed` × `tabela_valores` na vigência, via `TabelaValoresService`); valor apresentado e valor pago pelo convênio (analíticos importados); glosa em R$ e %; conciliações `pending / reviewed / paid`; divergências apontadas; repasse estimado por profissional (percentual do cadastro).

Gráficos: linha executado × pago por mês; barras de glosa por convênio e por especialidade; pareto dos motivos de glosa (se o analítico traz motivo — verificar campo); barras horizontais de repasse por profissional; pizza da situação das conciliações.

Tabelas: por convênio e por profissional — executado, apresentado, pago, glosa, % glosa, repasse.

### Automações e integrações (`relatorios.automacoes`)

Fontes: `automacao_execucoes`, `automacao_eventos`, `conector_execucoes`, `clinica_sync_execucoes`, `saude_componentes`, `convenio_credenciais.automation_paused_at`.

KPIs: execuções no período; taxa de sucesso; falhas por `erro_codigo` (top 5); duração média e p95 (`finished_at − started_at`); tempo médio em fila (`started_at − queued_at`); reprocessamentos (`parent_id` não nulo); dias com disjuntor pausado; sincronizações com o clínica (ok/erro) e última bem-sucedida; componentes de saúde fora do ar no período (contagem de eventos, se `SaudeComponente` guarda histórico — verificar).

Gráficos: linha de execuções ok × erro por dia; barras por `operacao`; pareto de `erro_codigo`; histograma de duração; linha de sync do clínica.

Tabelas: por operação e por código de erro, com link para `/automacoes` já filtrado.

### Uso do sistema (`relatorios.uso`)

Fontes: `audit_logs`, `alertas`, `novidade_leituras`, lotes de importação (`*_import_lotes`), documentos lidos por IA (`solicitacao_documentos`/`paciente_documentos` — verificar onde o resultado da IA fica registrado), e-mails enviados (verificar se há registro; se não houver, deixar fora e anotar como não-objetivo).

KPIs: usuários ativos (distinct `user_id` em `audit_logs`); logins; ações por dia (média); ações por papel; importações por tipo e taxa de linhas com erro; documentos processados por IA; alertas gerados, lidos e tempo médio até leitura; novidades lidas ÷ usuários.

Gráficos: linha de ações por dia; barras por entidade (`audit_logs.entidade`); barras por usuário (top 10); barras por hora do dia (0–23) para mostrar quando a clínica usa o sistema; barras de importações por tipo.

Tabelas: por usuário (ações, último acesso, entidades mais tocadas) e por entidade.

## Contrato da API

```
GET /api/relatorios/{aba}            aba = operacao | financeiro | automacoes | uso
  ?de=YYYY-MM-DD&ate=YYYY-MM-DD      obrigatórios; máximo 366 dias
  &granularidade=dia|semana|mes      opcional; padrão calculado pelo tamanho do período
  &convenio_id=&especialidade_id=&profissional_id=   opcionais; aplicam onde fazem sentido
  &tenant_id=<id>|todos              só super admin; ignorado (403) para os demais
  &comparar=1                        devolve o período anterior nos KPIs

200 {
  "data": {
    "periodo":    { "de", "ate", "granularidade", "dias" },
    "comparacao": { "de", "ate" } | null,
    "filtros_aplicados": { ... },
    "kpis":    [ { "key", "label", "valor", "anterior", "formato": "inteiro|percentual|moeda|horas", "hint" } ],
    "series":  [ { "key", "label", "tipo": "linha|area|barras|funil|pizza", "pontos": [ { "x": "2026-09-01", "...": n } ], "campos": [ { "key", "label" } ] } ],
    "tabelas": [ { "key", "label", "colunas": [ { "key", "label", "formato" } ], "linhas": [ {...} ] } ],
    "gerado_em": "...", "cache": true|false
  }
}

GET /api/relatorios/{aba}/export?tabela=<key>&formato=csv|xlsx   mesmos filtros; streaming
```

Regras: cada aba exige a permissão própria via middleware `permission:`; cache `Cache::remember` com chave `relatorios:{tenant}:{aba}:{hash dos filtros}` por 5 min (`cache: true` na resposta quando veio do cache); `kpis` de valor monetário sempre em centavos inteiros na API, formatação na UI.

## Estrutura de código

API:
- `app/Http/Controllers/RelatorioController.php` — valida filtros (`RelatorioFiltrosRequest`), resolve tenant, chama o serviço da aba, devolve o contrato.
- `app/Services/Relatorios/RelatorioFiltros.php` (DTO), `RelatorioPeriodo.php` (presets, período anterior, granularidade), `RelatorioOperacaoService.php`, `RelatorioFinanceiroService.php`, `RelatorioAutomacoesService.php`, `RelatorioUsoService.php`, `RelatorioExportService.php`.
- Migration `sync_relatorios_permissions_to_existing_roles` seguindo o padrão das `sync_*` existentes (concede as quatro ao papel admin; `relatorios.operacao` e `relatorios.automacoes` ao operador — ajustar conforme o catálogo de papéis em `perfis-e-permissoes`).
- Rotas em `routes/api.php` dentro do grupo autenticado.

Web:
- `web/src/features/relatorios/`: `RelatoriosPage.tsx` (abas com Radix Tabs, já é dependência), `FiltrosRelatorio.tsx`, `KpiTile.tsx`, `GraficoLinha.tsx` / `GraficoBarras.tsx` / `GraficoFunil.tsx` / `GraficoPizza.tsx` (wrappers de Recharts com tokens), `TabelaRelatorio.tsx` (com botão exportar), `abas/AbaOperacao.tsx` etc., `api.ts` (hooks TanStack Query, `staleTime` 5 min), `index.ts`.
- `web/src/lib/graficos.ts` — paleta categórica e sequencial lida de CSS vars do design system (`getComputedStyle`), nunca hex literal.
- `routes/navigation.ts` — grupo novo; `AppRoutes.tsx` — rotas `/relatorios` e página de grupo.
- Dependência nova: `recharts`. Dev: nada.

## Como usar os prompts

1. Cada prompt roda numa sessão nova do Claude Code no VSCode, dentro da raiz do repo, com a árvore limpa (`git status` vazio).
2. Rodar na ordem. Cada prompt termina em commit próprio. Não avançar com teste vermelho.
3. Os prompts assumem que este arquivo está em `docs/plano-relatorios.md`. Se colocar em outro lugar, ajuste a referência.
4. Ao final de cada prompt, o resumo deve listar specs lidas e comandos de validação rodados (regra do `AGENTS.md`).

---

## Prompt 0 — Change OpenSpec

```text
Leia AGENTS.md, openspec/config.yaml, docs/decisoes-arquitetura.md, docs/schema.md e @docs/plano-relatorios.md.
Leia também, como referência de formato, openspec/changes/dashboard-cards-por-linha (proposal.md, design.md, tasks.md, specs/) e openspec/changes/central-de-alertas.

Antes de escrever qualquer coisa, inspecione e me devolva um resumo curto do que existe, porque o plano marca alguns pontos como "verificar":
1. Campos de app/Models/ConciliacaoFinanceira.php, AnaliticoUnimedLinha.php, AnaliticoUnimedLote.php, MovimentoFinanceiro.php e as migrations correspondentes: quais valores (apresentado, pago, glosa, motivo) existem de fato.
2. Se AuthController grava algo em audit_logs no login. Se não, a change inclui isso.
3. Onde fica registrado o resultado de leitura por IA de documentos (PedidoMedicoAiService, CarteirinhaAiService, RegistroSessoesAiService) e se há registro de e-mail enviado.
4. Como app/Concerns/BelongsToTenant.php resolve o tenant e como o super admin é identificado (users.super_admin) — preciso disso para a visão "todas as clínicas".
5. Como as migrations sync_*_to_existing_roles concedem permissão nova e como o catálogo de permissões chega ao PermissionController/front (web/src/lib/permissoes).
6. Como as páginas de grupo (Cadastros, Operação) são montadas em web/src/features/grupos e web/src/routes, para o grupo novo seguir o mesmo componente.

Com isso, crie a change openspec/changes/relatorios-de-uso com:
- proposal.md: Why (a clínica só enxerga contagens do momento; não tem histórico nem comparação), What Changes (menu vira grupo com Painel e Relatórios; página /relatorios com quatro abas; endpoints; permissões; export), Non-goals explícitos (snapshot pré-agregado, PDF, relatório por paciente, e-mail agendado de relatório, o que faltar de dado conforme o item 1–3 acima), Spec updates.
- design.md: contrato da API exatamente como no plano; DTO de filtros e período; regra de tenant para super admin (withoutGlobalScope só no serviço de relatórios); cache de 5 min; datas por transição de status; Recharts com tokens e como isso passa no ds:check; onde entra cada permissão.
- specs/relatorios-de-uso/spec.md com requisitos e cenários (formato dos specs existentes) cobrindo: filtros e validação, permissão por aba, isolamento de tenant, comparação de período, granularidade, cada KPI com sua definição de cálculo, export CSV/XLSX, menu e página de grupo.
- tasks.md dividido em blocos revisáveis, na ordem dos prompts 1 a 6 do plano.
- .openspec.yaml igual ao das outras changes.

Ajuste as métricas do plano ao que existe de verdade no banco: o que não tiver dado vai para Non-goals com uma linha explicando. Se algo for ambíguo, pare e pergunte antes de escrever a spec.
Rode openspec validate. Faça um commit "spec: change relatorios-de-uso".
No resumo final liste specs lidas e comandos rodados.
```

## Prompt 1 — Permissões, rotas, período e esqueleto da API

```text
Leia AGENTS.md, openspec/changes/relatorios-de-uso (proposal, design, tasks, specs) e @docs/plano-relatorios.md. Implemente o bloco 1 do tasks.md:

1. Permissões relatorios.operacao, relatorios.financeiro, relatorios.automacoes, relatorios.uso, registradas no catálogo de permissões existente e concedidas por migration sync_relatorios_permissions_to_existing_roles, no mesmo padrão das migrations sync_* anteriores (admin recebe as quatro; operador recebe operacao e automacoes; ajuste se o catálogo de papéis disser outra coisa).
2. app/Services/Relatorios/RelatorioFiltros.php (DTO imutável) e RelatorioPeriodo.php: presets, período anterior de mesmo tamanho, granularidade automática (até 31 dias: dia; até 120: semana; acima: mês), limite de 366 dias, tudo em America/Sao_Paulo.
3. app/Http/Requests/RelatorioFiltrosRequest.php validando de/ate/granularidade/convenio_id/especialidade_id/profissional_id/tenant_id/comparar. tenant_id só é aceito quando users.super_admin; para os demais devolve 403 mesmo que enviado.
4. app/Http/Controllers/RelatorioController.php com show(aba) e export(aba). Por ora cada service devolve o contrato com kpis/series/tabelas vazios, para a rota existir e ser testável.
5. Resolução de tenant: usuário comum sempre no próprio tenant via escopo global; super admin com tenant_id=<id> força aquele tenant; tenant_id=todos usa withoutGlobalScope(BelongsToTenant) apenas dentro dos services de relatório. Documente isso em comentário curto no service base.
6. Cache: Cache::remember com chave relatorios:{tenant|todos}:{aba}:{sha1 dos filtros}, 5 min; campo cache na resposta.
7. Rotas em routes/api.php com middleware permission:relatorios.{aba}.

Testes (PHPUnit, seguindo os feature tests existentes): 403 sem permissão; 422 para período inválido ou > 366 dias; 403 para tenant_id vindo de usuário comum; super admin com tenant_id=todos não filtra por tenant; período anterior calculado certo para os presets; granularidade automática.

Rode php artisan test --filter Relatorio e openspec validate. Commit "feat(relatorios): permissões, filtros e esqueleto da API". Resumo final com specs lidas e comandos rodados.
```

## Prompt 2 — Serviços de cálculo (as quatro abas)

```text
Leia AGENTS.md, openspec/changes/relatorios-de-uso e @docs/plano-relatorios.md. Implemente o bloco 2 do tasks.md: os quatro services de relatório com os KPIs, séries e tabelas definidos na spec.

Regras que não podem ser quebradas:
- Taxa de aprovação, negação, tempo em status e "guias aprovadas no período" saem de guia_status_historico pela data da transição, nunca de guias.created_at. Reaproveite o que DashboardGuiasCardService já faz.
- Valor executado usa TabelaValoresService na vigência da data da sessão. Valores monetários em centavos inteiros na resposta.
- Nada de regra de convênio hardcoded (regra de ouro do README).
- Agregações em SQL (groupBy/selectRaw), não em coleção PHP. Uma query por série, não uma por ponto. Precisa rodar em SQLite (dev/teste) e MariaDB (produção): use DATE()/strftime com cuidado ou uma helper para truncar por dia/semana/mês nos dois bancos.
- Todo KPI devolve anterior quando comparar=1.
- Filtros convenio_id/especialidade_id/profissional_id aplicam onde a entidade tem a coluna; onde não faz sentido (ex.: automações por profissional), ignore e registre em filtros_aplicados quais foram usados.

Séries e tabelas exatamente como listadas na spec da change, com key estável (a UI vai depender delas).

Testes: um arquivo por service, com dados criados por factory cobrindo cada KPI com números conhecidos (ex.: 3 aprovadas + 1 negada no período => 75%), período anterior, isolamento de tenant, e um teste que roda cada aba contra o DemoDataSeeder sem erro.

Rode php artisan test --filter Relatorio e openspec validate. Commit "feat(relatorios): cálculo das abas operação, financeiro, automações e uso". Resumo final com specs lidas e comandos rodados.
```

## Prompt 3 — Export CSV/XLSX e seed com histórico

```text
Leia AGENTS.md, openspec/changes/relatorios-de-uso e @docs/plano-relatorios.md. Implemente o bloco 3 do tasks.md:

1. RelatorioExportService: GET /api/relatorios/{aba}/export?tabela=<key>&formato=csv|xlsx com os mesmos filtros. CSV nativo (UTF-8 com BOM, separador ; para o Excel brasileiro, datas dd/mm/aaaa, moeda com vírgula). XLSX via composer require openspout/openspout, em streaming. Nome do arquivo: relatorio-{aba}-{tabela}-{de}-{ate}.{ext}. Mesma permissão da aba. Registre a exportação em audit_logs (acao relatorio.exportado, payload com aba, tabela, filtros).
2. DemoDataSeeder: gere ~90 dias de histórico determinístico (seed fixo) com transições em guia_status_historico, lançamentos com faltas, execuções de automação ok/erro com durações variadas, audit_logs em horários variados de vários usuários, analíticos com glosa. Mantenha os cenários que já existem; só adicione. A tela de Relatórios precisa parecer viva com o seed.

Testes: export CSV com conteúdo esperado e cabeçalho; export XLSX abre e tem as linhas; 403 sem permissão; auditoria gravada; seeder roda em migrate:fresh --seed sem erro.

Rode php artisan test e openspec validate. Commit "feat(relatorios): export CSV/XLSX e seed com histórico". Resumo final com specs lidas e comandos rodados.
```

## Prompt 4 — Menu em grupo e base da tela

```text
Leia AGENTS.md, openspec/changes/relatorios-de-uso, @docs/plano-relatorios.md, design-system-xiax-agenda.md (inteiro, especialmente §11 e as regras do ds:check), web/src/routes/navigation.ts, web/src/routes/AppRoutes.tsx, web/src/features/grupos e web/src/features/dashboard. Implemente o bloco 4 do tasks.md:

1. navigation.ts: a entrada '/dashboard' vira NavGroup { to: '/inicio', label: 'Gestão de Convênios' } com filhos Painel (to '/dashboard', descricao curta, sem permissão) e Relatórios (to '/relatorios', permissao: qualquer uma das quatro relatorios.* — estenda NavLeaf com permissaoQualquerUma?: string[] ou equivalente, e trate em filtrarItens/montarMenu; escolha o mais simples e explique em comentário). Página de grupo em /inicio usando o mesmo componente de Cadastros/Operação. HomePage continua levando ao /dashboard.
2. web/src/features/relatorios/: RelatoriosPage.tsx com Radix Tabs (só abas permitidas; se nenhuma, redireciona ao dashboard), FiltrosRelatorio.tsx (presets, intervalo livre, convênio/especialidade/profissional carregados das APIs existentes, seletor de clínica só para super_admin, toggle comparar), estado dos filtros na URL (searchParams) para link compartilhável, api.ts com hooks TanStack Query (staleTime 5 min, chave inclui filtros), KpiTile.tsx (valor, variação vs anterior com seta e cor semântica do design system, hint) e TabelaRelatorio.tsx (ordenável como as listagens existentes, botão Exportar CSV/XLSX).
3. Instale recharts. Crie web/src/lib/graficos.ts que monta a paleta a partir de CSS vars do design system (categórica: 6 cores de tokens existentes; sequencial: uma cor com opacidades) e wrappers GraficoLinha, GraficoArea, GraficoBarras, GraficoFunil, GraficoPizza em features/relatorios/graficos/. Nenhum hex literal: cor sempre via var(--token). Tooltip e legenda com componentes do próprio design system. Estados de vazio ("sem dados no período") e de carregamento (skeleton) em todo gráfico. Os dois temas (padrão e alto contraste) precisam funcionar — teste trocando o tema.
4. Aba Operação completa com os KPIs, gráficos e tabelas da spec. As outras três abas ficam com placeholder "em construção" neste bloco.

Rode npm run lint (inclui ds:check), npm run build e os testes que existirem. Se o ds:check reprovar algo do Recharts, resolva pelo caminho do design system (token/classe), não por exceção na regra, e me diga o que foi. Commit "feat(relatorios): menu em grupo, filtros e aba Operação". Resumo final com specs lidas e comandos rodados.
```

## Prompt 5 — Abas Financeiro, Automações e Uso

```text
Leia AGENTS.md, openspec/changes/relatorios-de-uso, @docs/plano-relatorios.md e web/src/features/relatorios. Implemente o bloco 5 do tasks.md: as abas Financeiro, Automações e Uso, reaproveitando KpiTile, os wrappers de gráfico e TabelaRelatorio.

- Financeiro: formatação em BRL, % de glosa com uma casa, pizza das conciliações, pareto de motivos de glosa (se a spec manteve).
- Automações: linhas ok × erro, barras por operação, pareto de erro_codigo, histograma de duração; linha da tabela de erro leva a /automacoes já filtrado (verifique quais filtros a listagem aceita).
- Uso: barras por hora do dia, por entidade e por usuário; tabela por usuário com último acesso.

Responsividade: em telas estreitas os KPIs viram 2 colunas, gráficos empilham, tabelas rolam horizontalmente — siga o contrato de responsividade do design system.

Playwright: um e2e que loga como admin com o DemoDataSeeder, abre /relatorios, troca as quatro abas, muda preset de período, aplica filtro de convênio, confere que a URL muda e que ao menos um KPI e um gráfico renderizam em cada aba; um e2e que loga como papel sem relatorios.financeiro e confirma que a aba não existe.

Rode npm run lint, npm run build, e2e, e openspec validate. Commit "feat(relatorios): abas Financeiro, Automações e Uso". Resumo final com specs lidas e comandos rodados.
```

## Prompt 6 — Fechamento

```text
Leia AGENTS.md, openspec/changes/relatorios-de-uso e @docs/plano-relatorios.md. Bloco 6, fechamento:

1. Revise a change contra o código: cada requisito da spec tem teste? Marque tasks.md. O que ficou de fora vai para Non-goals com o motivo.
2. Manual: adicione a seção "Relatórios" em ManualController/manual do produto (como as outras features fazem) e uma Novidade (NovidadeService) anunciando a tela, para o card do painel exibir.
3. docs/resumo-entregas-<data de hoje>.md no formato dos anteriores: o que entrou, decisões, pendências, comandos de deploy (composer install por causa do openspout, migrate para a permissão, npm ci/build).
4. Se houver ADR nova (o grupo "Gestão de Convênios" no menu e o withoutGlobalScope restrito aos relatórios merecem uma linha em docs/decisoes-arquitetura.md), registre.
5. php artisan test completo, npm run lint, npm run build, e2e, openspec validate, e então openspec archive relatorios-de-uso.

Commit "docs(relatorios): manual, novidade, resumo de entrega e archive da change". Resumo final com specs lidas, comandos rodados e o número final de testes PHP e e2e.
```

## Depois da entrega

- Medir o tempo de resposta de cada aba com dados reais da NeuroKids; se alguma passar de 1,5 s sem cache, é aí que entra o snapshot diário — não antes.
- Segundo tenant em produção é o teste real do isolamento e da visão "todas as clínicas".
- Candidatos para a v2: relatório agendado por e-mail (reaproveita a esteira de alertas), PDF, comparação entre clínicas para o super admin, metas por especialidade.
