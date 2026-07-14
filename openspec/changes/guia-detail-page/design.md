## Context

`GET /api/guias/{guia}` já retorna uma guia isolada, dentro do escopo do tenant definido pelo ADR-13. A tela atual de listagem contém ações de finalizar, negar e gerar conciliação, mas não oferece uma visão detalhada ou uma navegação pelo número da guia.

## Goals / Non-Goals

**Goals:**

- Disponibilizar uma página autenticada de detalhe usando o contrato existente da API.
- Compartilhar o fluxo de finalizar/negar entre a lista e o detalhe.
- Manter a tradução de status centralizada e o critério de validade vencendo em sete dias já adotado pela lista.

**Non-Goals:**

- Criar ou alterar endpoints, dados do banco, permissões ou regras de convênio.
- Alterar filtros, paginação ou o destaque de prazo da lista.
- Criar fluxos de edição de guia, antecipação ou conciliação.

## Decisions

- O `GuiaResource` do endpoint existente incluirá, no detalhe, os dados de referência e as relações de antecipações e conciliações já modeladas. Alternativa: criar endpoint adicional; descartada porque o recurso existente é a representação canônica da guia.
- Criar `useGuia(id)` com a mesma chave raiz `['guias']` dos hooks atuais, permitindo que as mutations existentes invalidem lista e detalhe. Alternativa: buscar a guia somente no cache da lista; descartada pois paginação e filtros não asseguram que o registro esteja disponível.
- Extrair as ações e o formulário de finalização/negação em um componente de UI compartilhado. Alternativa: duplicar o formulário no detalhe; descartada para não criar dois fluxos de mutation e tratamento de erro.
- A página de detalhe usará os recursos retornados pela API para antecipações e conciliação. Links apontarão para as páginas existentes de domínio; a leitura não exigirá novas consultas.
- O prazo de senha usará o mesmo limite de sete dias da lista. Alternativa: criar configuração local diferente; descartada por introduzir comportamento inconsistente.

## Risks / Trade-offs

- [O detalhe pode não trazer relações opcionais] → a interface mostra seções condicionais e não assume que uma antecipação ou conciliação exista.
- [Um ID inválido ou de outro tenant retorna 404] → a página apresenta um estado de erro explícito, sem renderizar uma tela vazia.
- [Mutations atualizam dados que também estão na lista] → as mutations invalidam a chave `guias`, provocando atualização do detalhe ativo sem recarregar o navegador.
