## Purpose

Antecipação é a renovação do ciclo de atendimento antes que ele acabe: o sistema avisa quando uma guia já autorizada chegou na data de pedir a próxima, e o operador decide gerar as guias do próximo ciclo ou dispensar o aviso. Antecipar nunca dispara nada sozinho — só prepara o que uma pessoa revisa e envia.

## ADDED Requirements

### Requirement: Data-alvo da antecipação calculada por configuração

O sistema SHALL calcular a data-alvo de antecipação de uma guia a partir de uma quantidade de dias somada a um campo de referência da própria guia, e SHALL resolver essas duas configurações pela precedência: valor gravado na guia, senão o do convênio, senão o padrão global da clínica.

O campo de referência SHALL ser um entre `validade_senha`, `data_finalizacao` e `data_solicitacao`. A quantidade de dias e o campo de referência SHALL ser dado configurável, nunca fixado em código.

#### Scenario: Padrão global da clínica
- **WHEN** uma guia não tiver data-alvo própria e o convênio dela não tiver configuração de antecipação
- **THEN** o sistema SHALL calcular a data-alvo usando os dias e o campo de referência configurados globalmente para a clínica

#### Scenario: Convênio sobrepõe o padrão global
- **WHEN** o convênio da guia tiver a própria configuração de antecipação
- **THEN** o sistema SHALL usar a configuração do convênio no lugar da global

#### Scenario: Data-alvo gravada na guia vence tudo
- **WHEN** a guia tiver uma data-alvo de antecipação gravada
- **THEN** o sistema SHALL usar essa data, ignorando a configuração do convênio e a global

#### Scenario: Campo de referência ainda vazio
- **WHEN** o campo de referência escolhido ainda não estiver preenchido na guia
- **THEN** o sistema SHALL tratar a guia como sem data-alvo e SHALL NOT considerá-la elegível

### Requirement: Elegibilidade da guia para antecipação

O sistema SHALL considerar elegível para antecipação a guia que esteja aprovada ou finalizada, que não seja histórica, que não tenha o alerta dispensado, e cuja data-alvo já tenha sido alcançada.

#### Scenario: Guia alcançou a data-alvo
- **WHEN** a data de hoje for igual ou posterior à data-alvo de uma guia aprovada ou finalizada
- **THEN** o sistema SHALL considerar essa guia elegível para antecipação

#### Scenario: Guia ainda não alcançou a data-alvo
- **WHEN** a data de hoje for anterior à data-alvo da guia
- **THEN** o sistema SHALL NOT considerar a guia elegível

#### Scenario: Guia em status que não permite antecipar
- **WHEN** a guia não estiver aprovada nem finalizada
- **THEN** o sistema SHALL NOT considerá-la elegível, independentemente da data-alvo

#### Scenario: Guia com alerta dispensado
- **WHEN** o alerta de antecipação da guia tiver sido dispensado
- **THEN** o sistema SHALL NOT considerá-la elegível enquanto a dispensa valer

#### Scenario: Isolamento entre clínicas
- **WHEN** a fila de elegíveis for consultada
- **THEN** o sistema SHALL retornar somente guias da clínica do usuário autenticado

### Requirement: Alerta de antecipação devida

O sistema SHALL manter uma regra de alerta que avise sobre guias elegíveis para antecipação, e esse alerta SHALL NOT gerar itens, guias ou solicitações por conta própria.

O alerta SHALL subir de nível quando o atraso sobre a data-alvo ultrapassar um limiar configurável, e SHALL oferecer as ações de dispensar a guia e de abrir a geração da antecipação.

#### Scenario: Guia elegível vira alerta
- **WHEN** uma guia se tornar elegível para antecipação
- **THEN** o sistema SHALL abrir um alerta identificando o paciente, o convênio e a data prevista

#### Scenario: Atraso escala o nível
- **WHEN** o atraso sobre a data-alvo atingir o limiar configurado
- **THEN** o sistema SHALL elevar o alerta ao nível mais grave

#### Scenario: Alerta não age sozinho
- **WHEN** o alerta for aberto
- **THEN** o sistema SHALL NOT criar nenhum item, guia ou solicitação sem uma ação explícita do operador

#### Scenario: Dispensar pelo alerta
- **WHEN** o operador dispensar o alerta de antecipação de uma guia
- **THEN** o sistema SHALL parar de listar essa guia como elegível e SHALL fechar o alerta dela

### Requirement: Fila de elegíveis agrupada por solicitação

O sistema SHALL apresentar as guias elegíveis agrupadas pela solicitação de origem, exibindo em cada entrada o paciente, o convênio, a data prevista e os itens envolvidos, e SHALL omitir da fila as solicitações que já tenham um registro de antecipação.

#### Scenario: Várias guias da mesma solicitação
- **WHEN** mais de uma guia elegível pertencer à mesma solicitação
- **THEN** o sistema SHALL apresentar uma única entrada na fila, reunindo os itens dessas guias

#### Scenario: Data prevista da entrada
- **WHEN** as guias agrupadas tiverem datas-alvo diferentes
- **THEN** o sistema SHALL exibir na entrada a mais antiga entre elas

#### Scenario: Solicitação já tratada sai da fila
- **WHEN** uma solicitação já tiver antecipação gerada ou dispensada
- **THEN** o sistema SHALL NOT exibi-la novamente entre os elegíveis

### Requirement: Gerar a antecipação por renovação na mesma solicitação

O sistema SHALL permitir gerar a antecipação escolhendo quais pares de especialidade e profissional renovar, e para cada par escolhido SHALL criar um item novo na **mesma** solicitação de origem, encadeado ao item anterior como renovação — nunca uma solicitação nova.

O sistema SHALL registrar o que foi gerado como histórico, incluindo os itens criados e as guias correspondentes quando houver.

#### Scenario: Gerar para os itens escolhidos
- **WHEN** o operador confirmar a geração para um conjunto de pares especialidade/profissional
- **THEN** o sistema SHALL criar um item de renovação por par escolhido, na solicitação de origem, e SHALL registrar a antecipação como gerada

#### Scenario: Item escolhido não pertence à solicitação
- **WHEN** um par especialidade/profissional escolhido não corresponder a nenhum item da solicitação de origem
- **THEN** o sistema SHALL recusar a operação com erro de validação e SHALL NOT criar item algum

#### Scenario: Autoria e momento registrados
- **WHEN** uma antecipação for gerada
- **THEN** o sistema SHALL registrar quem gerou e quando

### Requirement: Dispensar uma solicitação elegível

O sistema SHALL permitir dispensar uma solicitação elegível sem gerar nada, registrando-a no histórico como ignorada.

#### Scenario: Dispensar sem gerar
- **WHEN** o operador dispensar uma entrada da fila de elegíveis
- **THEN** o sistema SHALL registrar a antecipação como ignorada e SHALL NOT criar nenhum item ou guia

### Requirement: Histórico de antecipações

O sistema SHALL manter o histórico das antecipações geradas e ignoradas, permitindo filtrar por status, e SHALL permitir excluir um registro do histórico sem afetar os itens e guias já gerados.

#### Scenario: Filtrar por status
- **WHEN** o operador filtrar o histórico por gerada ou ignorada
- **THEN** o sistema SHALL listar somente os registros daquele status

#### Scenario: Excluir registro não desfaz a geração
- **WHEN** o operador excluir um registro de antecipação que havia gerado itens
- **THEN** o sistema SHALL remover apenas o registro de acompanhamento, preservando os itens e guias criados

### Requirement: Permissões separadas para ver e operar

O sistema SHALL exigir permissão de visualização para consultar a fila de elegíveis e o histórico, e permissão de gestão para gerar, dispensar, alterar ou excluir antecipações.

#### Scenario: Sem permissão de visualização
- **WHEN** um usuário sem a permissão de visualizar antecipações consultar a fila ou o histórico
- **THEN** o sistema SHALL negar o acesso

#### Scenario: Somente visualização
- **WHEN** um usuário tiver apenas a permissão de visualizar
- **THEN** o sistema SHALL exibir a fila e o histórico, e SHALL negar gerar, dispensar, alterar e excluir

### Requirement: Disponibilidade de sessões contada ao vivo na guia

O sistema SHALL determinar quantas sessões ainda cabem em uma guia comparando a quantidade autorizada — ou, na falta dela, a solicitada — com a quantidade de sessões já lançadas naquela guia, calculada no momento da consulta, sem manter cota persistida.

O sistema SHALL oferecer para lançamento de sessão somente as guias em que essa conta ainda deixe saldo.

#### Scenario: Guia com saldo aparece para lançamento
- **WHEN** a quantidade autorizada de uma guia aprovada ou finalizada superar o total de sessões já lançadas nela
- **THEN** o sistema SHALL oferecer essa guia na busca de guia para lançamento

#### Scenario: Guia sem saldo não aparece
- **WHEN** o total de sessões lançadas alcançar a quantidade autorizada da guia
- **THEN** o sistema SHALL NOT oferecer essa guia para novos lançamentos

#### Scenario: Guia sem quantidade informada
- **WHEN** a guia não tiver quantidade autorizada nem solicitada
- **THEN** o sistema SHALL tratar o saldo como zero e SHALL NOT oferecê-la para lançamento

#### Scenario: Saldo acompanha o lançamento
- **WHEN** uma sessão for lançada contra uma guia
- **THEN** o sistema SHALL refletir imediatamente o novo total de lançadas e o saldo restante ao exibir a guia
