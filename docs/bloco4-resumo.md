# Bloco 4 — Resumo

Este bloco fechou a lógica de negócio central do módulo de convênios antes de
qualquer endpoint HTTP ou tela. A regra que guiou tudo foi: `service` primeiro,
com teste automatizado antes de seguir para a próxima peça.

## Peças entregues

### Services

- [`api/app/Services/TabelaValoresService.php`](../api/app/Services/TabelaValoresService.php)
  - resolve a cascata do ADR-07 por vigência
  - lança `TabelaValorNaoEncontradaException` se não encontrar valor
- [`api/app/Services/AntecipacaoService.php`](../api/app/Services/AntecipacaoService.php)
  - abre ciclo com base em `convenio_regras`
  - consome cota e fecha a antecipação ao atingir o limite
- [`api/app/Services/GuiaService.php`](../api/app/Services/GuiaService.php)
  - finaliza ou nega guia com validação de status
  - calcula validade de senha quando ela não vem explícita
  - abre antecipação automaticamente ao finalizar
- [`api/app/Services/LancamentoService.php`](../api/app/Services/LancamentoService.php)
  - registra lançamento como `completed`
  - consome a cota da antecipação dentro de transaction
- [`api/app/Services/ConciliacaoService.php`](../api/app/Services/ConciliacaoService.php)
  - soma lançamentos `completed`
  - calcula `valor_unitario` e `valor_total`
- [`api/app/Services/Connectors/ConnectorResolver.php`](../api/app/Services/Connectors/ConnectorResolver.php)
  - resolve conector por `connector_type`

### Exceptions

- [`api/app/Exceptions/TabelaValorNaoEncontradaException.php`](../api/app/Exceptions/TabelaValorNaoEncontradaException.php)
- [`api/app/Exceptions/ConvenioRegraNaoEncontradaException.php`](../api/app/Exceptions/ConvenioRegraNaoEncontradaException.php)
- [`api/app/Exceptions/AntecipacaoCotaEsgotadaException.php`](../api/app/Exceptions/AntecipacaoCotaEsgotadaException.php)
- [`api/app/Exceptions/GuiaStatusInvalidoException.php`](../api/app/Exceptions/GuiaStatusInvalidoException.php)

### Connector

- [`api/app/Services/Connectors/ConnectorInterface.php`](../api/app/Services/Connectors/ConnectorInterface.php)
- [`api/app/Services/Connectors/ManualConnector.php`](../api/app/Services/Connectors/ManualConnector.php)

### Job

- [`api/app/Jobs/VerificarGuiasDiarioJob.php`](../api/app/Jobs/VerificarGuiasDiarioJob.php)
- Agendamento em [`api/routes/console.php`](../api/routes/console.php)

## Decisões registradas

- **ADR-12 - Ciclo de antecipação é inclusivo**
  - o `ciclo_fim` conta o período de forma inclusiva
  - isso ficou aplicado em `AntecipacaoService` e coberto por teste
- **Cron de produção**
  - o agendamento do job diário já está declarado no Laravel 11
  - em produção, o cron do sistema deve executar `php artisan schedule:run`
    a cada minuto
  - essa pendência operacional já está refletida no roadmap do projeto

## Resultado do bloco

- `19` testes passando
- `59` assertions
- `0` mock no teste de feature ponta a ponta

## Marco atingido

O Bloco 4 provou a esteira inteira com dado real dos seeders:

`solicitação -> guia -> antecipação -> lançamento -> conciliação`

Esse é o ponto em que o núcleo de convênios ficou validado antes da primeira
tela.
