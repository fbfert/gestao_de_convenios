# Runbook operacional: Automações Unimed

## Componentes

- Laravel API: fonte de verdade, banco, fila, auditoria e regras de tenant.
- Queue worker: executa jobs da fila `automacoes`.
- Worker Node local: executa automações de navegador sem acesso ao banco.
- Scheduler Laravel: enfileira despachantes leves.

## Deploy

1. Fazer backup do banco e do storage privado.
2. Aplicar migrations.
3. Configurar `UNIMED_WORKER_URL`, `UNIMED_WORKER_TOKEN` e `QUEUE_CONNECTION=database`.
4. Subir worker Node em localhost.
5. Subir `php artisan queue:work database --queue=automacoes,default`.
6. Habilitar scheduler Laravel.
7. Executar smoke tests de healthcheck e envio mockado.

## Healthcheck

Endpoint administrativo:

```http
GET /api/configuracoes/unimed/worker-health
```

Status esperado: `available`.

## Circuit breaker

Se o worker retornar `PORTAL_STRUCTURE_CHANGED`, o backend pausa a credencial Unimed:

- `ativo=false`
- `automation_paused_at` preenchido
- `automation_paused_reason=PORTAL_STRUCTURE_CHANGED`
- AuditLog `unimed_rda.automation_paused`

Reativação administrativa:

```http
POST /api/configuracoes/unimed/reativar
```

## Retenção

Dry-run:

```bash
php artisan automacao:limpar-evidencias --dry-run --days=30
```

Execução real:

```bash
php artisan automacao:limpar-evidencias --days=30
```

O comando só considera caminhos sob `automacoes/evidencias/` e preserva documentos médicos.

## Rollback

1. Pausar queue worker.
2. Parar worker Node.
3. Desativar scheduler temporariamente.
4. Restaurar backup se necessário.
5. Manter evidências e documentos privados até decisão humana.

## Smoke tests

- Healthcheck do worker retorna `available`.
- Configuração Unimed não expõe senha.
- Execução mockada cria eventos e não registra credenciais.
- Falha `PORTAL_STRUCTURE_CHANGED` pausa automação.
- Reativação administrativa gera AuditLog.
