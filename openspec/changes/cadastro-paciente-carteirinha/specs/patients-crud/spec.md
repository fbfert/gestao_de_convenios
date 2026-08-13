## ADDED Requirements

### Requirement: Convênio é a primeira escolha do cadastro
O sistema SHALL pedir o convênio antes dos demais dados do paciente e SHALL NOT pré-selecionar convênio algum.

#### Scenario: Abrir o cadastro de paciente
- **WHEN** um usuário abrir o formulário de novo paciente
- **THEN** o sistema SHALL apresentar o convênio como primeira escolha, sem valor pré-selecionado

#### Scenario: Formato da carteirinha segue o convênio
- **WHEN** o usuário escolher o convênio
- **THEN** o sistema SHALL aplicar ao campo de carteirinha o formato de blocos daquele convênio

### Requirement: CPF opcional, mascarado e conferido
O sistema SHALL aceitar CPF apenas em dígitos, exibi-lo no formato `000.000.000-00`, gravá-lo sem formatação e recusar CPF cujos dígitos verificadores não confiram. O campo SHALL permanecer opcional.

#### Scenario: Digitar CPF
- **WHEN** o usuário digitar o CPF
- **THEN** o sistema SHALL descartar qualquer caractere que não seja dígito e SHALL exibir a máscara conforme a digitação

#### Scenario: CPF inválido
- **WHEN** o CPF informado não passar na conferência dos dígitos verificadores
- **THEN** o sistema SHALL recusar a gravação explicando o motivo

#### Scenario: CPF em branco
- **WHEN** o cadastro for gravado sem CPF
- **THEN** o sistema SHALL aceitar normalmente

#### Scenario: Armazenamento sem formatação
- **WHEN** o CPF for gravado
- **THEN** o sistema SHALL persistir apenas os dígitos

### Requirement: Vários telefones por paciente
O sistema SHALL permitir cadastrar mais de um telefone por paciente, cada um com número, rótulo e nome de quem atende, e SHALL identificar qual é o principal.

#### Scenario: Acrescentar telefone
- **WHEN** o usuário acrescentar um telefone ao paciente
- **THEN** o sistema SHALL gravá-lo junto dos demais, preservando a ordem informada

#### Scenario: Um único principal
- **WHEN** o usuário marcar um telefone como principal
- **THEN** o sistema SHALL desmarcar o principal anterior

#### Scenario: Paciente sem telefone
- **WHEN** o cadastro for gravado sem nenhum telefone
- **THEN** o sistema SHALL aceitar normalmente

### Requirement: Validade da carteirinha e data de nascimento
O sistema SHALL registrar a validade da carteirinha e a data de nascimento do paciente, ambas opcionais.

#### Scenario: Gravar as datas
- **WHEN** o usuário informar validade da carteirinha ou data de nascimento
- **THEN** o sistema SHALL gravá-las no cadastro do paciente

### Requirement: Identificador externo fora do formulário
O sistema SHALL NOT exibir o identificador do Clínica Ágil no formulário de paciente, e SHALL preservar o valor já gravado.

#### Scenario: Editar paciente com identificador externo
- **WHEN** um usuário editar um paciente que tem identificador do Clínica Ágil
- **THEN** o sistema SHALL manter o valor existente, sem exibi-lo nem apagá-lo
