## Why

O bloco de Guias do "Resumo por área" mostra dois números soltos e leva para `/guias` cru — o operador chega na listagem inteira e precisa filtrar de novo para achar aquilo que o número prometia. E os números não dizem o que exige ação: "14 em análise" e "30 aprovadas" não distinguem o que chegou hoje do que está parado há três semanas.

Com o histórico de status da fase anterior, dá para responder o que o operador realmente pergunta de manhã: **o que apareceu desde ontem e o que ainda não resolvi.**

## What Changes

- O bloco de Guias vira um card com três linhas, cada uma clicável e levando à listagem **já filtrada**.
- O número grande de cada linha é o que ainda exige ação, não o total.
- "Hoje" e "na semana" são contados pela data da transição de status, nunca por `created_at` da guia.
- "Senha vencendo" passa a consumir `configuracoes_globais.senha_alerta_dias` — configuração que existe desde sempre e que nenhum código lia.
- Filtros novos no `GuiaController`: `pendente` (com `status=denied`) e `senha_vencendo`.

## Capabilities

### New Capabilities
- `dashboard-cards-por-linha`: card denso de Guias, com linhas clicáveis que abrem a listagem filtrada.

### Modified Capabilities

## Impact

- API Laravel: uma seção nova na resposta de `GET /dashboard`, calculada em duas consultas agregadas; dois filtros novos em `GET /guias`.
- Frontend React: o bloco de Guias sai da grade genérica e vira card próprio.
- Banco de dados: nenhuma alteração. Usa `guias.negada_em` e `guia_status_historico`, criados na fase anterior.

## Correção a uma premissa do plano

O plano pedia parar de enviar o bloco `usuarios` do `GET /dashboard`, sob a premissa de que só o `DashboardPage` o consumia e já o descartava. **A verificação mostrou o contrário:** `web/src/routes/navigation.ts` declara `metricKey: 'usuarios'`, e as telas de grupo (Cadastros) montam os cartões a partir dessa lista, mostrando o número de cada item. Remover o bloco apagaria silenciosamente esse número. O bloco permanece.

## Não-objetivos

- Aplicar o mesmo padrão a Conciliação e Solicitações. O padrão se prova em Guias primeiro.
- Remover o `GuiaAlertaNegacoes`. Ele é absorvido pela central de alertas; tirá-lo agora custaria ao operador as ações de "ocultar" e "nova solicitação" sem substituto pronto.
