# Automacoes Unimed

Indice da documentacao entregue para a change OpenSpec `automacao-unimed-multi-itens`.

## Documentos

- `00-preflight-congelamento-tecnico.md`: baseline tecnico antes da implementacao, branch, HEAD, validacoes iniciais e falhas conhecidas.
- `03-fila-worker-mockado.md`: setup local de fila, worker mockado, variaveis e contrato operacional.
- `04-homologacao-gerar-guia.md`: roteiro para envio manual de item e geracao de Guia.
- `06-consulta-status-senha.md`: consulta de status, captura de senha/validade e scheduler due.
- `08-runbook-operacional.md`: deploy, rollback, healthcheck, circuit breaker, retencao e smoke tests.
- `09-go-no-go.md`: resultado final, excecoes, validacoes executadas e roteiro assistido com portal real.
- `v2-07-homologacao-real-execucao.md`: primeira execucao real contra o portal Unimed (24/08/2026) — causas raiz encontradas e corrigidas, guia real criada, pendencias do roteiro da Etapa 5.

## Estado final

- OpenSpec `automacao-unimed-multi-itens`: 58/58 tarefas concluidas.
- Validacao OpenSpec final: aprovada.
- Producao real: GO parcial — caso "guia aprovada diretamente" homologado ao vivo em 24/08/2026 (ver `v2-07`); demais casos do roteiro da Etapa 5 seguem pendentes.
- Homologacao com worker mockado: GO parcial com queue controlada.

## Excecoes conhecidas

- Suíte backend completa ainda possui duas falhas de baseline registradas no GO/NO-GO.
- E2E Playwright iniciou e executou parcialmente, mas excedeu timeout total nas duas tentativas.
