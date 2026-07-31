# Automacao Unimed: consulta de status e captura de senha

## Script 2: consulta de status

Operacao backend: `consultar_status`.

Entrada minima enviada ao worker:

- `guia_id`
- `numero_guia`
- dados basicos do paciente
- credencial Unimed somente em memoria

Saida esperada:

```json
{
  "status": "succeeded",
  "portal_status": "pending"
}
```

Quando a senha ainda nao estiver disponivel, o backend registra evento `dados_indisponiveis` e agenda nova elegibilidade com default de 24 horas.

## Script 3: captura de senha e validade

O mesmo endpoint operacional `consultar_status` pode retornar senha e validade:

```json
{
  "status": "succeeded",
  "portal_status": "approved",
  "senha": "SENHA-123",
  "validade_senha": "2026-08-30"
}
```

Quando ambos os campos vierem preenchidos, o backend finaliza a Guia local pelo fluxo existente de Guias, preservando regras de negocio de senha e validade.

## Scheduler

O job `EnfileirarConsultasUnimedDueJob` roda como despachante leve a cada 30 minutos.
Ele nao abre navegador diretamente; apenas localiza Guias Unimed RDA com `unimed_next_check_at` vencido ou vazio e enfileira `ExecutarAutomacaoUnimedJob`.

## Concorrencia

- Enqueue manual bloqueia consulta se ja houver `consultar_status` `queued` ou `running` para a mesma Guia.
- Executor usa lock por tenant antes de chamar o worker local.
- Due scheduler usa lock curto por tenant para evitar enfileiramento duplicado em lotes concorrentes.
