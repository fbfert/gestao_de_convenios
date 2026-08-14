## ADDED Requirements

### Requirement: Ordenação pelo cabeçalho da tabela
O sistema SHALL permitir ordenar uma listagem clicando no cabeçalho da coluna, SHALL inverter o sentido ao clicar de novo na mesma coluna e SHALL indicar visualmente qual coluna ordena e em que sentido.

#### Scenario: Ordenar por uma coluna
- **WHEN** o usuário clicar no cabeçalho de uma coluna ordenável
- **THEN** o sistema SHALL reordenar a listagem por aquela coluna, em ordem crescente

#### Scenario: Inverter o sentido
- **WHEN** o usuário clicar de novo no cabeçalho já ativo
- **THEN** o sistema SHALL inverter o sentido da ordenação

#### Scenario: Coluna sem ordenação
- **WHEN** uma coluna não puder ser ordenada pela API
- **THEN** o sistema SHALL exibir o cabeçalho sem indicação de ordenação

### Requirement: Ordenação resolvida no servidor
O sistema SHALL ordenar a listagem inteira no servidor, e não apenas os registros já carregados, e SHALL manter a ordem estável entre páginas.

#### Scenario: Listagem paginada
- **WHEN** o usuário ordenar uma listagem paginada
- **THEN** o sistema SHALL aplicar a ordenação sobre todos os registros, não só sobre a página exibida

#### Scenario: Empate na coluna escolhida
- **WHEN** vários registros empatarem na coluna escolhida
- **THEN** o sistema SHALL desempatar por uma coluna estável, para que páginas seguidas não repitam nem pulem registros

### Requirement: Coluna de ordenação restrita a uma lista conhecida
O sistema SHALL aceitar como critério de ordenação apenas colunas previstas para aquela listagem, e SHALL usar a ordenação padrão para qualquer outro valor recebido.

#### Scenario: Coluna desconhecida
- **WHEN** a listagem for pedida com uma coluna fora da lista prevista
- **THEN** o sistema SHALL devolver a listagem na ordenação padrão

#### Scenario: Valor malicioso
- **WHEN** o valor recebido como coluna contiver trecho de SQL
- **THEN** o sistema SHALL ignorá-lo e SHALL NOT incorporá-lo à consulta

### Requirement: Ordenação por dado de relação
O sistema SHALL ordenar colunas que representam outro cadastro pelo nome exibido, e não pelo identificador.

#### Scenario: Ordenar por paciente
- **WHEN** o usuário ordenar uma listagem pela coluna de paciente
- **THEN** o sistema SHALL ordenar pelo nome do paciente
