## Context

Change retroativa: o comportamento descrito já está em produção. Ver `proposal.md — Why` para a motivação e para o conflito registrado com a change arquivada `2026-07-18-fluxo-operacional-convenio`. Os requisitos estão em `specs/antecipacao/spec.md`.

O que molda o desenho e não está nos requisitos:

- O modelo antigo mantinha uma tabela `antecipacoes` com `qtd_autorizada`/`qtd_utilizada`, e `lancamentos.antecipacao_id` apontava para ela. Havia 4 linhas em produção, todas `open`, nenhuma com lançamento vinculado.
- A Central de Alertas (`central-de-alertas`) já existia, com regras plugáveis e níveis configuráveis por clínica. A antecipação entra como mais uma regra, sem infraestrutura nova.
- "Adicionar sessões" (`solicitacao-adicionar-sessoes`) já sabia criar item novo encadeado na mesma solicitação por `renovacao_de_item_id`.
- `AGENTS.md`: regras de convênio são dado configurável, nunca código.

## Goals / Non-Goals

**Goals:**
- Descrever a antecipação sem depender de nenhuma tabela de cota.
- Reaproveitar a Central de Alertas e o encadeamento por renovação em vez de criar mecanismo próprio.
- Deixar a decisão de gerar sempre com uma pessoa.

**Non-Goals:**
- Reescrever ou promover a change arquivada `2026-07-18-fluxo-operacional-convenio`.
- Agendamento, conciliação e repasse — os outros trechos daquela change arquivada.
- Reintroduzir importação de antecipação por planilha.

## Decisions

**Cota deixa de ser dado; vira consulta.**
A disponibilidade passou a ser `COALESCE(sessoes_autorizadas, sessoes_solicitadas, 0)` menos a contagem de lançamentos da guia, avaliada na hora. Alternativa considerada: manter um contador materializado na guia. Descartada porque contador desincroniza — era exatamente a falha do balde antigo, em que `qtd_utilizada` e a contagem real podiam divergir sem que nada acusasse. A consulta ao vivo não tem como mentir. O custo é uma subconsulta por linha na listagem de guias disponíveis para lançamento, aceitável na ordem de grandeza atual (~2,3 mil guias por clínica).

**Data-alvo resolvida em cascata, não copiada.**
A precedência guia → convênio → global é resolvida na leitura, não gravada na guia no momento da criação. Alternativa: carimbar a data-alvo em cada guia ao criá-la. Descartada porque mudar a configuração do convênio não corrigiria as guias já existentes, e a configuração é justamente o que se espera poder ajustar. O override na guia continua existindo para o caso pontual.

**Antecipação é aviso, nunca ação.**
A regra de alerta só descreve o que encontrou; gerar exige uma ação explícita. Alternativa: gerar as guias do próximo ciclo automaticamente ao atingir a data-alvo. Descartada porque emitir guia ao convênio é ato externo e irreversível, e a data-alvo é uma estimativa — um erro de configuração viraria emissão indevida em lote.

**Renovar dentro da mesma solicitação.**
Gerar cria item novo encadeado por `renovacao_de_item_id` na solicitação de origem, reusando o caminho de "Adicionar sessões". Alternativa: abrir uma solicitação nova por ciclo. Descartada porque quebraria o histórico do paciente em N solicitações desconexas e duplicaria os dados do pedido médico, que não mudam entre ciclos.

**A fila exclui o que já tem registro, em vez de marcar a guia.**
Uma solicitação sai dos elegíveis quando existe qualquer `Antecipacao` apontando para ela — gerada ou ignorada. Isso mantém um único lugar de verdade para "já foi tratada" e faz a dispensa e a geração saírem pelo mesmo caminho. Dispensar pelo alerta é o caso à parte: marca a guia individualmente, porque ali o operador está dispensando aquela guia, não a solicitação inteira.

## Risks / Trade-offs

**A data-alvo depende de um campo que pode estar vazio** → Guia sem o campo de referência preenchido some silenciosamente da fila. Mitigação: os três campos de referência aceitos cobrem os estágios normais da guia, e a escolha é configurável por convênio, então dá para apontar para o campo que aquele convênio de fato preenche.

**Trocar a configuração global remexe a fila inteira de uma vez** → Como a data-alvo é resolvida na leitura, reduzir os dias pode fazer dezenas de guias virarem elegíveis no mesmo dia. É o comportamento pretendido, mas surpreende. Mitigação: o alerta escala por atraso, então o que estava represado aparece graduado por gravidade em vez de um bloco uniforme.

**A subconsulta de saldo cresce com o número de lançamentos** → Mitigação: hoje a listagem é paginada e o volume é pequeno; se pesar, o caminho é um índice em `lancamentos(guia_id)`, não voltar ao contador materializado.

**Excluir do histórico não desfaz a geração** → Pode dar a impressão de que desfez. Mitigação: a confirmação de exclusão diz explicitamente que remove só o registro de acompanhamento.

## Migration Plan

Já aplicado em produção, nesta ordem:

1. `lancamentos.antecipacao_id` → `lancamentos.guia_id`, com o drop na ordem que o MySQL exige (corrigido em `afbfa41`).
2. Drop de `antecipacoes`, `antecipacao_import_lotes` e `antecipacao_import_linhas` do modelo antigo.
3. Configuração de antecipação em `configuracoes_globais`, override em `convenios`, campos em `guias`.
4. Nova tabela `antecipacoes` no formato de histórico.
5. Sincronização de `antecipacoes.*` com o catálogo de papéis nas clínicas existentes (`94dcdca`).

Rollback: as migrations têm `down()`, mas o `down()` de `drop_antecipacao_tables` recria as tabelas **vazias** — as 4 linhas do modelo antigo não voltam. Como nenhuma delas tinha lançamento vinculado, nada de histórico real se perde; ainda assim, reverter é recriar estrutura, não restaurar dado.
