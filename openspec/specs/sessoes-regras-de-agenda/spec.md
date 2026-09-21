# sessoes-regras-de-agenda Specification

## Purpose
Impedir que o Gescon grave sessões que não poderiam ter acontecido: duas no mesmo horário, mais do que cabe num dia, ou emendadas sem o intervalo que a operadora exige. A folha assinada vai para o portal como está — o erro precisa ser pego aqui, antes de virar remessa.

## Requirements

### Requirement: Intervalo mínimo entre sessões do paciente

O sistema SHALL exigir ao menos cinquenta minutos entre os **inícios** de duas sessões do mesmo paciente, contadas pela data e pela hora de início.

O intervalo SHALL valer entre sessões da mesma guia e entre sessões de guias diferentes do mesmo paciente.

O sistema SHALL considerar apenas sessões efetivamente realizadas: sessão cancelada ou não comparecida SHALL NOT ocupar horário.

O sistema SHALL NOT aplicar a regra a sessões sem hora de início registrada, porque não há como afirmar conflito sem horário.

#### Scenario: Intervalo respeitado
- **WHEN** duas sessões do paciente começarem com cinquenta minutos ou mais de diferença
- **THEN** o sistema SHALL aceitá-las

#### Scenario: Intervalo violado na mesma guia
- **WHEN** duas sessões do mesmo lote começarem com menos de cinquenta minutos de diferença
- **THEN** o sistema SHALL recusar a gravação e SHALL apontar as duas sessões

#### Scenario: Mesma data e mesma hora
- **WHEN** duas sessões do paciente tiverem a mesma data e a mesma hora de início
- **THEN** o sistema SHALL recusar a gravação

#### Scenario: Sessão cancelada não ocupa horário
- **WHEN** a sessão que ocuparia o horário estiver cancelada ou marcada como não comparecida
- **THEN** o sistema SHALL NOT tratar o horário como ocupado

#### Scenario: Sessão sem horário
- **WHEN** uma das sessões não tiver hora de início registrada
- **THEN** o sistema SHALL NOT acusar conflito de intervalo entre elas

### Requirement: Limite diário por especialidade

O sistema SHALL limitar a oito o número de sessões do mesmo paciente, na mesma especialidade, no mesmo dia, quando a especialidade for de terapia ABA.

O sistema SHALL limitar a uma o número de sessões do mesmo paciente, na mesma especialidade, no mesmo dia, quando a especialidade não for de terapia ABA.

O sistema SHALL contar o limite **por especialidade**, não pelo total do dia: o paciente pode ter sessões de mais de uma especialidade no mesmo dia, desde que nenhuma delas comece no mesmo horário que outra e o intervalo mínimo seja respeitado.

O sistema SHALL identificar a terapia ABA pelo nome da especialidade.

#### Scenario: Oito sessões ABA no dia
- **WHEN** o paciente tiver oito sessões de uma especialidade ABA no mesmo dia, respeitando o intervalo mínimo
- **THEN** o sistema SHALL aceitá-las

#### Scenario: Nona sessão ABA no dia
- **WHEN** a gravação levar a uma nona sessão da mesma especialidade ABA no mesmo dia
- **THEN** o sistema SHALL recusar a gravação e SHALL informar o limite da especialidade

#### Scenario: Segunda sessão convencional no dia
- **WHEN** a gravação levar a uma segunda sessão da mesma especialidade não-ABA no mesmo dia
- **THEN** o sistema SHALL recusar a gravação e SHALL informar o limite da especialidade

#### Scenario: Especialidades diferentes no mesmo dia
- **WHEN** o paciente tiver sessões de duas especialidades diferentes no mesmo dia, em horários que respeitem o intervalo mínimo
- **THEN** o sistema SHALL aceitá-las, contando o limite de cada especialidade em separado

#### Scenario: Especialidades diferentes no mesmo horário
- **WHEN** duas sessões de especialidades diferentes do mesmo paciente começarem no mesmo horário
- **THEN** o sistema SHALL recusar a gravação, porque o paciente não está em dois atendimentos ao mesmo tempo

### Requirement: Conferência contra as sessões já gravadas

O sistema SHALL conferir as sessões que estão sendo gravadas contra as sessões já registradas do mesmo paciente em qualquer guia, e SHALL NOT limitar a conferência ao lote em confirmação.

O sistema SHALL nomear, no aviso de conflito, a guia e a data e hora da sessão já gravada que conflita, para que quem está com a folha em mãos saiba contra o que bateu.

#### Scenario: Conflito com sessão de outra guia
- **WHEN** uma sessão sendo gravada conflitar com sessão já registrada em outra guia do mesmo paciente
- **THEN** o sistema SHALL recusar a gravação e SHALL identificar a guia e o horário da sessão existente

#### Scenario: Paciente diferente não conflita
- **WHEN** a sessão coincidir em data e hora com sessão de outro paciente
- **THEN** o sistema SHALL NOT tratar isso como conflito do paciente

### Requirement: Conflito é bloqueio, não aviso

O sistema SHALL recusar a gravação enquanto houver conflito de intervalo ou de limite diário, e SHALL NOT oferecer caminho para gravar assim mesmo — nem com justificativa.

O sistema SHALL permitir ao operador corrigir data e hora das sessões na própria tela onde o conflito apareceu, e SHALL reconferir a cada correção.

O sistema SHALL apresentar todos os conflitos encontrados de uma vez, e SHALL NOT revelá-los um a um a cada tentativa.

#### Scenario: Correção na tela
- **WHEN** o operador corrigir a data ou a hora de uma sessão em conflito
- **THEN** o sistema SHALL reconferir e SHALL liberar a gravação assim que nenhum conflito restar

#### Scenario: Sem escape por justificativa
- **WHEN** o operador tentar confirmar com conflito em aberto
- **THEN** o sistema SHALL recusar, e SHALL NOT oferecer justificativa como forma de prosseguir

#### Scenario: Todos os conflitos de uma vez
- **WHEN** houver mais de um conflito no lote
- **THEN** o sistema SHALL apresentar todos juntos

### Requirement: Aviso de choque de agenda do profissional

O sistema SHALL avisar quando o executante da sessão já tiver, no mesmo horário, sessão registrada com outro paciente.

Esse aviso SHALL NOT impedir a gravação: quem registra a folha assinada nem sempre tem como resolver a agenda de outra pessoa, e o dado pode estar certo com o erro do outro lado.

#### Scenario: Profissional em dois pacientes no mesmo horário
- **WHEN** a sessão sendo gravada coincidir em data e hora com sessão do mesmo executante em outro paciente
- **THEN** o sistema SHALL exibir aviso nomeando o outro paciente e o horário, e SHALL permitir gravar

#### Scenario: Aviso não vira bloqueio
- **WHEN** houver apenas choque de profissional, sem conflito do paciente
- **THEN** o sistema SHALL gravar normalmente após o aviso

### Requirement: Alerta de sessões em conflito no dashboard

O sistema SHALL apresentar, no grupo de alertas de guias do dashboard, a contagem de guias cujas sessões registradas violam o intervalo mínimo ou o limite diário.

O alerta SHALL levar à lista dessas guias, e SHALL desaparecer quando não houver nenhuma.

#### Scenario: Existem guias em conflito
- **WHEN** houver guias com sessões em conflito
- **THEN** o sistema SHALL exibir o alerta com a contagem e SHALL permitir abrir a lista delas

#### Scenario: Nenhuma guia em conflito
- **WHEN** não houver guia com sessões em conflito
- **THEN** o sistema SHALL NOT exibir o alerta

### Requirement: Alcance das regras

O sistema SHALL aplicar as regras de agenda às sessões gravadas a partir da vigência desta capacidade, e SHALL NOT recusar nem alterar sessões já gravadas antes dela.

O sistema SHALL aplicar as mesmas regras qualquer que seja o caminho de entrada da sessão — importação da folha de registro, cadastro avulso ou edição de sessão existente.

#### Scenario: Sessões antigas em conflito
- **WHEN** sessões gravadas antes da vigência violarem as regras
- **THEN** o sistema SHALL mantê-las como estão, sem recusa retroativa

#### Scenario: Editar sessão antiga
- **WHEN** o operador editar a data ou a hora de uma sessão já gravada
- **THEN** o sistema SHALL aplicar as regras à edição, porque ali há decisão nova sendo tomada

#### Scenario: Cadastro avulso
- **WHEN** a sessão for cadastrada fora da importação da folha
- **THEN** o sistema SHALL aplicar as mesmas regras
