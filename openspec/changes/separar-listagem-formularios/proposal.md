## Why

Hoje várias páginas do sistema misturam listagem e formulário na mesma tela, o que faz a lista permanecer visível enquanto o usuário cria ou edita um registro. Isso aumenta a carga visual, dificulta o fluxo em telas longas e deixa o comportamento inconsistente entre módulos.

## What Changes

- Separar a experiência de lista e formulário em telas distintas para os CRUDs afetados.
- Fazer com que ações de `Novo` e `Inserir` levem para uma rota própria de criação, sem reutilizar a mesma tela da listagem.
- Preservar a listagem como a tela principal de consulta.
- Manter o comportamento atual dos dados e permissões, alterando apenas a navegação e a organização visual.
- **BREAKING**: o formulário deixa de aparecer embutido na tela de listagem nas páginas afetadas.

## Capabilities

### New Capabilities

- `crud-lista-formulario-separados`: define o padrão de navegação e layout para CRUDs com listagem, criação e edição em telas separadas.

### Modified Capabilities

- `<existing-name>`: 

## Impact

- Frontend React: páginas de Pacientes, Solicitações, Guias, Lançamentos, Antecipações, Convênios, Profissionais, Médicos, Especialidades e Usuários.
- Rotas da aplicação web: criação de rotas dedicadas para formulário quando ainda não existirem.
- UX: redução de densidade visual na listagem e fluxo mais previsível para cadastro/edição.
- Testes e validações: ajustar testes de navegação e atualização de páginas para o novo fluxo.
