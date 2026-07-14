## Context

Solicitações já lista pacientes, convênios, profissionais e status, mas a análise rápida da guia exige troca de contexto para a tela de Guias. O popup mantém o usuário na lista e usa os dados existentes da guia para inspeção rápida.

## Goals / Non-Goals

**Goals:**
- Abrir um modal de leitura ao clicar no paciente.
- Exibir os dados principais da guia sem navegação.
- Reaproveitar os dados da `GuiaResource` e a estrutura visual já existente no detalhe de guia.

**Non-Goals:**
- Editar ou finalizar a guia dentro do popup.
- Criar um novo fluxo de listagem de guias.
- Substituir a página de detalhe de guia.

## Decisions

- Expor a guia no `SolicitacaoResource`.
  - Alternativa considerada: buscar a guia por paciente na hora do clique.
  - Racional: o vínculo da solicitação com a guia já existe e é a origem mais estável para abrir o popup.

- Reaproveitar o formato de dados da guia já usado no detalhe.
  - Alternativa considerada: montar um DTO específico só para o popup.
  - Racional: reduz duplicação e mantém o popup consistente com a tela de detalhe.

- Implementar um modal local na tela de Solicitações.
  - Alternativa considerada: abrir a página de detalhe em nova rota.
  - Racional: a necessidade do usuário é consulta rápida dentro do contexto atual.

## Risks / Trade-offs

- [Solicitações sem guia vinculada] → mostrar estado vazio explícito em vez de quebrar a interação.
- [Modal com dados demais] → limitar o conteúdo ao resumo essencial da guia.
- [Carga extra na listagem] → carregar apenas o vínculo e buscar o detalhe sob demanda quando necessário.
