## Why

O menu superior hoje não reflete a ordem operacional combinada com o cliente, e o fluxo de analíticos está espalhado dentro de outra área da aplicação. Também existe a necessidade de reduzir a navegação principal para deixar permissões acessíveis a partir do CRUD de Usuários, em vez de expostas como item separado.

## What Changes

- Reordenar o menu superior para seguir a sequência operacional definida pelo cliente.
- Ajustar rótulos visíveis para manter a nomenclatura acordada na interface, sem alterar as rotas internas.
- Criar uma página dedicada de Analíticos com padrão de CRUD, baseada na estrutura do `item3.3.xlsx`.
- Adicionar na tela de Analíticos o fluxo de importação de arquivos `.xlsx`, com leitura, pré-visualização e ação final de salvar ou recusar.
- Remover o item de Permissões do menu principal e disponibilizar esse acesso dentro do CRUD de Usuários.
- Manter o fluxo de conciliação já existente como base de processamento, sem alterar a regra de negócio de importação e conferência neste passo.

## Capabilities

### New Capabilities
- `shell-navigation`: organiza a navegação principal, ordem dos itens, rótulos visíveis e remoção de acessos que deixam de ser topo de menu.
- `analiticos-page`: define a nova página de Analíticos com apresentação em formato CRUD e suporte à importação e revisão de arquivos Excel do analítico.
- `usuarios-acesso`: expõe o gerenciamento de permissões como ação dentro do CRUD de Usuários.

### Modified Capabilities
- `conciliacao-analitico-unimed`: o fluxo de importação do analítico deixa de ser apenas uma seção embutida e passa a ser apresentado como parte da nova página dedicada, preservando a pré-visualização e a conferência antes do salvamento.

## Impact

Afeta o shell de navegação do frontend, a página de Usuários, a experiência de importação de analíticos e a forma como o usuário acessa o fluxo de conciliação. A implementação deverá reutilizar a importação e normalização já existentes, sem mudar o contrato do arquivo Excel neste momento.
