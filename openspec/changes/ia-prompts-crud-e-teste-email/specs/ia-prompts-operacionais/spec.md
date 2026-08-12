## ADDED Requirements

### Requirement: Cadastro de prompts operacionais
O sistema SHALL permitir que um usuário com `configuracoes.manage` crie, liste, edite e exclua os prompts que a IA usa para transformar documentos em dados estruturados.

#### Scenario: Criar um prompt
- **WHEN** o usuário informar chave, nome, prompt de sistema e prompt do usuário
- **THEN** o sistema SHALL gravar o prompt no tenant do usuário e passar a listá-lo

#### Scenario: Chave em formato inválido
- **WHEN** o usuário informar uma chave que não seja letras minúsculas, números e underscore começando por letra
- **THEN** o sistema SHALL recusar a gravação e explicar o formato aceito

#### Scenario: Chave repetida no tenant
- **WHEN** o usuário informar uma chave que já existe no seu tenant
- **THEN** o sistema SHALL recusar a gravação e informar que a chave já está em uso

#### Scenario: Mesma chave em outro tenant
- **WHEN** dois tenants diferentes cadastrarem prompts com a mesma chave
- **THEN** o sistema SHALL aceitar os dois, isolados por tenant

#### Scenario: Desativar sem excluir
- **WHEN** o usuário desmarcar o prompt como ativo
- **THEN** o sistema SHALL manter o registro e deixar de usá-lo nas leituras automáticas

### Requirement: Proteção das chaves consumidas pelo código
O sistema SHALL impedir que prompts cuja chave é procurada pelo código sejam excluídos ou renomeados, permitindo apenas a edição do seu conteúdo.

#### Scenario: Tentar excluir um prompt de sistema
- **WHEN** o usuário tentar excluir um prompt cuja chave é usada pelo código
- **THEN** o sistema SHALL recusar a exclusão e sugerir desativá-lo

#### Scenario: Tentar renomear a chave de um prompt de sistema
- **WHEN** o usuário tentar gravar um prompt de sistema com chave diferente da original
- **THEN** o sistema SHALL recusar a alteração e informar que aquela chave é usada pelo sistema

#### Scenario: Editar o conteúdo de um prompt de sistema
- **WHEN** o usuário alterar nome, descrição, modelo ou o texto dos prompts de um prompt de sistema
- **THEN** o sistema SHALL gravar as alterações normalmente

#### Scenario: Sinalizar na interface
- **WHEN** o usuário abrir a lista de prompts
- **THEN** o sistema SHALL identificar quais são de sistema e não oferecer a ação de excluir para eles

### Requirement: Prompts padrão disponíveis desde o primeiro acesso
O sistema SHALL garantir que os prompts de sistema existam no tenant sem depender de execução de seeder.

#### Scenario: Primeiro acesso de um tenant novo
- **WHEN** o usuário abrir a listagem de prompts pela primeira vez
- **THEN** o sistema SHALL criar os prompts de sistema que ainda não existirem, com o texto padrão

#### Scenario: Prompt já editado
- **WHEN** um prompt de sistema já tiver sido editado pelo operador
- **THEN** o sistema SHALL preservar o texto editado

### Requirement: Separação entre conexão e prompts
O sistema SHALL tratar a credencial da OpenAI e os prompts em telas e endpoints distintos.

#### Scenario: Salvar a conexão
- **WHEN** o usuário salvar a conexão OpenAI
- **THEN** o sistema SHALL gravar apenas a credencial, sem alterar prompt algum

#### Scenario: Manter a chave já gravada
- **WHEN** o usuário salvar a conexão deixando o campo de API key em branco
- **THEN** o sistema SHALL preservar a chave gravada anteriormente

## MODIFIED Requirements

### Requirement: Envio de e-mail de teste
O sistema SHALL permitir enviar um e-mail de teste para um endereço informado, usando o SMTP gravado do tenant.

#### Scenario: Envio bem-sucedido
- **WHEN** o usuário informar um endereço válido e acionar o envio, com SMTP configurado e ativo
- **THEN** o sistema SHALL enviar a mensagem pelo SMTP do tenant e confirmar o destino na tela

#### Scenario: SMTP não configurado
- **WHEN** o usuário acionar o envio sem servidor ou remetente gravados
- **THEN** o sistema SHALL recusar e pedir que a configuração seja salva antes do teste

#### Scenario: Envio desativado
- **WHEN** o usuário acionar o envio com a configuração de e-mail marcada como inativa
- **THEN** o sistema SHALL recusar e informar que o envio está desativado

#### Scenario: Falha no servidor de e-mail
- **WHEN** o servidor SMTP recusar a mensagem ou a conexão falhar
- **THEN** o sistema SHALL exibir o motivo relatado pelo servidor

#### Scenario: Endereço inválido
- **WHEN** o usuário informar um endereço que não seja um e-mail válido
- **THEN** o sistema SHALL recusar antes de tentar qualquer conexão
