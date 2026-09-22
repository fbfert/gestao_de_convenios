# resiliencia-da-interface Specification

## Purpose
Garantir que uma falha de renderização vire uma tela que explica e oferece saída, e não uma página em branco — e que alguém consiga, depois, descobrir o que aconteceu.

## Requirements

### Requirement: Falha de renderização vira tela de erro

O sistema SHALL apresentar uma tela de erro quando uma renderização falhar, e SHALL NOT deixar a página em branco.

A tela SHALL dizer, em português, que algo deu errado naquela tela e que **os dados no servidor não foram perdidos** — porque a primeira pergunta de quem estava no meio de um lançamento é se o trabalho sumiu.

A tela SHALL apresentar um código curto do erro, para que o suporte consiga achá-lo no registro.

A tela SHALL oferecer dois caminhos de saída: recarregar a página e voltar ao início.

#### Scenario: Erro durante a renderização
- **WHEN** um componente lançar durante a renderização
- **THEN** o sistema SHALL apresentar a tela de erro em lugar da tela que falhou

#### Scenario: A tela de erro diz o que importa
- **WHEN** a tela de erro for apresentada
- **THEN** o sistema SHALL informar que os dados no servidor não foram perdidos e SHALL exibir o código do erro

#### Scenario: Voltar ao início
- **WHEN** o usuário escolher voltar ao início
- **THEN** o sistema SHALL navegar para a tela inicial e SHALL voltar a renderizar normalmente, sem recarregar a página

#### Scenario: Recarregar
- **WHEN** o usuário escolher recarregar
- **THEN** o sistema SHALL recarregar a página

#### Scenario: Mudar de rota recupera
- **WHEN** a rota mudar depois de um erro
- **THEN** o sistema SHALL sair da tela de erro e renderizar a rota nova

### Requirement: Erro do navegador chega ao servidor

O sistema SHALL registrar no servidor toda falha da interface: a capturada pela tela de erro e a que acontece fora da renderização — exceção não tratada e promessa rejeitada sem tratamento.

O registro SHALL incluir a mensagem, a pilha, o endereço da tela, o navegador e o momento; e, quando a falha vier de uma renderização, também a pilha de componentes.

O registro SHALL incluir tenant e usuário quando a requisição estiver autenticada, e SHALL acontecer mesmo quando não estiver — erro na tela de login também precisa chegar.

O envio SHALL NOT lançar exceção em nenhuma circunstância: uma falha ao relatar a falha não pode virar a segunda falha.

O sistema SHALL evitar repetição: o mesmo erro SHALL NOT ser enviado mais de uma vez na mesma sessão, e SHALL haver teto de envios por sessão — um erro em laço de renderização não pode virar enxurrada de requisições.

#### Scenario: Erro de renderização é registrado
- **WHEN** a tela de erro for acionada
- **THEN** o sistema SHALL enviar o erro ao servidor, com a pilha de componentes

#### Scenario: Erro fora da renderização
- **WHEN** uma exceção não tratada ou uma promessa rejeitada escapar
- **THEN** o sistema SHALL enviá-la ao servidor

#### Scenario: Usuário não autenticado
- **WHEN** o erro acontecer sem sessão iniciada
- **THEN** o sistema SHALL registrá-lo assim mesmo, sem tenant nem usuário

#### Scenario: Usuário autenticado
- **WHEN** o erro acontecer com sessão iniciada
- **THEN** o sistema SHALL registrar também o tenant e o usuário

#### Scenario: O mesmo erro repetido
- **WHEN** o mesmo erro acontecer de novo na mesma sessão
- **THEN** o sistema SHALL NOT enviá-lo outra vez

#### Scenario: Muitos erros diferentes
- **WHEN** a quantidade de envios da sessão atingir o teto
- **THEN** o sistema SHALL parar de enviar

#### Scenario: O envio falha
- **WHEN** o envio do erro não completar
- **THEN** o sistema SHALL seguir em silêncio e SHALL NOT lançar exceção

#### Scenario: Payload inválido
- **WHEN** o servidor receber um registro sem os campos exigidos
- **THEN** o sistema SHALL recusá-lo

#### Scenario: Volume acima do aceitável
- **WHEN** a quantidade de registros recebidos passar do limite por minuto
- **THEN** o sistema SHALL recusar os excedentes

### Requirement: A interface sobrevive ao DOM reescrito por fora

O sistema SHALL continuar funcionando quando um tradutor de navegador, ou extensão equivalente, reescrever o texto das telas.

Um botão com estado de carregamento SHALL NOT quebrar a página quando acionado depois dessa reescrita: o rótulo SHALL estar dentro de um elemento próprio, para que o indicador de carregamento seja inserido antes de um elemento e não de um nó de texto solto.

O sistema SHALL continuar declarando que as telas não devem ser traduzidas automaticamente, e SHALL NOT tentar detectar nem desfazer a tradução — declara, e sobrevive quando for traduzido assim mesmo.

#### Scenario: Clicar num botão depois da reescrita
- **WHEN** o texto das telas tiver sido reescrito por fora e o usuário acionar um botão que entra em carregamento
- **THEN** o sistema SHALL executar a ação normalmente e SHALL NOT esvaziar a página

#### Scenario: Aparência preservada
- **WHEN** o rótulo do botão passar a viver dentro de um elemento
- **THEN** o sistema SHALL apresentá-lo exatamente como antes
