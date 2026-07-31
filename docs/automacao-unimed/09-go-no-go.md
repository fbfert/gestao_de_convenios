# Relatorio GO/NO-GO: Automacoes Unimed

Data: 2026-07-31

## Escopo validado

- Solicitacoes multi-item com documento Pedido Medico.
- Configuracao Unimed RDA por tenant.
- Credenciais criptografadas e nao expostas.
- Execucoes/eventos de automacao com payload redigido.
- Worker local mockado sem acesso ao banco.
- Envio manual de item para Unimed.
- Criacao de Guia local somente apos sucesso estruturado.
- Consulta de status, captura de senha/validade e due default 24h.
- Tela de Automacoes com filtros, detalhe, timeline e reprocessamento seguro.
- Circuit breaker `PORTAL_STRUCTURE_CHANGED`, healthcheck, reativacao auditada e retencao.

## Resultado

NO-GO para producao real sem homologacao assistida no portal Unimed.

GO parcial para ambiente de homologacao com worker mockado e queue controlada.

## Excecoes

- `php artisan test` completo: 148 testes passaram, 2 falharam por baseline conhecido:
  - `Tests\Unit\VerificarGuiasDiarioJobTest`: esperado 3, recebido 4.
  - `Tests\Feature\GuiasApiTest::profissional_so_enxerga_guias_proprias_na_listagem`: esperado 1, recebido 2.
- E2E Playwright:
  - `npm run test:e2e` iniciou migrations, seed e testes, mas excedeu timeout total de 240s.
  - `npx playwright test` repetido com timeout de 360s tambem excedeu timeout total antes de finalizar.

## Validacoes aprovadas

- Testes focados backend de automacao Unimed.
- Testes focados de configuracao Unimed.
- Testes focados de relatorio/reprocessamento.
- `npm run lint`.
- `npm run build`.
- `openspec validate automacao-unimed-multi-itens --type change --no-interactive`.
- `php artisan migrate:fresh --env=testing`.
- `php artisan migrate:rollback --step=10 --env=testing`.

## Roteiro assistido com portal real autorizado

1. Confirmar credencial Unimed em ambiente autorizado.
2. Executar healthcheck do worker.
3. Enviar um item elegivel para Unimed.
4. Confirmar que nao ha `dynaHash` hardcoded no worker real.
5. Confirmar Guia criada localmente apenas apos retorno estruturado ou confirmacao idempotente.
6. Simular timeout apos submit e confirmar status `uncertain`.
7. Consultar status e capturar senha/validade.
8. Simular `PORTAL_STRUCTURE_CHANGED` e validar pausa automatica.
9. Reativar automacao administrativamente e conferir AuditLog.
10. Executar dry-run de retencao de evidencias.
