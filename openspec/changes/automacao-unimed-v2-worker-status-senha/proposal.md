## Why

A Etapa 2 implementou a geração real de guia, mas o fluxo Unimed ainda depende de operações mockadas ou fundidas para atualizar status e capturar senha/validade. A Etapa 3 separa essas automações para manter idempotência, elegibilidade própria e rastreabilidade operacional por guia.

## What Changes

- Implementar no worker local as operações reais `consult_status_batch` e `capture_authorization_data_batch` usando Playwright e fixtures HTML locais.
- Separar no backend Laravel os services/jobs de consulta de status e captura de senha/validade.
- Atualizar scheduler e endpoints manuais para respeitar elegibilidade própria, lock por tenant e ciclo de 24h.
- Atualizar a UI de Guias para acionar operações distintas e mostrar última consulta/erro recente.
- Documentar a máquina de estados, paginação de "Exames em aberto" e outcomes operacionais.

## Capabilities

### New Capabilities
- `automacao-unimed-worker-status-senha`: Consulta real de status e captura real de senha/validade em operações separadas do worker Unimed.

### Modified Capabilities
- `guia-detail`: Ações e dados operacionais de guia passam a distinguir consulta de status e busca de senha/validade.

## Impact

- `worker-unimed/`: novas operações, fixtures e testes Playwright sem acesso ao portal real.
- `api/app/Services/Automation`, jobs e rotas de automação Unimed: separação de operações, payloads e aplicação de resultados.
- `web/src/features/guias`: botões e estados para consulta de status e captura de autorização.
- `docs/automacao-unimed/v2-03-worker-status-senha.md`: documentação operacional da etapa.
