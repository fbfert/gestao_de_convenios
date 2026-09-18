## Why

A clínica só enxerga o agora. O painel conta o que está aberto hoje — guias pendentes, senhas vencendo, alertas — e as listagens filtram o presente. Não há como responder "a taxa de negação piorou?", "qual convênio glosa mais?", "a automação está mais lenta que mês passado?" nem "quem usa o sistema, e quando?".

O dado existe: `guia_status_historico` guarda cada transição com data, `automacao_execucoes` guarda duração e erro, `audit_logs` guarda quem fez o quê e quando, e os analíticos importados trazem pago e glosado. Falta agregar e comparar períodos.

## What Changes

- **"Gestão de Convênios" vira grupo no menu**, no padrão de Cadastros e Operação: uma página de cartões com dois filhos, **Painel** (`/dashboard`, o de hoje) e **Relatórios** (`/relatorios`).
- **`/relatorios` com quatro abas** — Operação, Financeiro, Automações e Uso —, filtros no topo valendo para todas: período com presets e comparação com o período anterior, convênio, especialidade, profissional, e escolha de clínica para o super admin.
- **Quatro permissões**, uma por aba. Aba sem permissão não aparece; a página some do menu para quem não tem nenhuma.
- **`GET /api/relatorios/{aba}`** devolvendo KPIs, séries e tabelas num contrato único, com cache de 5 minutos por tenant e filtros.
- **Export CSV e XLSX** por tabela, registrado na auditoria.
- **Histórico de saúde dos componentes** (ver Decisões abaixo).
- **`DemoDataSeeder` passa a gerar ~90 dias de histórico**, senão a tela nasce vazia e não dá para avaliar nem testar.

## Decisões tomadas contra o banco real

O plano em `docs/plano-relatorios.md` marcava seis pontos como "verificar". Verificados antes de escrever esta spec, e o que mudou:

- **A "taxa de utilização das antecipações" saiu.** O plano pedia `qtd_utilizada ÷ qtd_autorizada`, colunas do modelo de cota que **não existe mais** — foi derrubado junto com `ciclo_inicio`/`ciclo_fim` na change `antecipacao-alerta-e-fila-de-elegiveis`, e a disponibilidade de sessões passou a ser contada ao vivo contra a guia. No lugar entra **antecipações geradas × ignoradas no período, com a % de dispensa**, que é o que a tabela atual responde e mede a decisão do operador. Absorve o KPI "antecipações manuais no período", que virava duplicata.
- **O login já é auditado.** O plano previa incluir isso ("é uma linha"). `AuthController` já grava `acesso.login` e `acesso.login_recusado` via `Auditoria::registrar`. A aba Uso consome o que existe.
- **Motivo de glosa existe.** `analitico_unimed_linhas` tem `motivo`, `tipo`, `valor` e `valor_normalizado`, e `analitico_unimed_lotes` tem `total_pago`, `total_glosado` e `saldo_total`. O pareto de motivos fica no escopo.
- **"Valor apresentado" não é coluna.** `ConciliacaoFinanceira` guarda `valor_unitario`, `valor_total` e `status`, sem separar apresentado/pago/glosa. Apresentado passa a ser derivado do lote como `total_pago + total_glosado`, e a spec diz isso explicitamente para ninguém procurar um campo que não existe.
- **Saúde dos componentes ganha histórico.** `saude_componentes` guarda `ultimo_heartbeat_em` e `ultimo_status` — um retrato do agora, não série temporal. Como a aba Automações precisa de "fora do ar no período", esta change **cria a tabela de histórico**, gravando cada mudança de estado. Consequência aceita: o relatório só enxerga do deploy em diante; período anterior a ele fica vazio, e a tela diz isso em vez de mostrar zero como se fosse "sempre no ar".

## Capabilities

### New Capabilities
- `relatorios-de-uso`: como a clínica consulta seus números agregados por período, com comparação, filtros, permissão por aba e exportação.

## Impact

**API**
- `app/Http/Controllers/RelatorioController.php`, `app/Http/Requests/RelatorioFiltrosRequest.php`
- `app/Services/Relatorios/` — `RelatorioFiltros`, `RelatorioPeriodo`, um service por aba, `RelatorioExportService`
- `app/Models/SaudeComponenteEvento.php` + migration do histórico, e a gravação em `SaudeService::registrarHeartbeat`
- Migration `sync_relatorios_permissions_to_existing_roles`, no padrão das `sync_*` existentes
- `database/seeders/DemoDataSeeder.php`
- Dependência nova: `openspout/openspout` (XLSX em streaming)

**Web**
- `web/src/features/relatorios/` — página, abas, filtros, KPIs, tabelas e wrappers de gráfico
- `web/src/lib/graficos.ts` — paleta lida dos tokens do design system
- `web/src/routes/navigation.ts` e `AppRoutes.tsx` — grupo novo
- Dependência nova: `recharts`

## Non-Goals

- **Snapshot pré-agregado.** Uma clínica em produção, menos de um mês de dados: agregar por query com cache de 5 min resolve. Se alguma aba passar de 1,5 s sem cache com dado real, aí entra — não antes.
- **PDF e relatório agendado por e-mail.** O segundo reaproveitaria a esteira de alertas, mas é outra entrega.
- **Relatório por paciente.** A pasta do paciente já cobre o caso individual.
- **Métrica de e-mails enviados.** Não há registro de envio: existem `EmailSmtpSetting` e `EmailTemplate`, nenhuma tabela de envio. Fora do escopo até existir o que medir.
- **Comparação entre clínicas para o super admin.** A visão "todas" soma; comparar lado a lado é v2.
- **Retroagir o histórico de saúde.** A tabela nasce vazia e só registra do deploy em diante.
