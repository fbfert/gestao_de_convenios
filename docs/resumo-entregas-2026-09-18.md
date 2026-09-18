# Entregas de 18/09/2026 — relatórios por período

Uma frente só: a change `relatorios-de-uso`, dos seis blocos do `docs/plano-relatorios.md`. O
sistema só sabia contar o agora — o painel mostra o que está aberto hoje e as listagens filtram o
presente. Não havia como responder "a taxa de negação piorou?", "qual convênio glosa mais?", "a
automação está mais lenta que mês passado?" nem "quem usa o sistema, e quando?".

O dado já existia em quatro origens que não conversavam: `guia_status_historico` (transições com
data), `analitico_unimed_lotes`/`_linhas` (dinheiro da operadora), `automacao_execucoes` (robô) e
`audit_logs` (gente). Faltava agregar e comparar.

## O que entrou, por bloco

| Bloco | Commit | O que entrou |
|---|---|---|
| 0. Spec | `d49bc1a` | Change `relatorios-de-uso` conferida contra o banco real |
| 1. Fundação | `2ee9300` | Quatro permissões, `RelatorioPeriodo`, `RelatorioFiltros`, escopo de clínica, cache |
| 2. Cálculo | `5f3e6ac` | Os quatro services, `RelatorioSql`, histórico de saúde |
| 3. Export e seed | `012ee12` | CSV/XLSX auditados, `DemoDataSeeder` com 90 dias |
| 4. Tela | `9fbfd72` | Menu em grupo, paleta por token, filtros na URL, aba Operação |
| 5. Abas | `5ee811e` | Financeiro, Automações e Uso; responsividade; e2e |
| 6. Fechamento | (este) | Manual, novidade, ADRs, resumo e archive |

## As decisões que mais custam se forem esquecidas

**Datas de transição, nunca `created_at`.** Aprovação, negação e tempo de decisão saem de
`guia_status_historico` pela data em que a transição ocorreu. Uma guia criada em agosto e negada em
setembro é negação de **setembro**; contar pela criação joga o número no mês errado e faz a série
mentir justamente onde ela deveria informar.

**`withoutGlobalScope` vive em um arquivo só.** `RelatorioService::naClinica()` é o único ponto do
sistema que derruba o `TenantScope` de propósito, e precisa continuar sendo — ver ADR-30. O `grep`
por `withoutGlobalScope` nos serviços de relatório tem que continuar respondendo esse método.

**A queda de componente não é vista pelo heartbeat.** Ele só acontece com o componente vivo, então
registra a volta ao ar e nunca a saída. Daí `SaudeService::sincronizarEstados()`, a cada minuto no
agendador — ver ADR-31. **Se o `schedule:run` não estiver ativo na VPS, o histórico de saúde nasce
só com recuperações.**

**Dinheiro em centavos inteiros na API.** `decimal:2` do Eloquent volta string; somar string em PHP
e depois em JS é como se perde centavo. A formatação é da tela — e do export, que faz a conversão
uma vez.

**`sentido` no contrato dos KPIs.** Cada indicador declara `maior_melhor`, `menor_melhor` ou
`neutro` junto de onde é calculado. Sem isso o front manteria uma lista de exceções por chave, longe
da definição, e o próximo KPI nasceria com "a negação subiu" pintado de verde.

## Três defeitos que só apareceram com dado real

1. **O `DemoDataSeeder` estava quebrado no HEAD.** Ainda gravava `antecipacoes.guia_id` e
   `lancamentos.antecipacao_id`, colunas derrubadas com o modelo de cota na Fase 3 —
   `db:seed --class=DemoDataSeeder` falhava. Corrigido no bloco 2, reescrito no bloco 3.

2. **Todas as guias do seed nasciam decididas hoje.** O hook de `Guia` grava a primeira transição
   com a hora de agora; a aba de Operação conta pela transição, então a série virava um pico único no
   dia do seed. O seeder passou a reescrever `guia_status_historico` espalhado nos 90 dias, e há teste
   que falha se voltarem a se concentrar.

3. **Duração de automação em horas era ilegível.** A tela mostrava "Duração média 0,1 h" e "Tempo
   médio em fila 0,0 h". Entrou o formato `duracao`: a API devolve segundos crus e a tela escolhe a
   unidade ("45 s", "3,3 min", "2,1 h"). No export a coluna sai em segundos, com a unidade no
   cabeçalho — planilha que mistura unidades numa coluna não ordena nem soma.

Junto vieram duas correções de borda: `origem`/`natureza` das linhas de analítico no seeder não
casavam com o que o importador grava (o pareto de glosa nascia vazio), e quatro entidades apareciam
com o nome da tabela no relatório de Uso, por falta de rótulo no `AuditoriaCatalogo`.

## O que ficou de fora, e por quê

Registrado nos Non-Goals da change. Os dois descobertos na implementação:

- **Pago e glosa por convênio ou por profissional.** O analítico da operadora chega agregado por
  lote, e a linha não carrega convênio nem profissional. Ratear seria estimativa apresentada como
  medição — e é um número que alguém usaria para pagar alguém.
- **Documentos lidos por IA.** Não há registro de que a leitura aconteceu; os documentos guardam o
  arquivo e um `metadata` livre, sem marca de processamento.

## Comandos de deploy

```bash
# 1. Dependência nova no backend (openspout/openspout, para o XLSX)
cd api && composer install --no-dev --optimize-autoloader

# 2. Migrations: as quatro permissões e a tabela de histórico de saúde
php artisan migrate --force

# 3. O manual e as novidades são arquivos: limpar o cache que os serve
php artisan cache:clear

# 4. Frontend (dependência nova: recharts)
cd ../web && npm ci && npm run build
```

Duas migrations entram:

| Migration | O que faz |
|---|---|
| `2026_09_18_120000_sync_relatorios_permissions_to_existing_roles` | Concede as quatro permissões: `admin` recebe todas, `funcionario` recebe operação e automações |
| `2026_09_18_120100_create_saude_componente_eventos_table` | Tabela do histórico de estado dos componentes |

**Conferir depois do deploy:**

- `/inicio` abre a página de grupo com os cartões Painel e Relatórios.
- `/relatorios` responde nas quatro abas para o admin da NeuroKids.
- Exportar uma tabela em CSV e abrir no Excel — acentos e vírgula decimal corretos.
- `php artisan schedule:list` mostra `carimbo-scheduler` a cada minuto (é ele que alimenta o
  histórico de saúde).

## Pendências

- **Tempo de resposta com dado real.** A change fixou o corte: se alguma aba passar de **1,5 s sem
  cache** na NeuroKids, é aí que entra o snapshot pré-agregado — e não antes (tarefa 7.1).
- **Histórico de saúde só enxerga do deploy em diante.** Conferir em algumas semanas se o volume de
  eventos justifica expurgo (tarefa 7.2).
- **Segundo tenant em produção** é o teste real do isolamento e da visão "todas as clínicas".

## Validação

| Suíte | Antes | Depois |
|---|---|---|
| `php artisan test` | 733 passaram | 816 passaram (3187 asserções) |
| Playwright e2e | 48 passaram | 56 passaram |
| `npm run build` | ✓ | ✓ |
| `npm run lint` (oxlint + `ds:check`) | as quatro guardas da §11 | as quatro guardas da §11 |
| `openspec validate relatorios-de-uso` | — | válido |

As quatro abas foram conferidas visualmente sobre o `DemoDataSeeder`, nos dois temas (padrão e alto
contraste) e em 390px de largura — `scrollWidth` igual à viewport nas quatro, sem rolagem horizontal
de página.

**Specs lidas:** `AGENTS.md`, `openspec/changes/relatorios-de-uso/` (proposal, design, tasks,
spec), `docs/plano-relatorios.md`, `docs/schema.md`, `docs/decisoes-arquitetura.md`,
`design-system-xiax-agenda.md` (§11, via `scripts/verificar-design-system.mjs`).

**Documentação:** ADR-30 e ADR-31 em `docs/decisoes-arquitetura.md`; seção 4.1 no manual do produto;
novidade `2026-09-18-relatorios-por-periodo.md`.
