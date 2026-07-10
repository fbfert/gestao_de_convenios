# Decisões de Arquitetura

Registro das decisões já validadas. Não reabrir sem motivo forte — se uma
decisão precisar mudar, documentar o porquê aqui mesmo, não só no código.

---

### ADR-01 — Multi-tenancy: banco único + `tenant_id`
Banco único, isolamento por coluna `tenant_id` + Global Scope no Eloquent
(`TenantScope`). Mais simples de operar sozinho do que banco-por-tenant.
Migrar pra banco-por-tenant só se algum cliente exigir isolamento físico.

### ADR-02 — Conectores de convênio: Strategy Pattern
Cada convênio aponta pra um `connector_type` (`manual` | `api` | `scraping`).
Todos nascem `manual`. Trocar por automação é implementar uma nova classe de
conector, sem tocar no resto do sistema. Job diário roda contra a interface,
não contra a implementação.

### ADR-03 — Regras de convênio são dado, não código
Frequência de lançamento, quantidade autorizada, validade de senha e valor por
profissional ficam em tabelas configuráveis (`convenio_regras`,
`tabela_valores`), com vigência por data. Nenhuma regra de negócio de convênio
deve ser hardcoded em Service/Controller.

### ADR-04 — Rollout em duas etapas (Rota A)
Etapa 1 ataca só a esteira de convênios, convivendo com o Clínica Ágil (que
segue responsável por agenda/cadastro/estoque/CRM). Etapa 2 expande pra
substituição completa, só depois da Etapa 1 validada em produção com o
primeiro cliente. Motivo: menor risco, validação rápida, sem competir de
imediato com um produto maduro (agenda/cadastro) que já funciona.

### ADR-05 — Paciente "leve" na Etapa 1
`pacientes` carrega só o necessário pro fluxo de convênio (nome, carteirinha,
convênio, contato) + `clinica_agil_id` nullable como referência externa.
Prontuário/anamnese completos só entram na Etapa 2.

### ADR-06 — Idioma dos dados: status em inglês, UI em português
Valores de `status` padronizados em inglês no banco/API
(`under_review`, `approved`, `finalized`, `open`, `closed`, `pending`,
`reviewed`, `paid`...). Tradução acontece só na camada de apresentação, via um
mapa central de labels (`statusLabels.ts` no frontend) — evita string em
português espalhada pelo código e facilita internacionalização futura.

### ADR-07 — Cascata de valor em `tabela_valores`
Override em cascata: convênio → convênio+especialidade → convênio+especialidade+profissional
(mais específico vence), respeitando vigência por data. Lógica isolada em
`TabelaValoresService`, não misturada no `ConciliacaoService`.

### ADR-08 — Permissões: simples na Etapa 1, granular na Etapa 2
`role` enum (`admin`/`operador`) resolve a Etapa 1. Etapa 2 expande com
`permissoes` + `role_permissoes` granular por tela/ação (via
`spatie/laravel-permission`, que já cobre os dois estágios sem precisar trocar
de pacote no meio do caminho).

### ADR-09 — Job diário de verificação
Um job por convênio, agendado de madrugada, percorre guias em aberto e aciona
o conector configurado. Manual = sinaliza pendência de conferência humana.
API/scraping = consulta de fato. Ver ADR-02.

### ADR-10 — Ponto de fusão Etapa 1 → Etapa 2
`agendamentos.guia_id` (nullable, só existe na Etapa 2) é o campo que conecta
sessão agendada ao consumo de `antecipacao`, eliminando o lançamento duplicado
que existe hoje entre agenda e controle de convênio.

### ADR-11 — `User` fica fora do `TenantScope`
O login busca por e-mail antes de existir tenant resolvido na requisição
(chicken-and-egg). Por isso `User` não usa a trait `BelongsToTenant`/
`TenantScope`: é o `tenant_id` do usuário autenticado que alimenta o
`TenantContext` via `ResolveTenant` middleware, nunca o contrário. Pressupõe
e-mail único globalmente entre tenants — se e-mail duplicado entre clínicas
virar requisito, revisar (ex: login por slug da clínica + e-mail).

### ADR-12 — `ciclo_fim` é inclusivo
Em `antecipacoes`, `ciclo_inicio` e `ciclo_fim` definem uma janela inclusiva
dos dois lados. Ex: ciclo diário → `ciclo_inicio == ciclo_fim`; ciclo semanal
→ 7 dias corridos incluindo o último; ciclo mensal → do primeiro ao último
dia do mês, ambos incluídos. `AntecipacaoService::abrirCiclo()` calcula os
dois extremos a partir de `frequencia_lancamento` seguindo essa convenção.

### ADR-13 — Route-model-binding implícito não respeita `TenantScope` sozinho
O `SubstituteBindings` do Laravel roda numa prioridade de middleware que
antecede o `ResolveTenant` (mesmo com `ResolveTenant` "append"ado no grupo
`api`), então o binding implícito resolve o Model pelo `id` cru, antes do
`TenantContext` existir — o `TenantScope` global não tem o que filtrar nesse
momento. Resultado: sem tratamento explícito, um usuário do Tenant A consegue
acessar por HTTP um registro do Tenant B pelo `id`, mesmo com `TenantScope`
implementado corretamente (a falha é de ordem de execução, não do scope).

**Mitigação obrigatória**: todo Model usado como parâmetro de rota (Guia,
Antecipacao, Lancamento, ConciliacaoFinanceira, Solicitacao, e qualquer um
que vier depois) precisa de um `Route::bind()` explícito em
`AppServiceProvider`, buscando pelo `tenant_id` do usuário autenticado
**e** pelo `id`, retornando 404 se não bater — não confiar no binding
implícito puro do Eloquent nesses casos.
