## ADDED Requirements

### Requirement: Página dedicada de Analíticos
O sistema SHALL fornecer uma página dedicada de Analíticos em formato de CRUD para apoiar a conferência operacional do analítico da Unimed.

#### Scenario: Acesso pela navegação principal
- **WHEN** o usuário clicar em Analíticos no menu superior
- **THEN** o sistema SHALL abrir a página dedicada de Analíticos

### Requirement: Estrutura baseada no modelo Excel
O sistema SHALL apresentar a página de Analíticos usando a estrutura funcional do arquivo `item3.3.xlsx`, preservando a leitura por abas e os campos relevantes do arquivo modelo.

#### Scenario: Reconhecer abas do modelo
- **WHEN** o sistema carregar a referência do modelo
- **THEN** o sistema SHALL considerar as abas de analítico e glosa como parte da estrutura suportada

### Requirement: Importação de analíticos em Excel
O sistema SHALL permitir importar arquivos `.xlsx` de analíticos e SHALL exibir uma pré-visualização antes da confirmação final.

#### Scenario: Importar arquivo Excel
- **WHEN** o operador selecionar um arquivo `.xlsx` válido
- **THEN** o sistema SHALL ler o conteúdo e montar uma pré-visualização das linhas importadas

### Requirement: Conferência antes de salvar
O sistema SHALL oferecer ações finais para salvar ou recusar a importação após a pré-visualização.

#### Scenario: Salvar importação
- **WHEN** o operador confirmar a importação após revisar a pré-visualização
- **THEN** o sistema SHALL salvar o lote importado

#### Scenario: Recusar importação
- **WHEN** o operador recusar a importação após revisar a pré-visualização
- **THEN** o sistema SHALL descartar a importação sem persistir o lote

### Requirement: Listagem dos lotes importados
O sistema SHALL exibir a lista dos lotes de analíticos já importados, permitindo ao operador ver o histórico salvo na página de Analíticos.

#### Scenario: Carregar lotes salvos
- **WHEN** o usuário abrir a página de Analíticos
- **THEN** o sistema SHALL mostrar os lotes importados mais recentes

#### Scenario: Refletir novo lote na lista
- **WHEN** um novo analítico for importado com sucesso
- **THEN** o sistema SHALL atualizar a listagem para exibir o novo lote salvo

### Requirement: Abrir lote em detalhe
O sistema SHALL permitir abrir um lote importado para visualizar seus dados, totais e linhas normalizadas.

#### Scenario: Abrir lote salvo
- **WHEN** o operador clicar para abrir um lote da lista
- **THEN** o sistema SHALL mostrar os detalhes daquele lote em uma tela própria

#### Scenario: Exibir linhas do lote
- **WHEN** o lote em detalhe for carregado
- **THEN** o sistema SHALL exibir as linhas do analítico, das glosas e da conciliação vinculadas ao lote
