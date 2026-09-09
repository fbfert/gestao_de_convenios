## Why

Hoje o que exige ação está espalhado: senha vencendo aparece numa coluna da listagem de guias, guia negada tem um banner próprio no dashboard, e falha em série da automação não aparece em lugar nenhum — só nos registros de execução, que ninguém abre de manhã.

Um lugar só para "o que precisa de mim", com regra ligável e limiar configurável, resolve os três de uma vez e abre espaço para os outros quinze casos que já foram levantados e ficaram de fora desta entrega.

## What Changes

- Criar `alertas` (o que está aberto agora) e `alerta_regras` (o que dispara, com limiar por tenant).
- Um job avaliador roda no agendador, abre alertas novos, atualiza os existentes e **resolve automaticamente** os que não satisfazem mais a condição.
- Quatro regras nesta entrega: senha vencendo, guia negada, falhas em série da automação e componente de saúde fora.
- Tela `/alertas` com filtros, e `/alertas/configuracoes` para ligar, desligar e ajustar limiares.
- Card no dashboard com os cinco alertas abertos mais recentes.
- Permissões novas: `alertas.view` e `alertas.manage`.
- O `GuiaAlertaNegacoes` é absorvido pela regra `guia.negada` e removido — **com** as ações que ele oferece hoje preservadas.

## Capabilities

### New Capabilities
- `central-de-alertas`: alertas materializados, avaliador com deduplicação e fechamento automático, telas de listagem e de configuração.

### Modified Capabilities

## Impact

- API Laravel: duas tabelas, dois models, uma interface de avaliador com quatro implementações, um job no agendador, endpoints de listagem e de configuração, duas permissões.
- Frontend React: duas telas novas, entrada própria no menu, card no dashboard, remoção do banner de guias negadas.
- Banco de dados: `alertas` e `alerta_regras`.

## Não-objetivos

Registrados aqui para não se perderem — são as regras já levantadas que ficam para depois: `guia.parada`, `guia.a_definir`, `guia.saldo_baixo`, `credencial.rda_invalida`, `automacao.uncertain_pendente`, `automacao.desligada`, `sync.pendencias_acumuladas`, `sync.falhando`, `analitico.nao_conciliado`, `conciliacao.glosa_alta`, `lancamento.fora_da_senha`, `antecipacao.aberta_vencida`, `paciente.duplicados`, `convenio.sem_valor` e `email.smtp_falhando`.

Também fora desta entrega: notificação por e-mail (é a fase seguinte) e qualquer canal que não seja a tela.
