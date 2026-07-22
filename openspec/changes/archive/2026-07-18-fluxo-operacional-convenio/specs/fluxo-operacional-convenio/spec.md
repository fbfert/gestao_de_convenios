## ADDED Requirements

### Requirement: Fluxo operacional unificado de convênio
O sistema SHALL tratar a solicitação médica, a guia, as antecipações, as sessões, a conciliação e o repasse financeiro como uma jornada operacional única vinculada ao paciente e ao convênio.

#### Scenario: Solicitação inicia o fluxo operacional
- **WHEN** uma solicitação médica for cadastrada para um paciente
- **THEN** o sistema SHALL vincular essa entrada ao fluxo operacional da guia daquele paciente

#### Scenario: Guia continua a jornada
- **WHEN** a guia for criada ou atualizada a partir da solicitação
- **THEN** o sistema SHALL preservar o vínculo com o paciente, o convênio, o profissional e a especialidade para uso nas etapas seguintes

### Requirement: Status operacionais em inglês com tradução na interface
O sistema SHALL persistir os status operacionais em inglês na API e no banco de dados, e SHALL traduzir esses status para português apenas na interface.

Os status operacionais mínimos para solicitação/guia SHALL incluir:
- `registered` para Cadastrado
- `under_review` para Em análise
- `approved` para Aprovado
- `canceled` para Cancelado
- `denied` para Negado
- `expired` para Vencido

#### Scenario: Exibir status traduzido
- **WHEN** a interface receber uma solicitação ou guia com status `approved`
- **THEN** o sistema SHALL exibir o status como "Aprovado"

#### Scenario: Status técnico permanece em inglês
- **WHEN** a API retornar uma guia com status `under_review`
- **THEN** o sistema SHALL manter esse valor em inglês no payload e traduzir somente na camada de apresentação

### Requirement: Antecipação após a primeira liberação
O sistema SHALL permitir antecipações somente após a primeira autorização e o primeiro agendamento do paciente.

A primeira autorização SHALL ser registrada pela guia aprovada. Após isso, a continuidade SHALL ocorrer por antecipações que definem a quantidade de sessões liberadas por período.

#### Scenario: Bloquear antecipação antes do primeiro agendamento
- **WHEN** um operador tentar criar uma antecipação para um paciente sem primeiro agendamento realizado
- **THEN** o sistema SHALL impedir a operação

#### Scenario: Criar antecipação após a liberação inicial
- **WHEN** a primeira autorização já estiver aprovada e existir ao menos um agendamento realizado
- **THEN** o sistema SHALL permitir criar uma antecipação para continuidade do atendimento

### Requirement: Agendamento condicionado à autorização válida
O sistema SHALL permitir agendamento somente para guias com autorização válida, e SHALL bloquear agendamentos vinculados a guias que ainda não estejam aprovadas.

#### Scenario: Bloquear guia não autorizada
- **WHEN** o usuário tentar agendar um paciente em uma guia que não esteja aprovada
- **THEN** o sistema SHALL impedir o agendamento

#### Scenario: Permitir após aprovação ou antecipação válida
- **WHEN** a guia estiver aprovada ou a antecipação vinculada estiver válida
- **THEN** o sistema SHALL permitir o agendamento do atendimento

### Requirement: Alertas de continuidade e agenda
O sistema SHALL sinalizar quando um paciente não tiver mais agendamentos futuros vinculados ao fluxo operacional, para orientar a criação de antecipações e a revisão da agenda.

#### Scenario: Sem próximos agendamentos
- **WHEN** um paciente não tiver mais sessões/agendamentos futuros em uma guia ou antecipação ativa
- **THEN** o sistema SHALL exibir um alerta de continuidade para antecipações e agenda

### Requirement: Registro de sessões com impressão em branco e transcrição automática
O sistema SHALL disponibilizar CRUD de sessões com dados operacionais por linha, incluindo data, hora inicial, hora final, acompanhante e resumo das atividades.

O sistema SHALL permitir gerar uma impressão em branco do registro de sessões para posterior preenchimento manual ou automático.

O sistema SHALL permitir preencher o registro de sessões por:
- upload de foto do registro
- upload do documento digitalizado
- transcrição automática dos dados extraídos
- importação em lote de texto transcrito/OCR para múltiplas sessões

#### Scenario: Gerar registro em branco
- **WHEN** o operador solicitar a impressão do registro de sessões
- **THEN** o sistema SHALL gerar um layout em branco para preenchimento posterior

#### Scenario: Transcrever registro de sessão
- **WHEN** o operador enviar uma foto ou documento do registro preenchido
- **THEN** o sistema SHALL transcrever os dados extraídos para os campos da sessão correspondente

#### Scenario: Importar várias sessões transcritas
- **WHEN** o operador enviar um texto transcrito contendo várias linhas de sessão
- **THEN** o sistema SHALL interpretar e registrar cada linha como uma sessão individual vinculada ao contexto informado

### Requirement: Finalização do registro para envio à Unimed
O sistema SHALL permitir finalizar um conjunto de sessões antes do envio à Unimed, exigindo confirmação do operador para datas, horários e anexos necessários.

Para a regional `0220`, o sistema SHALL exigir o PDF do registro de sessões no envio. Para as demais regionais, esse anexo SHALL ser opcional.

#### Scenario: Confirmar envio com ajustes
- **WHEN** o operador revisar um registro de sessões finalizado
- **THEN** o sistema SHALL permitir confirmar ou ajustar datas e horários antes do envio

#### Scenario: Regional 0220 exige PDF
- **WHEN** a guia estiver vinculada à regional `0220`
- **THEN** o sistema SHALL exigir o PDF do registro de sessões antes de concluir o envio

### Requirement: Importação do analítico da Unimed em Excel
O sistema SHALL importar o analítico devolvido pela Unimed em formato Excel e SHALL transcrever as linhas relevantes para processamento financeiro.

#### Scenario: Importar analítico
- **WHEN** o operador enviar o arquivo Excel do analítico da Unimed
- **THEN** o sistema SHALL ler as linhas do arquivo e preparar os dados para conciliação

#### Scenario: Cada atendimento vira linha processável
- **WHEN** o Excel trouxer um atendimento pago ou glosado
- **THEN** o sistema SHALL registrar os dados necessários para identificar a guia, a quantidade de sessões e o valor pago

### Requirement: Conciliação e repasse por sessão paga
O sistema SHALL gerar a conciliação a partir do analítico importado e SHALL calcular entradas e saídas financeiras por sessão efetivamente paga pelo convênio.

O sistema SHALL calcular o repasse do profissional usando um percentual configurável por profissional, mantendo a retenção da clínica como dado configurável.

#### Scenario: Calcular repasse por sessão paga
- **WHEN** uma linha do analítico indicar sessão paga
- **THEN** o sistema SHALL calcular o valor devido ao profissional para aquela sessão

#### Scenario: Profissional do plano e profissional executor
- **WHEN** o profissional informado ao convênio for diferente do profissional que atendeu
- **THEN** o sistema SHALL registrar ambos e usar o profissional executor para o repasse correto

#### Scenario: Registrar entrada e saída financeira
- **WHEN** a conciliação for processada
- **THEN** o sistema SHALL registrar a entrada recebida da guia/antecipação e a saída prevista ou efetivada para o profissional
