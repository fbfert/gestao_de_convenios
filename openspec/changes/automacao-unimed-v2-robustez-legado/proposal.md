## Why

As operações reais da Unimed já existem, mas a robustez ainda está incompleta: o circuit breaker reconhece poucos erros estruturais, o job legado pode concorrer com o motor novo, e a visibilidade/permissão das configurações Unimed precisa estar fechada antes da homologação real.

## What Changes

- Ampliar o catálogo de erros estruturais para pausar o conector Unimed em falhas globais.
- Revisar `VerificarGuiasDiarioJob` e adaptar/remover seu impacto sobre guias Unimed RDA.
- Confirmar e corrigir o uso da permissão dedicada `configuracoes.unimed.manage` em rotas Unimed.
- Melhorar a UI administrativa para mostrar conector pausado e permitir reativação autorizada.
- Documentar decisões, rollback e observabilidade da Etapa 4.

## Capabilities

### New Capabilities
- `automacao-unimed-robustez-observabilidade`: Robustez operacional, pausa/reativação e higiene de legado do conector Unimed RDA.

### Modified Capabilities
- `guia-detail`: Guias Unimed não devem ser processadas pelo job legado incompatível com o motor novo.

## Impact

- Backend Laravel: catálogo de erros, circuit breaker, scheduler/job legado, testes de permissão e auditoria.
- Frontend React: tela de configurações Unimed com estado pausado/reativação.
- Documentação em `docs/automacao-unimed/v2-04-robustez-observabilidade.md`.
