# Schema de Banco de Dados

> Convenção: nomes de tabela/coluna em português; valores de `status` em
> inglês no banco/API (tradução acontece só na UI, via mapa central de
> labels — ver `docs/decisoes-arquitetura.md`).

---

## Etapa 1 — MVP (módulo de convênios)

```
tenants
  id, nome, slug, cnpj, ativo, created_at, updated_at

users
  id, tenant_id, nome, email, password, role (admin|operador), ativo,
  created_at, updated_at

especialidades
  id, tenant_id, nome, ativo

profissionais
  id, tenant_id, nome, especialidade_id (FK), conselho_registro, ativo

medicos
  id, tenant_id, nome, crm, especialidade_medica, telefone, email (nullable),
  ativo

convenios
  id, tenant_id, nome, connector_type (manual|api|scraping),
  connector_config (json, nullable), ativo

convenio_regras
  id, convenio_id (FK),
  tipo_terapia (especializada|convencional|outro),
  frequencia_lancamento (diaria|semanal|mensal),
  qtd_autorizada_por_ciclo (int),
  validade_senha_dias (int, nullable),
  observacoes (text, nullable),
  vigente_desde, vigente_ate (nullable)

pacientes
  id, tenant_id, nome, cpf (nullable), carteirinha, convenio_id (FK),
  telefone, clinica_agil_id (nullable, string), ativo

solicitacoes
  id, tenant_id, paciente_id (FK), profissional_id (FK), especialidade_id (FK),
  convenio_id (FK), medico_id (FK, nullable),
  status (under_review|approved|denied),
  solicitado_em (date), observacoes (text, nullable)

guias
  id, tenant_id, solicitacao_id (FK, nullable), convenio_id (FK),
  paciente_id (FK), profissional_id (FK), especialidade_id (FK),
  numero_guia (string), tipo_terapia (especializada|convencional|outro),
  status (under_review|approved|finalized|denied|canceled|needs_verification),
  data_solicitacao, data_finalizacao (nullable),
  senha (string, nullable), validade_senha (date, nullable),
  observacoes (text, nullable),
  alerta_negacao_ocultado_em (timestamp, nullable) -- 31/08/2026: alerta de guia
    negada some de /guias e /dashboard quando preenchido; ver GuiaAlertaNegacoes.tsx

antecipacoes
  id, tenant_id, guia_id (FK), paciente_id (FK), convenio_id (FK),
  ciclo_inicio, ciclo_fim, qtd_autorizada, qtd_utilizada (default 0),
  status (open|closed)

lancamentos
  id, tenant_id, antecipacao_id (FK), profissional_id (FK),
  data_sessao (date), status (completed|missed|canceled),
  observacoes (text, nullable)

tabela_valores
  id, tenant_id, convenio_id (FK),
  especialidade_id (FK, nullable),
  profissional_id (FK, nullable),
  valor (decimal),
  vigente_desde, vigente_ate (nullable)

conciliacoes_financeiras
  id, tenant_id, guia_id (FK), profissional_id (FK),
  quantidade (int), valor_unitario (decimal, nullable), valor_total (decimal, nullable),
  referencia_analitico_convenio (string, nullable),
  status (pending|reviewed|paid), conferido_em (nullable)

conector_execucoes
  id, tenant_id, convenio_id (FK), executado_em, status (ok|error|pending_manual),
  detalhes (json, nullable)

audit_logs
  id, tenant_id, user_id (FK, nullable), acao, entidade, entidade_id,
  payload (json, nullable), created_at
```

### Fluxo de status
```
solicitacao: under_review -> approved -> (gera guia)
guia:        under_review -> approved (automação Unimed autoriza sozinha) | denied
             under_review | approved -> finalized (Finalizar, com senha+validade;
               abre ciclo de Antecipacao) — ver GuiaService::finalizar()
             -- 'approved' e 'finalized' sao estados DIFERENTES (approved ainda nao
             -- tem ciclo de Antecipacao aberto) mas so 'finalized' aparecia como
             -- "Aprovado" antes de 31/08/2026; agora approved = "Autorizado",
             -- finalized = "Aprovado" (so o rotulo mudou, ver statusLabels.ts)
antecipacao: open (qtd_utilizada < qtd_autorizada) -> closed
lancamento:  a cada sessão, incrementa qtd_utilizada da antecipação
conciliacao: pending -> reviewed -> paid
```

### Cascata de prioridade em `tabela_valores`
Ao calcular o valor de uma conciliação, buscar a linha mais específica primeiro,
sempre dentro da vigência (`vigente_desde <= data <= vigente_ate` ou
`vigente_ate` nulo):
1. `convenio_id + especialidade_id + profissional_id` (match exato)
2. `convenio_id + especialidade_id` (profissional_id nulo)
3. `convenio_id` (especialidade_id e profissional_id nulos)

Lógica isolada em `app/Services/TabelaValoresService.php`, testável separado
do `ConciliacaoService`.

---

## Etapa 2 — Completo (substituição do Clínica Ágil)

```
filiais
  id, tenant_id, nome, endereco, ativo

salas
  id, tenant_id, filial_id (FK), nome, ativo

tratamentos
  id, tenant_id, nome, especialidade_id (FK, nullable)

exercicios
  id, tenant_id, nome, categoria, instrucoes (text)

servicos
  id, tenant_id, nome, valor (decimal), ativo

mensalidades
  id, tenant_id, paciente_id (FK), valor, dia_vencimento, ativo

anamneses
  id, tenant_id, paciente_id (FK), profissional_id (FK),
  conteudo (json/text), created_at

agendamentos
  id, tenant_id, paciente_id (FK), profissional_id (FK), sala_id (FK),
  filial_id (FK), especialidade_id (FK),
  guia_id (FK, nullable) -- junção com o consumo da antecipação
  data, hora_inicio, hora_fim,
  status (confirmed|pending|missed|completed|canceled)

estoque_produtos
  id, tenant_id, nome, unidade, quantidade_atual, quantidade_minima

estoque_movimentacoes
  id, tenant_id, produto_id (FK), tipo (entrada|saida),
  quantidade, motivo, created_at

crm_leads
  id, tenant_id, nome, telefone, origem, status, observacoes

crm_interacoes
  id, tenant_id, lead_id (FK), canal (whatsapp|telefone|email),
  conteudo (text), created_at

whatsapp_mensagens
  id, tenant_id, paciente_id (FK, nullable), lead_id (FK, nullable),
  direcao (enviada|recebida), conteudo (text), status, created_at

permissoes
  id, nome, descricao

role_permissoes
  role, permissao_id (FK)

financeiro_lancamentos
  id, tenant_id, tipo (receita|despesa), categoria, valor,
  referencia (string, nullable), data, status
```

O `guia_id` nullable em `agendamentos` é o ponto de fusão: a sessão agendada
passa a consumir a `antecipacao` diretamente, eliminando o lançamento duplicado
que existe hoje entre agenda e controle de convênio.
