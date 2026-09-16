## MODIFIED Requirements

### Requirement: Escolha da antecipação e do executante

O sistema SHALL identificar a antecipação pelo paciente, pela especialidade e pelo saldo do ciclo, e SHALL oferecer como executante apenas quem atende a especialidade daquela antecipação.

O sistema SHALL usar o número da guia lido no registro para escolher a guia: resolvendo para uma única guia disponível para lançamento **cujo paciente não contradiga o da folha**, SHALL selecioná-la e SHALL indicar que a escolha veio da leitura; não resolvendo, ou contradizendo, SHALL abrir a busca de guia já preenchida com o número lido.

O sistema SHALL apresentar o nome do executante lido no registro como informação de conferência, e SHALL NOT preenchê-lo automaticamente a partir da leitura.

O sistema SHALL exigir a guia e o executante para confirmar as sessões, independentemente de a leitura ter ocorrido antes.

#### Scenario: Escolher a antecipação
- **WHEN** o usuário abrir a lista de antecipações da importação
- **THEN** o sistema SHALL exibir paciente, especialidade e quanto do ciclo já foi utilizado

#### Scenario: Executante da especialidade
- **WHEN** a antecipação estiver escolhida
- **THEN** o sistema SHALL listar apenas profissionais que atendem a especialidade dela

#### Scenario: Executante único
- **WHEN** houver um único profissional para a especialidade
- **THEN** o sistema SHALL selecioná-lo automaticamente

#### Scenario: Número lido identifica uma guia
- **WHEN** o número de guia lido corresponder a exatamente uma guia disponível para lançamento, e o paciente dela não contradisser o da folha
- **THEN** o sistema SHALL selecionar essa guia e SHALL indicar que a escolha veio da leitura

#### Scenario: Número lido não identifica uma guia
- **WHEN** o número de guia lido não corresponder a nenhuma guia disponível, ou corresponder a mais de uma
- **THEN** o sistema SHALL abrir a busca de guia com o número lido já preenchido, e SHALL NOT escolher guia alguma

#### Scenario: Número lido cai numa guia de outro paciente
- **WHEN** o número de guia lido corresponder a uma única guia, mas o paciente dela contradisser o da folha
- **THEN** o sistema SHALL NOT selecioná-la, SHALL abrir a busca de guia com o número lido, e SHALL informar qual dado não fechou

#### Scenario: Registro sem número de guia legível
- **WHEN** a leitura não reconhecer o número da guia
- **THEN** o sistema SHALL manter a escolha da guia com o usuário, sem abrir a busca nem escolher por outro dado

#### Scenario: Executante lido é só informação
- **WHEN** a leitura reconhecer o nome do executante
- **THEN** o sistema SHALL exibi-lo junto ao campo de executante e SHALL NOT selecionar profissional algum a partir dele

#### Scenario: Confirmar continua exigindo guia e executante
- **WHEN** o usuário tentar confirmar as sessões sem guia ou sem executante escolhidos
- **THEN** o sistema SHALL recusar a confirmação, ainda que a leitura já tenha sido feita

## ADDED Requirements

### Requirement: Conferência do paciente entre a folha e a guia

O sistema SHALL comparar o paciente lido na folha com o paciente da guia escolhida, sempre que houver os dois, e SHALL avisar de forma permanente e explícita enquanto eles se contradisserem, nomeando os dois lados.

A comparação SHALL usar o número do cartão quando ele estiver legível na folha e presente na guia, e o nome do paciente como reforço quando o cartão não puder ser comparado. A comparação SHALL tolerar leitura parcial e variação de escrita do nome, acusando apenas contradição — não mera diferença de formato.

O aviso SHALL valer igualmente para a guia escolhida pela leitura e para a escolhida à mão.

#### Scenario: Paciente confere
- **WHEN** o paciente da folha e o da guia escolhida corresponderem
- **THEN** o sistema SHALL NOT exibir aviso de divergência

#### Scenario: Paciente contradiz
- **WHEN** o paciente da folha contradisser o da guia escolhida
- **THEN** o sistema SHALL exibir aviso permanente nomeando o que a folha diz e de quem é a guia

#### Scenario: Cartão decide quando existe dos dois lados
- **WHEN** o número do cartão estiver legível na folha e presente na guia
- **THEN** o sistema SHALL decidir por ele, sem deixar o nome derrubar a conferência

#### Scenario: Leitura parcial do cartão não é contradição
- **WHEN** o número do cartão lido for um trecho do número da guia, ou o contrário
- **THEN** o sistema SHALL tratar como conferido

#### Scenario: Variação de escrita do nome não é contradição
- **WHEN** o nome lido e o da guia compartilharem o primeiro ou o último nome
- **THEN** o sistema SHALL tratar como conferido

#### Scenario: Folha sem dado de paciente
- **WHEN** a folha não trouxer nome nem número de cartão legíveis
- **THEN** o sistema SHALL NOT exibir aviso de divergência

#### Scenario: Divergência na escolha manual
- **WHEN** o usuário escolher à mão uma guia cujo paciente contradiga a folha lida
- **THEN** o sistema SHALL exibir o mesmo aviso da escolha automática

### Requirement: Justificativa para lançar sob divergência

O sistema SHALL exigir confirmação explícita com justificativa escrita para confirmar as sessões enquanto o paciente da guia contradisser o da folha, e SHALL registrar na trilha de auditoria quem decidiu, quando, o que divergia e a justificativa dada.

O sistema SHALL NOT impedir o lançamento sob divergência: há motivos legítimos, e a decisão é de quem tem a folha em mãos. O que o sistema impede é que ela passe em silêncio.

#### Scenario: Confirmar sob divergência pede justificativa
- **WHEN** o usuário confirmar as sessões com divergência de paciente em aberto
- **THEN** o sistema SHALL apresentar o conflito e SHALL exigir uma justificativa escrita antes de gravar

#### Scenario: Justificativa vazia não passa
- **WHEN** o usuário tentar prosseguir sem escrever a justificativa
- **THEN** o sistema SHALL recusar e SHALL NOT gravar sessão alguma

#### Scenario: Desistir na confirmação
- **WHEN** o usuário cancelar a confirmação de divergência
- **THEN** o sistema SHALL NOT gravar sessão alguma e SHALL manter a tela como estava

#### Scenario: Decisão registrada
- **WHEN** o usuário confirmar as sessões com justificativa
- **THEN** o sistema SHALL gravar as sessões e SHALL registrar na auditoria quem decidiu, o que divergia e a justificativa

#### Scenario: Sem divergência não pede nada
- **WHEN** o paciente da guia não contradisser o da folha
- **THEN** o sistema SHALL confirmar as sessões sem pedir justificativa
