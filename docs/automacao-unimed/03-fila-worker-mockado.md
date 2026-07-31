# Automacao Unimed: fila e worker mockado

## Variaveis

Backend Laravel:

```bash
QUEUE_CONNECTION=database
UNIMED_WORKER_URL=http://127.0.0.1:8787
UNIMED_WORKER_TOKEN=defina-um-token-local
UNIMED_WORKER_TIMEOUT=20
```

Worker Node:

```bash
UNIMED_WORKER_PORT=8787
UNIMED_WORKER_TOKEN=defina-um-token-local
```

## Execucao local

Em um terminal, suba o worker mockado:

```bash
cd worker-unimed
npm start
```

Em outro terminal, rode a fila de automacoes:

```bash
cd api
php artisan queue:work database --queue=automacoes,default
```

O worker mockado expoe:

- `GET /health`: retorna disponibilidade operacional.
- `POST /operations/{operacao}`: retorna resultado estruturado `succeeded` sem acessar banco.

## Contrato operacional

Laravel permanece como fonte de verdade e grava `automacao_execucoes` e `automacao_eventos`.
O worker recebe somente dados enviados por HTTP em memoria e nao acessa banco de dados.
Payloads persistidos pelo backend passam por redaction central para campos como senha, token, cookie e authorization.

## Locks

O job `ExecutarAutomacaoUnimedJob` usa lock por tenant para impedir sessoes concorrentes do portal.
Se o lock estiver ocupado, a execucao e finalizada como `failed` com codigo `TENANT_LOCK_UNAVAILABLE`.
