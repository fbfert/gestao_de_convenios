# Automação Unimed v2 - Etapa 3 Status e Senha

## Escopo

Esta etapa implementa duas operações reais e separadas no worker local:

- `consult_status_batch`: consulta a situação da guia no portal.
- `capture_authorization_data_batch`: busca senha e validade em "Exames em aberto".

Os testes usam fixtures HTML locais. Não há acesso ao portal real nesta etapa.

## Consulta de Status

O fluxo faz login uma vez por lote, refaz a identificação do beneficiário com a carteirinha 4+4+6+2+1, abre "Localizar Guia", pesquisa `numero_guia` e lê a situação retornada.

Mapeamento:

- `Autorizado` ou `Em execução`: `approved`
- `Em estudo` ou `Em Análise`: `under_review`
- `Negado`: `denied`
- `Cancelado`: `canceled`

`unimed_last_checked_at` só é atualizado quando a consulta é conclusiva. Falhas individuais registram evento operacional, mas não simulam uma consulta bem-sucedida.

## Captura de Senha e Validade

A operação roda somente para guias `approved`, com `numero_guia`, e senha ou validade ausente. O worker acessa "Exames em aberto", percorre a paginação por links reais da sessão e abre a guia cujo número bate exatamente.

Ao abrir a execução, captura:

- `NR_SENHA`
- `DT_VALIDADE_SENHA`, normalizada para `YYYY-MM-DD`

Valores vazios não sobrescrevem dados existentes. Quando a guia não aparece em nenhuma página, o outcome é `NOT_FOUND_IN_OPEN_EXAMS` e a guia permanece elegível para tentativa futura.

## Backend

O Laravel continua sendo fonte de verdade para elegibilidade, credenciais, filas e persistência. O worker recebe payload em memória e não acessa banco.

Services:

- `ConsultarStatusUnimedService`
- `CapturarSenhaValidadeUnimedService`

O scheduler `EnfileirarConsultasUnimedDueJob` despacha cada operação conforme sua própria elegibilidade. O executor usa lock por tenant e operação.

## UI

O detalhe da guia exibe ações separadas:

- "Consultar Unimed" para guias com número e status não terminal.
- "Buscar senha/validade" para guias aprovadas com autorização incompleta.

A lista e o detalhe mostram última consulta e a execução Unimed recente quando disponível.

## Códigos e Outcomes

- `GUIA_NOT_FOUND`: guia não localizada na consulta de status.
- `BENEFICIARY_RESTRICTION`: restrição individual ao consultar status.
- `NOT_FOUND_IN_OPEN_EXAMS`: guia não encontrada em "Exames em aberto".
- `ITEM_STATUS_FAILED`: falha individual não estrutural ao consultar uma guia.
- `LOGIN_ERROR`, `PORTAL_UNAVAILABLE`, `WORKER_INTERNAL_FATAL`: erros estruturais continuam tratados pelo catálogo/circuit breaker existente e serão ampliados na Etapa 4.
