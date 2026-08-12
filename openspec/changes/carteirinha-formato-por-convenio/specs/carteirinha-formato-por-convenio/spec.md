## ADDED Requirements

### Requirement: Formato de carteirinha declarado no convênio
O sistema SHALL permitir que cada convênio declare o formato da sua carteirinha como uma sequência de tamanhos de bloco, e SHALL tratar o convênio sem formato declarado como texto livre.

#### Scenario: Declarar o formato
- **WHEN** o usuário informar os tamanhos dos blocos no cadastro do convênio
- **THEN** o sistema SHALL gravar o formato e passar a aplicá-lo aos pacientes daquele convênio

#### Scenario: Convênio sem formato
- **WHEN** o convênio não tiver formato declarado
- **THEN** o sistema SHALL aceitar a carteirinha como texto livre, sem validação de tamanho

#### Scenario: Independência do conector de automação
- **WHEN** um convênio tiver o conector de automação ativo mas nenhum formato declarado
- **THEN** o sistema SHALL NOT impor formato à carteirinha

#### Scenario: Formato sem automação
- **WHEN** um convênio declarar formato sem ter conector de automação
- **THEN** o sistema SHALL aplicar o formato normalmente

### Requirement: Digitação em blocos
O sistema SHALL apresentar a carteirinha em um campo por bloco quando o convênio declarar formato.

#### Scenario: Preencher a carteirinha
- **WHEN** o usuário escolher um convênio com formato declarado no cadastro de paciente
- **THEN** o sistema SHALL exibir um campo por bloco, cada um aceitando apenas dígitos e limitado ao tamanho do seu bloco

#### Scenario: Editar paciente existente
- **WHEN** o usuário abrir um paciente cuja carteirinha já está gravada
- **THEN** o sistema SHALL distribuir os dígitos gravados nos campos correspondentes

#### Scenario: Trocar de convênio no formulário
- **WHEN** o usuário trocar de um convênio sem formato para um com formato
- **THEN** o sistema SHALL aproveitar os dígitos já digitados no campo único

### Requirement: Gravação normalizada
O sistema SHALL gravar a carteirinha como uma única sequência de dígitos quando o convênio declarar formato, preservando o texto original quando não declarar.

#### Scenario: Gravar carteirinha formatada
- **WHEN** a carteirinha for enviada em blocos ou com separadores
- **THEN** o sistema SHALL gravar apenas os dígitos, sem separador

#### Scenario: Quantidade incorreta de dígitos
- **WHEN** a carteirinha não tiver exatamente o total de dígitos exigido pelo formato
- **THEN** o sistema SHALL recusar a gravação, informando o total esperado e a composição dos blocos

#### Scenario: Cadastros anteriores
- **WHEN** um convênio passar a declarar formato
- **THEN** o sistema SHALL NOT alterar nem invalidar as carteirinhas já gravadas

### Requirement: Exibição agrupada
O sistema SHALL exibir a carteirinha agrupada pelos blocos do convênio nas telas de consulta.

#### Scenario: Listagem de pacientes
- **WHEN** a carteirinha tiver exatamente o total de dígitos do formato do convênio
- **THEN** o sistema SHALL exibi-la separada em blocos

#### Scenario: Valor fora do formato
- **WHEN** a carteirinha não corresponder ao formato
- **THEN** o sistema SHALL exibi-la como está, sem tentar agrupar
