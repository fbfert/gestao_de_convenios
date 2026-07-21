## Context

O sistema já possui importação e pré-visualização do analítico da Unimed embutidas na área de lançamentos/sessões, mas o cliente quer essa operação representada como uma área própria na navegação principal. Em paralelo, o menu superior precisa seguir a ordem operacional definida e a gestão de permissões deve sair do topo e ficar acessível dentro do CRUD de Usuários.

O arquivo `item3.3.xlsx` na raiz do projeto é o modelo funcional para a nova página de Analíticos, servindo como referência de colunas, abas e leitura do Excel.

## Goals / Non-Goals

**Goals:**

- Reorganizar a navegação principal conforme a sequência operacional acordada.
- Expor uma página dedicada de Analíticos com importação de `.xlsx`, pré-visualização e decisão de salvar ou recusar.
- Tirar Permissões do menu superior e oferecer esse acesso dentro de Usuários.
- Reaproveitar o parser e a conciliação já existentes, evitando duplicação de regras.

**Non-Goals:**

- Não alterar o formato suportado do Excel do analítico nesta mudança.
- Não redesenhar a lógica financeira de conciliação, repasse ou glosa.
- Não revisar os demais CRUDs além dos rótulos e posições de navegação que dependem dessa reorganização.
- Não adicionar novas integrações externas.

## Decisions

- A navegação principal será tratada como fonte única da ordem operacional do sistema. Isso evita menu duplicado ou rotas “escondidas” que conflitam com a jornada do cliente.
  - Alternativa considerada: manter o menu atual e adicionar atalhos extras. Rejeitada porque não resolve a leitura operacional do sistema.

- A página de Analíticos será uma superfície própria, mas consumirá o mesmo backend de importação e normalização já existente.
  - Alternativa considerada: duplicar a UI dentro de Lançamentos. Rejeitada porque perpetua o acoplamento com a área errada.

- A gestão de permissões deixará de ser item global de navegação e ficará exposta no CRUD de Usuários, como ação contextual.
  - Alternativa considerada: manter a página de Permissões no menu e apenas adicionar um atalho em Usuários. Rejeitada porque mantém redundância na navegação principal.

- O topo continuará exibindo apenas a identidade da aplicação e o contexto do usuário autenticado; a simplificação visual já feita não precisa ser revertida por esta mudança.

## Risks / Trade-offs

- [Risco] A nova página de Analíticos pode duplicar parte da interface já existente em Lançamentos. → [Mitigação] Reaproveitar os mesmos hooks e componente de pré-visualização, extraindo blocos comuns quando necessário.
- [Risco] Mover Permissões para Usuários pode quebrar links antigos ou bookmarks. → [Mitigação] Manter rota compatível temporária, se necessário, enquanto o menu principal já aponta para a nova entrada.
- [Risco] A ordem do menu pode divergir de permissões reais do usuário. → [Mitigação] Renderizar apenas itens autorizados, mas preservando a ordem acordada entre os itens visíveis.
