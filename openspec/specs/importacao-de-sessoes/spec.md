# importacao-de-sessoes Specification

## Purpose

Lançar no sistema as sessões que já aconteceram, a partir da folha de registro assinada — a mesma que o paciente ou o acompanhante assina na clínica.

A folha é a fonte: ela traz o número da guia, o paciente, o cartão, quem executou e até dez sessões com data e horários. Por isso a leitura vem **antes** da escolha da guia, e não depois: obrigar alguém a achar a guia primeiro é pedir que faça à mão exatamente o que a leitura faria em seguida.

Ler nunca grava. Toda leitura — foto, PDF, webcam ou texto colado — desemboca na mesma grade de conferência, e é a confirmação de uma pessoa que cria as sessões. O que o sistema decide sozinho ele diz que decidiu, e o que ele não consegue confirmar ele não escolhe: uma guia escolhida pelo número lido só é aceita se o paciente da folha também fechar, porque um dígito errado raramente cai no vazio — cai na guia de outra pessoa, e a cota consumida é dela.

## Requirements

### Requirement: Importação em tela própria
O sistema SHALL abrir a importação do registro de sessões em tela dedicada, alcançada a partir da listagem de sessões, e SHALL NOT manter o formulário de importação embutido na listagem.

#### Scenario: Abrir a importação
- **WHEN** o usuário acionar a importação a partir da listagem de sessões
- **THEN** o sistema SHALL abrir a tela dedicada, sem manter a listagem visível no mesmo layout

### Requirement: Leitura do registro por imagem ou texto

O sistema SHALL aceitar o registro de sessões como foto escolhida do dispositivo, como foto capturada pela webcam, como PDF ou como texto colado, e SHALL devolver o mesmo conjunto de dados para revisão, qualquer que seja a origem.

O sistema SHALL permitir a leitura **antes** de a guia e o executante estarem escolhidos, e SHALL NOT exigir a guia para ler.

O sistema SHALL oferecer a captura pela webcam somente quando o navegador a disponibilizar, e SHALL manter os demais caminhos de leitura utilizáveis quando ela não estiver disponível.

O sistema SHALL apresentar a foto capturada para conferência antes de enviá-la para leitura, permitindo descartá-la e capturar outra.

O sistema SHALL encerrar o acesso à câmera ao terminar a captura, ao cancelar e ao sair da tela.

#### Scenario: Ler antes de escolher a guia
- **WHEN** o usuário acionar a leitura sem guia e sem executante escolhidos
- **THEN** o sistema SHALL ler o registro normalmente, porque os dados que identificam a guia estão na própria folha

#### Scenario: Ler foto ou PDF
- **WHEN** o usuário enviar uma foto ou PDF do registro
- **THEN** o sistema SHALL extrair o cabeçalho e as sessões e apresentá-los para conferência

#### Scenario: Capturar pela webcam
- **WHEN** o usuário capturar o registro pela webcam e confirmar a foto
- **THEN** o sistema SHALL lê-la pelo mesmo caminho da foto escolhida do dispositivo, apresentando o cabeçalho e as sessões para conferência

#### Scenario: Descartar a foto capturada
- **WHEN** o usuário descartar a foto capturada
- **THEN** o sistema SHALL NOT enviá-la para leitura e SHALL voltar a mostrar a imagem ao vivo da câmera

#### Scenario: Câmera indisponível no navegador
- **WHEN** o navegador não disponibilizar acesso à câmera
- **THEN** o sistema SHALL NOT oferecer a captura pela webcam, e SHALL manter a leitura por arquivo e por texto

#### Scenario: Permissão de câmera negada
- **WHEN** o acesso à câmera for recusado ou falhar
- **THEN** o sistema SHALL informar o motivo e SHALL indicar os outros caminhos de leitura, sem impedir o uso da tela

#### Scenario: Câmera liberada ao sair
- **WHEN** o usuário confirmar a foto, cancelar a captura ou sair da tela
- **THEN** o sistema SHALL encerrar o acesso à câmera

#### Scenario: Colar a transcrição
- **WHEN** o usuário colar a transcrição em texto
- **THEN** o sistema SHALL extrair o cabeçalho e as sessões no mesmo formato da leitura por imagem

#### Scenario: Linha ilegível
- **WHEN** uma linha lida não tiver data nem horário
- **THEN** o sistema SHALL descartá-la, por ser ruído de leitura e não uma sessão

#### Scenario: Nada reconhecido
- **WHEN** a leitura não reconhecer nenhuma sessão
- **THEN** o sistema SHALL informar o usuário e SHALL manter a tela pronta para nova tentativa

#### Scenario: Nada é gravado na leitura
- **WHEN** a leitura terminar
- **THEN** o sistema SHALL NOT criar sessão alguma antes da confirmação do usuário

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

### Requirement: Retorno visível durante a leitura
O sistema SHALL indicar de forma destacada que a leitura está em andamento e SHALL impedir o disparo de uma segunda leitura enquanto a primeira não terminar.

#### Scenario: Leitura demorada
- **WHEN** a leitura estiver em andamento
- **THEN** o sistema SHALL exibir aviso de progresso e SHALL manter os botões de leitura desabilitados

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

### Requirement: Várias folhas de registro por guia

O sistema SHALL aceitar mais de uma folha de registro de sessões para a mesma guia, porque uma guia de dez sessões costuma ser impressa em duas vias e preenchida em partes.

O sistema SHALL aceitar as folhas no mesmo envio da importação, várias de uma vez, e SHALL aceitar também anexar folhas depois, a uma guia que já teve sessões confirmadas.

O sistema SHALL guardar cada folha vinculada à guia que originou a remessa, na pasta do paciente, junto com a data do envio e quem a enviou.

O sistema SHALL apresentar na guia quais folhas já foram anexadas, e SHALL permitir removê-las enquanto a guia não tiver sido finalizada na operadora.

#### Scenario: Duas folhas no mesmo envio
- **WHEN** o usuário enviar duas folhas na mesma confirmação
- **THEN** o sistema SHALL guardar as duas, ambas vinculadas à mesma guia

#### Scenario: Anexar folha depois
- **WHEN** o usuário anexar uma folha a uma guia que já teve sessões confirmadas
- **THEN** o sistema SHALL guardá-la vinculada àquela guia, sem exigir nova confirmação de sessões

#### Scenario: Ver as folhas da guia
- **WHEN** alguém abrir uma guia com folhas anexadas
- **THEN** o sistema SHALL listar as folhas, com data de envio e quem enviou

#### Scenario: Remover folha antes de finalizar
- **WHEN** o usuário remover uma folha de guia ainda não finalizada na operadora
- **THEN** o sistema SHALL removê-la

#### Scenario: Guia já finalizada na operadora
- **WHEN** a guia já tiver sido finalizada na operadora
- **THEN** o sistema SHALL NOT permitir remover folha alguma, porque ela é o comprovante do que foi enviado

### Requirement: Exigência da folha na regional que a pede

O sistema SHALL continuar exigindo ao menos uma folha de registro na confirmação das sessões quando a carteirinha do paciente for da regional que a exige.

Uma folha SHALL bastar para a confirmação, ainda que as sessões tenham sido preenchidas em mais de uma via impressa: as demais podem ser anexadas depois, antes da finalização.

#### Scenario: Regional exige e nenhuma folha veio
- **WHEN** a carteirinha for da regional que exige a folha e a confirmação não trouxer nenhuma
- **THEN** o sistema SHALL recusar a confirmação, informando a exigência

#### Scenario: Uma folha basta para confirmar
- **WHEN** a confirmação trouxer ao menos uma folha
- **THEN** o sistema SHALL aceitá-la, sem exigir todas as vias de uma vez

### Requirement: Conferência de agenda antes de gravar as sessões

O sistema SHALL conferir as sessões da grade contra as regras de agenda antes de gravá-las, e SHALL recusar a confirmação enquanto houver conflito, apontando quais linhas conflitam e contra o quê.

O sistema SHALL permitir corrigir data e hora na própria grade de conferência e SHALL reconferir a cada correção.

A conferência de agenda SHALL ser independente da conferência do paciente entre a folha e a guia: uma divergência de paciente pode ser confirmada com justificativa, um conflito de agenda não.

#### Scenario: Grade com conflito
- **WHEN** duas linhas da grade violarem o intervalo mínimo entre sessões
- **THEN** o sistema SHALL recusar a confirmação e SHALL marcar as linhas envolvidas

#### Scenario: Conflito com sessão já gravada
- **WHEN** uma linha da grade conflitar com sessão já registrada em outra guia do mesmo paciente
- **THEN** o sistema SHALL recusar a confirmação, nomeando a guia e o horário existentes

#### Scenario: Corrigir na grade
- **WHEN** o usuário corrigir a data ou a hora da linha em conflito
- **THEN** o sistema SHALL reconferir e SHALL liberar a confirmação quando nenhum conflito restar

#### Scenario: Conflito de agenda não aceita justificativa
- **WHEN** o usuário tentar confirmar com conflito de agenda em aberto
- **THEN** o sistema SHALL recusar, e SHALL NOT oferecer justificativa como forma de prosseguir, ainda que a divergência de paciente aceite

#### Scenario: Grade sem conflito
- **WHEN** nenhuma linha violar as regras de agenda
- **THEN** o sistema SHALL seguir o fluxo de confirmação como hoje
