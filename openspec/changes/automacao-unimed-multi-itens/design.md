## Context

O GESCON já possui o fluxo base de convênios com Solicitações, Guias, Antecipações, Lançamentos, Conciliação e importação do analítico Unimed. A arquitetura aprovada define banco único multi-tenant por `tenant_id`, status em inglês no banco/API, permissões via Spatie teams, route bindings explícitos por tenant e regras de convênio como dados configuráveis.

O estado atual ainda modela Solicitação como uma unidade que aponta para paciente, profissional e especialidade, com relação `hasOne` para Guia. Ao aprovar uma Solicitação, o serviço cria uma Guia local automaticamente. O plano de Automações Unimed exige uma base diferente: uma Solicitação pode conter múltiplos itens e cada item pode gerar uma Guia própria via operação manual "Enviar para Unimed", com automação idempotente, fila database e worker Node/Playwright local.

## Goals / Non-Goals

**Goals:**

- Evoluir Solicitações para múltiplos itens sem quebrar dados e telas legadas no primeiro corte.
- Manter isolamento por tenant em todos os novos registros, validações, bindings e permissões.
- Configurar Unimed RDA por tenant e convênio sem comparação por nome textual.
- Manter credenciais Unimed criptografadas em repouso e fora de responses, logs, screenshots e fixtures.
- Criar uma camada de automação rastreável e idempotente, com execuções/eventos e fila database.
- Separar Laravel como fonte de verdade e worker Node/Playwright como executor local sem acesso direto ao banco.
- Bloquear retry cego após ação que possa ter gerado Guia.
- Preservar as specs aprovadas de detalhe de Guia e conciliação/analítico Unimed.

**Non-Goals:**

- Remover colunas legadas de Solicitações ou Guias durante a fundação.
- Chamar Unimed real em testes automatizados.
- Automatizar envio de Guia assim que a Solicitação for aprovada.
- Expor credenciais, storage state, screenshots ou HTML dumps publicamente.
- Reabrir decisões de multi-tenancy, permissões, idioma de status ou regras configuráveis.

## Decisions

### Solicitação multi-item por tabela dedicada

Criar `solicitacao_itens` com `tenant_id`, `solicitacao_id`, `especialidade_id`, `profissional_id`, quantidade, status operacional e campos auxiliares. Cada Solicitação legada recebe um item de backfill com os dados atuais.

Alternativa considerada: duplicar Solicitações para cada especialidade/profissional. Rejeitada porque perde a unidade operacional do pedido médico e torna documentos gerais, CID e revisão humana mais difíceis de manter.

### Compatibilidade gradual com campos legados

Manter `profissional_id`, `especialidade_id` e `solicitacao_id` em `guias` inicialmente. Adicionar `solicitacao_item_id` nullable em Guias e adaptar resources para expor dados novos quando existirem.

Alternativa considerada: migração destrutiva direta para remover campos antigos. Rejeitada por risco alto para Guias, Antecipações, Conciliação e telas existentes.

### Documentos como registros privados vinculados ao tenant

Criar documentos gerais da Solicitação e documentos por item, armazenados no disco privado atual. Pedido Médico será documento geral obrigatório para o fluxo Unimed; Laudo, Plano e Relatório serão opcionais conforme decisão operacional.

Alternativa considerada: continuar usando apenas colunas `pedido_medico_*`. Rejeitada porque não cobre documentos por item nem múltiplos anexos com rastreabilidade.

### Driver Unimed RDA configurado no domínio

Usar metadado/configuração canônica no Convênio ou tabela relacionada para identificar driver Unimed RDA, sem depender de nome. A configuração de credenciais será por tenant e administrativa.

Alternativa considerada: detectar Unimed por `convenios.nome`. Rejeitada por fragilidade e por contrariar o plano.

### Execuções e eventos substituem `conector_execucoes` para automação nova

Criar `automacao_execucoes` e `automacao_eventos` com operação, status, idempotency key, item/guia, timestamps, payload técnico redigido e evidências privadas. `conector_execucoes` permanece para fluxo legado até migração posterior.

Alternativa considerada: ampliar `conector_execucoes`. Rejeitada porque o modelo atual é simples demais e não expressa item, operação, retry, parent execution, evidências, idempotência ou estados `uncertain`/`needs_attention`.

### Worker Node/Playwright local e sem persistência de credenciais

Laravel descriptografa credenciais apenas durante a execução, chama worker em localhost com token de serviço e envia dados em memória. Worker devolve resultado estruturado e não acessa banco.

Alternativa considerada: rodar Playwright dentro de jobs PHP. Rejeitada por separar pior as responsabilidades, dificultar deploy e misturar automação de navegador com domínio Laravel.

### Idempotência obrigatória no envio de Guia

Depois de acionar "Finalizar e Gerar guia" no portal, timeout ou resposta ambígua vira `uncertain`/`resultado_incerto`. O sistema deve consultar/confirmar existência antes de permitir novo envio.

Alternativa considerada: retry automático no job. Rejeitada por risco de duplicidade de Guias.

### Scheduler por elegibilidade due

Scripts 2 e 3 usam timestamps de próxima elegibilidade e intervalo configurável default de 24h. Scheduler despacha jobs leves e não abre navegador diretamente.

Alternativa considerada: horário fixo único. Rejeitada porque o plano não define horário absoluto e múltiplos tenants precisam de locks e controle por item/guia.

## Risks / Trade-offs

- [Working tree sujo antes da mudança] -> Não misturar implementação Unimed com alterações pendentes de templates de email; resolver baseline antes de implementar Etapa 1.
- [Modelo híbrido temporário aumenta complexidade] -> Manter compatibilidade por pouco tempo, com backfill testado e documentação de campos legados.
- [Falha de tenant em novos recursos] -> Exigir `tenant_id` em todas as tabelas novas, bindings explícitos para novos route params e testes cross-tenant.
- [Credencial vazando por logs/evidências] -> Centralizar redaction e nunca retornar senha/API token em resources.
- [Duplicidade de Guia na Unimed] -> Bloquear retry cego pós-submit e exigir confirmação idempotente.
- [Portal Unimed muda estrutura] -> Classificar como falha estrutural, pausar conector automático e exigir reativação administrativa auditada.
- [Worker indisponível] -> Healthcheck, status em tela e falha operacional sem perder histórico da execução.

## Migration Plan

1. Criar fundação de dados: itens, documentos, vínculo de Guia com item e backfill não destrutivo.
2. Adaptar Solicitações e Guias para ler/escrever o modelo novo mantendo APIs legadas compatíveis.
3. Criar configurações Unimed e permissões administrativas.
4. Criar infraestrutura de automação, worker local mockado, execuções e eventos.
5. Implementar envio manual para Unimed e criação idempotente de Guia.
6. Revisar telas de Guias e estados operacionais.
7. Implementar consulta de status e captura de senha/validade.
8. Implementar relatórios, reprocessamento, circuit breaker, scheduler due e observabilidade.
9. Executar homologação assistida, documentação de deploy/rollback e relatório final.

Rollback preferencial: roll-forward para schema com dados. Antes de produção, pausar automações, parar worker Node e queue worker, fazer backup e só então migrar. Não remover dados médicos ou colunas legadas sem backup e decisão explícita.

## Open Questions

- Qual permissão final deve controlar exclusivamente a configuração Unimed: reutilizar `configuracoes.manage` ou criar `unimed.manage`?
- Quais campos do portal Unimed exigem mapeamento configurável já na primeira operação real?
- O CID será obrigatório para todos os itens Unimed já na Etapa 1 ou somente antes de enviar para Unimed?
- Onde ficará o limite de retenção de evidências técnicas em produção?
