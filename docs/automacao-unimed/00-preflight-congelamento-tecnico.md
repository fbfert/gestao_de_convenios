# Etapa 0 - Preflight e congelamento técnico

Data: 2026-07-31  
Branch: `main`  
HEAD local: `31fb380c49ca69d248fec749061395bee15ed535` (`feat: aprimora fluxos operacionais e configuracoes`)  
Referência do plano: `6a30f787c6da718c4c9675f3b573497fce2d708e` (`Separar CRUD de templates de email`)

## Objetivo

Registrar o estado real do repositório antes das Automações Unimed, mapear impactos técnicos e deixar explícitas as dependências para as próximas etapas, sem alterar comportamento da aplicação.

## Specs e documentos lidos

Specs aprovadas em `openspec/specs`:

- `openspec/specs/conciliacao-analitico-unimed/spec.md`
- `openspec/specs/guia-detail/spec.md`

Documentos de arquitetura e operação:

- `openspec/config.yaml`
- `AGENTS.md`
- `docs/decisoes-arquitetura.md`
- `docs/schema.md`
- `docs/fluxo-operacional-convenio.md`
- `docs/GESCON_plano_completo_super_prompts_codex.html`
- `README.md`

Specs/deltas relevantes consultados por busca:

- `openspec/changes/configuracoes-envio-emails/specs/configuracoes-envio-emails/spec.md`
- `openspec/changes/configuracoes-ia-openai/specs/configuracoes-ia-openai/spec.md`
- `openspec/changes/solicitacoes-ler-pedido-medico/specs/solicitacoes-ler-pedido-medico/spec.md`
- `openspec/changes/solicitacao-status-buttons/specs/solicitacao-status-actions/spec.md`
- `openspec/changes/solicitacoes-guia-popup/specs`
- `openspec/changes/menu-analiticos-importacao/specs/conciliacao-analitico-unimed/spec.md`

## Estado do repositório

O repositório não está limpo. Há alterações não commitadas e arquivos não rastreados anteriores à Etapa 0, principalmente no fluxo de configurações/templates de email.

Arquivos modificados rastreados:

- `api/app/Http/Controllers/EmailSettingsController.php`
- `api/app/Http/Requests/UpdateEmailSettingsRequest.php`
- `api/routes/api.php`
- `api/tests/Feature/EmailSettingsApiTest.php`
- `openspec/changes/configuracoes-envio-emails/specs/configuracoes-envio-emails/spec.md`
- `web/src/features/configuracoes/useEmailSettings.ts`

Arquivos não rastreados de destaque:

- `api/app/Http/Controllers/EmailTemplateController.php`
- `api/app/Http/Requests/StoreEmailTemplateRequest.php`
- `api/app/Http/Resources/EmailTemplateResource.php`
- `api/database/seeders/EmailTemplateSeeder.php`
- `web/src/features/configuracoes/EmailTemplatesPage.tsx`
- `docs/GESCON_plano_completo_super_prompts_codex.html`
- arquivos "Cópia em conflito" em `api`, `web` e `openspec`

### Divergência do baseline citado no plano

O plano cita `6a30f787...` como baseline, mas no histórico local o `merge-base` entre esse commit e o HEAD atual é o próprio HEAD `31fb380...`. Ou seja, o HEAD local está atrás do commit de referência citado, enquanto parte das mudanças desse commit aparece no working tree como modificada/não rastreada.

Risco: antes de começar a Etapa 1, é recomendável decidir se o trabalho pendente de templates de email será commitado, descartado seletivamente ou integrado ao histórico local. A Etapa 0 não altera esses arquivos.

## Inventário técnico atual

### Backend

Stack observada:

- Laravel 11
- PHP `^8.2`
- Laravel Sanctum
- Spatie Laravel Permission
- Queue database como default em `api/config/queue.php`

Módulos principais:

- Autenticação: `AuthController`, Sanctum, `ResolveTenant`
- Multi-tenancy: `BelongsToTenant`, `TenantScope`, `TenantContext`
- Permissões: `PermissionCatalog`, Spatie teams por `tenant_id`
- Solicitações: `Solicitacao`, `SolicitacaoController`, `SolicitacaoService`
- Guias: `Guia`, `GuiaController`, `GuiaService`
- Convênios: `Convenio`, `ConvenioRegra`, `TabelaValor`
- Profissionais, Especialidades, Médicos e Pacientes com bindings tenant-safe
- Conciliação/analítico Unimed já possui importação e persistência de lotes
- Conectores existentes: interface, resolver e conector manual

### Frontend

Stack observada:

- React 19
- TypeScript
- Vite
- TanStack Query
- Axios
- Zustand
- Tailwind
- Playwright E2E

Rotas atuais de maior impacto:

- `/solicitacoes`
- `/solicitacoes/ler-pedido-medico`
- `/guias`
- `/guias/:id`
- `/convenios`
- `/analiticos`
- `/configuracoes`
- `/auditoria`

Arquivos React de maior impacto futuro:

- `web/src/features/solicitacoes/SolicitacoesPage.tsx`
- `web/src/features/solicitacoes/SolicitacaoGuiaModal.tsx`
- `web/src/features/solicitacoes/useSolicitacoes.ts`
- `web/src/features/solicitacoes/types.ts`
- `web/src/features/guias/GuiasPage.tsx`
- `web/src/features/guias/GuiaDetalhePage.tsx`
- `web/src/features/guias/GuiaStatusActions.tsx`
- `web/src/features/guias/useGuias.ts`
- `web/src/features/convenios/ConveniosPage.tsx`
- `web/src/routes/AppRoutes.tsx`
- `web/src/routes/ShellLayout.tsx`
- `web/src/lib/statusLabels.ts`

### Banco de dados

Migrations existentes cobrem:

- `jobs`, `job_batches`, `failed_jobs`
- `tenants`, `users`, tokens Sanctum
- domínio principal: `especialidades`, `profissionais`, `medicos`, `convenios`, `convenio_regras`, `pacientes`, `solicitacoes`, `guias`, `tabela_valores`, `antecipacoes`, `lancamentos`, `conciliacoes_financeiras`, `audit_logs`
- Spatie permissions com teams
- analítico Unimed: `analitico_unimed_lotes`, `analitico_unimed_linhas`
- configurações: SMTP, templates de email, OpenAI, prompts de IA, manuais, templates de impressão

### Filas e scheduler

- `QUEUE_CONNECTION` default: `database`
- `api/routes/console.php` agenda `VerificarGuiasDiarioJob` diariamente às `02:00`
- `VerificarGuiasDiarioJob` consulta guias `under_review`, agrupa por convênio e chama o conector resolvido
- O resolver só suporta `manual`; `api` e `scraping` lançam exceção
- A tabela atual `conector_execucoes` é simples e não cobre execução por item, idempotência, eventos, retry, status granular ou evidências

## Conflitos, impactos e riscos

### Conflitos com o plano de Automações Unimed

1. O código atual mantém relação `Solicitacao -> guia()` como `hasOne`.
   - O plano exige `1 Solicitação -> N Itens` e `1 item -> 1 Guia`.
   - Impacto: Etapa 1 deve introduzir `solicitacao_itens` e migrar para vínculo por item sem quebrar o `solicitacao_id` legado em `guias`.

2. `SolicitacaoService::alterarStatus()` cria ou atualiza uma Guia automaticamente ao aprovar solicitação.
   - O plano exige que, para Unimed automatizada, a Guia nasça do item/automação e não apenas do status `approved`.
   - Impacto: manter compatibilidade para convênios manuais/legados e desviar Unimed automatizada para fluxo manual "Enviar para Unimed" em etapas futuras.

3. `guias.numero_guia` é obrigatório e o gerador atual usa `GUIA-SOLICITACAO-{id}`.
   - O plano exige criação local somente após retorno/estado idempotente da Unimed.
   - Impacto: Etapa 1 ou 5 precisará permitir estado intermediário por item sem número real, ou separar solicitação_item de guia até o retorno.

4. `convenios.connector_type` aceita `manual|api|scraping`, mas `ConnectorResolver` só suporta `manual`.
   - O plano quer driver canônico Unimed RDA sem comparar nome textual.
   - Impacto: adicionar identificador de driver/configuração compatível com ADR-02 e ADR-03.

5. `VerificarGuiasDiarioJob` usa horário fixo `02:00`.
   - O plano exige elegibilidade por `due` e intervalo default de 24h nas automações 2/3.
   - Impacto: Etapa 7/10 deve migrar para timestamps/locks sem quebrar o job legado.

### Nenhum conflito direto encontrado com specs aprovadas

As specs aprovadas atuais cobrem:

- detalhe de guia via endpoint existente;
- importação persistente e normalização do analítico Unimed;
- conciliação por guia com distinção de profissionais;
- pré-visualização/conferência operacional.

A Etapa 0 não altera comportamento, então não contraria essas specs. Para Etapa 1 em diante, será necessário criar ou atualizar spec antes da implementação, porque a automação Unimed e o modelo multi-item ainda não têm spec aprovada em `openspec/specs`.

## Mapa de impacto por módulo

### Solicitações

Impacto alto.

- Criar modelo/tabela `solicitacao_itens`
- Manter campos legados em `solicitacoes` durante transição
- Adaptar validações de paciente, convênio, médico, profissional e especialidade por tenant
- Separar documentos gerais da solicitação e documentos por item
- Revisar API/resources/types para lista e detalhe
- Preservar leitura de pedido médico e upload tenant-safe

### Guias

Impacto alto.

- Adicionar vínculo com `solicitacao_item_id` mantendo `solicitacao_id` legado inicialmente
- Preservar endpoint `GET /api/guias/{id}` conforme spec aprovada `guia-detail`
- Impedir criação manual inconsistente para Unimed automatizada nas etapas adequadas
- Incluir execução/idempotência/status operacional sem expor segredos
- Preservar status em inglês no banco/API

### Pacientes

Impacto médio.

- Carteirinha Unimed deve ser validada/formatada por configuração do convênio, não por nome textual
- Paciente continua leve conforme ADR-05
- Cadastro rápido vindo de pedido médico precisa continuar tenant-safe

### Especialidades

Impacto médio.

- Itens passam a apontar para especialidade
- Mapeamento de procedimento Unimed por especialidade/convenio deve ser dado configurável
- Unicidade por tenant deve ser preservada

### Profissionais

Impacto médio/alto.

- Itens passam a apontar para profissional executor
- Automação precisa distinguir profissional executor, profissional informado ao plano e médico solicitante
- Permissões `viewOwn` continuam baseadas em `profissional_id`

### Configurações/Convênios

Impacto alto.

- Adicionar configuração segura do conector Unimed por tenant
- Criptografar senha e nunca retornar senha ao frontend
- Definir driver Unimed RDA canônico em metadado/configuração
- Adicionar auditoria para alterações
- Atualizar catálogo de permissões se houver permissão administrativa específica

### Filas, conectores e worker

Impacto alto.

- Criar worker Node/Playwright separado
- Criar client Laravel para worker local
- Criar execução/eventos por automação e por item
- Usar queue database e locks por tenant
- Garantir idempotência e bloqueio de retry cego pós-submit
- Não hardcodear dynaHash

## Ordem técnica proposta para migrations futuras

1. Criar estruturas de Solicitação multi-item:
   - `solicitacao_itens`
   - índices por `tenant_id`, `solicitacao_id`, status/elegibilidade
   - backfill 1 item por solicitação legada

2. Criar documentos:
   - documentos gerais da solicitação
   - documentos por item, se a modelagem final separar anexos
   - migrar `pedido_medico_*` para documento geral sem apagar colunas legadas no primeiro corte

3. Criar mapeamentos por convênio:
   - driver/configuração canônica do convênio
   - códigos de procedimento/serviço por especialidade/profissional conforme necessário

4. Adaptar `guias`:
   - `solicitacao_item_id` nullable inicialmente
   - novos campos Unimed e campos de rastreabilidade, sem remover `solicitacao_id`

5. Criar credenciais Unimed por tenant:
   - senha/token criptografado
   - status ativo/pausado
   - auditoria de alteração

6. Criar automações:
   - `automacao_execucoes`
   - `automacao_eventos`
   - campos de idempotência, operação, status, timestamps, evidências privadas

7. Adicionar scheduler/retention/health quando o worker já existir.

## Baseline de validação

Comandos executados:

- `openspec validate --all --no-interactive`
- `openspec validate dashboard-home-refresh --type change --no-interactive`
- `php artisan test`
- `npm run lint`
- `npm run build`

Resultados:

- `npm run lint`: passou.
- `openspec validate --all --no-interactive`: falhou com 16 itens válidos e 1 inválido.
- `openspec validate dashboard-home-refresh --type change --no-interactive`: falhou porque `openspec/changes/dashboard-home-refresh` contém apenas `.openspec.yaml` e não possui deltas em `specs/`.
- `php artisan test`: falhou com 2 testes falhando e 119 passando.
- `npm run build`: falhou por erros TypeScript relacionados ao estado pendente de templates de email e por arquivo "Cópia em conflito".

Falhas PHP observadas:

- `Tests\Unit\VerificarGuiasDiarioJobTest::test_job_grava_execucao_para_guias_em_under_review_por_convenio`
  - Esperava 3 execuções, encontrou 4.
- `Tests\Feature\GuiasApiTest::test_profissional_so_enxerga_guias_proprias_na_listagem`
  - Esperava 1 guia na listagem do profissional, retornou 2.

Falhas TypeScript observadas:

- `ConfiguracoesPage.tsx` ainda referencia `templates` em `EmailSettingsForm`.
- `web/src/routes/AppRoutes (Cópia em conflito de URSS4 2026-07-24).tsx` é compilado e importa `EmailTemplatesPage` sem export correspondente.

## Dependências antes da Etapa 1

1. Resolver a divergência entre HEAD local e baseline `6a30f787...`.
2. Decidir o destino das alterações pendentes de templates de email e arquivos "Cópia em conflito".
3. Criar uma mudança OpenSpec específica para Automações Unimed / Solicitação multi-item antes de implementar funcionalidade nova.
4. Corrigir ou aceitar explicitamente as falhas de baseline atuais, para que regressões futuras sejam distinguíveis.
5. Confirmar que a Etapa 0 foi homologada antes de iniciar Etapa 1.

## Checklist de homologação da Etapa 0

- Confirmar que este documento representa o estado atual do repositório.
- Confirmar decisão sobre o working tree sujo.
- Confirmar se a Etapa 1 deve começar criando uma change OpenSpec nova.
- Confirmar se as falhas de baseline serão corrigidas antes da Etapa 1 ou registradas como dívida aceita.
- Confirmar que nenhuma automação real acessará a Unimed sem credenciais e autorização explícita.

