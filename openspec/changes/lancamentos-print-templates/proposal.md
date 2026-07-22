## Why

O modelo impresso de Registro de Sessões está fixo no frontend, impedindo ajustes operacionais no layout e nos textos usados na impressão.

## What Changes

- Criar uma área de Templates em Lançamentos para editar o HTML do modelo de impressão.
- Persistir o template por tenant.
- Renderizar placeholders funcionais no HTML salvo, incluindo dados do cabeçalho e linhas de sessões.
- Usar o template salvo no botão de impressão do modelo em branco.

## Impact

- Novo endpoint autenticado para consultar e salvar o template de impressão.
- Nova tabela para templates de impressão de lançamentos.
- Nova rota web `/lancamentos/templates`.
- Substituição do modelo fixo impresso por HTML renderizado a partir do template salvo.
