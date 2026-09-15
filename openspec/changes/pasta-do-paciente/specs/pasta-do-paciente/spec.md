## ADDED Requirements

### Requirement: Pasta do paciente em tela própria
O sistema SHALL apresentar a pasta do paciente numa rota própria, endereçável por
URL, e SHALL abrir essa rota ao clicar no nome do paciente na listagem.

#### Scenario: Abrir pela listagem
- **WHEN** o usuário clicar no nome de um paciente na listagem
- **THEN** o sistema SHALL navegar para a pasta daquele paciente
- **AND** SHALL manter o botão voltar do navegador funcional para retornar à listagem

#### Scenario: Abrir por link direto
- **WHEN** o usuário acessar diretamente a URL da pasta de um paciente do seu tenant
- **THEN** o sistema SHALL apresentar a pasta daquele paciente

#### Scenario: Paciente inexistente ou de outro tenant
- **WHEN** a URL apontar para um paciente que não existe no tenant do usuário
- **THEN** o sistema SHALL informar que o paciente não foi encontrado
- **AND** SHALL NOT revelar qualquer dado do paciente

### Requirement: Conteúdo completo da pasta
O sistema SHALL reunir na pasta, além dos dados cadastrais, as solicitações, as
guias, as sessões e as antecipações do paciente, e os arquivos anexados a ele.

#### Scenario: Paciente com histórico
- **WHEN** o usuário abrir a pasta de um paciente que possui registros
- **THEN** o sistema SHALL apresentar as solicitações, guias, sessões, antecipações e arquivos daquele paciente
- **AND** SHALL NOT incluir registros de outros pacientes

#### Scenario: Sessões lançadas nas guias do paciente
- **WHEN** houver sessões lançadas em guias do paciente
- **THEN** o sistema SHALL apresentá-las na pasta, identificando a guia de cada sessão

#### Scenario: Antecipações originadas das solicitações do paciente
- **WHEN** houver antecipações geradas a partir de solicitações do paciente
- **THEN** o sistema SHALL apresentá-las na pasta

#### Scenario: Paciente sem histórico
- **WHEN** o usuário abrir a pasta de um paciente sem nenhum registro
- **THEN** o sistema SHALL apresentar os dados cadastrais
- **AND** SHALL indicar, em cada seção vazia, que não há nada registrado

### Requirement: Seções recolhidas com contagem
O sistema SHALL apresentar cada seção da pasta recolhida ao abrir a tela, exibindo
no cabeçalho a quantidade de registros, e SHALL expandi-la sob comando do usuário.

#### Scenario: Estado inicial
- **WHEN** a pasta for aberta
- **THEN** o sistema SHALL apresentar todas as seções recolhidas
- **AND** SHALL exibir no cabeçalho de cada seção a quantidade de registros que ela contém

#### Scenario: Expandir uma seção
- **WHEN** o usuário acionar o cabeçalho de uma seção recolhida
- **THEN** o sistema SHALL apresentar os registros daquela seção

#### Scenario: Reabrir a pasta
- **WHEN** o usuário sair da pasta e voltar a ela
- **THEN** o sistema SHALL apresentar as seções novamente recolhidas

### Requirement: Registro de sessões guardado na pasta
O sistema SHALL guardar, como arquivo do paciente, a folha de registro de sessões
enviada na confirmação da transcrição, e SHALL vincular esse arquivo à guia que
originou a remessa.

#### Scenario: Confirmação com folha de registro
- **WHEN** a confirmação da transcrição de sessões for enviada com a folha de registro
- **THEN** o sistema SHALL guardar o arquivo como documento do paciente da guia
- **AND** SHALL registrar no arquivo a guia que originou a remessa
- **AND** SHALL apresentá-lo na pasta daquele paciente

#### Scenario: Confirmação sem folha de registro
- **WHEN** a confirmação da transcrição for enviada sem folha de registro, nos casos em que ela não é exigida
- **THEN** o sistema SHALL concluir o lançamento
- **AND** SHALL NOT criar arquivo algum para o paciente

#### Scenario: Falha na confirmação
- **WHEN** a confirmação da transcrição falhar
- **THEN** o sistema SHALL NOT guardar a folha de registro na pasta

### Requirement: Acesso restrito à pasta
O sistema SHALL exigir a permissão de acesso a pacientes para consultar a pasta, e
SHALL restringi-la aos pacientes do tenant do usuário.

#### Scenario: Usuário sem permissão de pacientes
- **WHEN** um usuário sem permissão de acesso a pacientes requisitar a pasta
- **THEN** o sistema SHALL negar o acesso

#### Scenario: Paciente de outro tenant
- **WHEN** um usuário requisitar a pasta de um paciente de outro tenant
- **THEN** o sistema SHALL responder como se o paciente não existisse
