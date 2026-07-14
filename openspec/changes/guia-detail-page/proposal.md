## Why

A lista de guias não expõe de forma concentrada todos os dados já disponíveis no detalhe da API. A equipe precisa abrir uma guia, verificar prazo de senha, uso de antecipações e conciliação, e concluir ou negar a guia sem voltar à lista.

## What Changes

- Adicionar uma rota autenticada de detalhe para uma guia existente, consumindo o atual `GET /api/guias/{id}`.
- Exibir os dados operacionais da guia, seus vínculos de antecipação e conciliação, com estados visíveis de carregamento, erro e ausência.
- Reutilizar as ações de finalizar e negar nas telas de lista e detalhe, preservando uma única implementação do formulário de finalização.
- Transformar o número da guia na lista em um link para o detalhe, sem modificar seus filtros ou o destaque de validade.

## Capabilities

### New Capabilities

- `guia-detail`: Consulta e operação de uma guia individual na interface autenticada.

### Modified Capabilities

- Nenhuma.

## Impact

- API Laravel: o `GuiaResource` do endpoint de detalhe existente passará a incluir os relacionamentos já modelados.
- Frontend React: rotas, feature de guias, hooks React Query e navegação da lista.
- Não há endpoint, migração, regra de negócio ou dependência nova.
