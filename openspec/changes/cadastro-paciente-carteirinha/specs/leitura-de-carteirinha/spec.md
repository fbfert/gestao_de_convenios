## ADDED Requirements

### Requirement: Leitura da carteirinha por IA
O sistema SHALL permitir enviar a imagem de uma carteirinha, por câmera ou arquivo, e SHALL devolver os dados extraídos para preencher o cadastro do paciente sem gravar nada por conta própria.

#### Scenario: Ler uma carteirinha
- **WHEN** um usuário enviar a imagem de uma carteirinha
- **THEN** o sistema SHALL devolver carteirinha, nome, convênio, CPF, validade e data de nascimento que conseguir extrair, e SHALL preencher com eles os campos correspondentes do formulário

#### Scenario: Revisão antes de gravar
- **WHEN** a leitura devolver os dados
- **THEN** o sistema SHALL manter todos os campos editáveis e SHALL NOT criar o paciente sem confirmação do usuário

#### Scenario: Convênio lido que não existe no cadastro
- **WHEN** o convênio extraído não corresponder a nenhum convênio da clínica
- **THEN** o sistema SHALL deixar o campo de convênio em branco e SHALL informar o nome lido

#### Scenario: IA não configurada
- **WHEN** a clínica não tiver conexão de IA ativa ou o prompt de leitura de carteirinha
- **THEN** o sistema SHALL recusar a leitura explicando o que falta configurar

#### Scenario: Documento ilegível
- **WHEN** a leitura não conseguir extrair nenhum campo
- **THEN** o sistema SHALL informar que nada foi reconhecido e SHALL manter o formulário como estava

### Requirement: Guarda temporária da imagem
O sistema SHALL guardar a imagem da carteirinha vinculada ao paciente por um prazo configurável na clínica, com padrão de 30 dias, e SHALL removê-la por rotina diária depois do prazo.

#### Scenario: Imagem vinculada ao paciente
- **WHEN** o cadastro for gravado depois de uma leitura
- **THEN** o sistema SHALL vincular a imagem lida ao paciente, com a data em que expira

#### Scenario: Expurgo da imagem vencida
- **WHEN** a rotina diária encontrar imagem além do prazo
- **THEN** o sistema SHALL apagar o arquivo e o registro correspondente

#### Scenario: Leitura descartada
- **WHEN** o usuário ler uma carteirinha e sair sem gravar o paciente
- **THEN** o sistema SHALL NOT manter a imagem vinculada a paciente nenhum

### Requirement: Aviso de carteirinha vencida
O sistema SHALL destacar a carteirinha vencida no cadastro do paciente e ao abrir uma solicitação para ele, e SHALL NOT impedir a operação por causa disso.

#### Scenario: Paciente com carteirinha vencida
- **WHEN** a validade registrada for anterior à data de hoje
- **THEN** o sistema SHALL exibir aviso destacado no paciente e na abertura de solicitação para ele

#### Scenario: Operação segue possível
- **WHEN** o usuário decidir prosseguir com a carteirinha vencida
- **THEN** o sistema SHALL permitir concluir a solicitação normalmente
