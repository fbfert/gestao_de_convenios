## MODIFIED Requirements

### Requirement: Erro do navegador chega ao servidor

O sistema SHALL registrar no servidor toda falha da interface: a capturada pela tela de erro e a que acontece fora da renderização — exceção não tratada e promessa rejeitada sem tratamento.

O registro SHALL incluir a mensagem, a pilha, o endereço da tela, o navegador e o momento; e, quando a falha vier de uma renderização, também a pilha de componentes.

O registro SHALL incluir tenant e usuário quando houver sessão iniciada no navegador, e SHALL acontecer mesmo quando não houver — erro na tela de login também precisa chegar. Para isso o relato SHALL levar a credencial da sessão quando ela existir: sem isso o servidor recebe todo erro como anônimo, e um erro que ninguém sabe de qual clínica veio é um erro que ninguém pode investigar.

O envio SHALL NOT lançar exceção em nenhuma circunstância: uma falha ao relatar a falha não pode virar a segunda falha. Em particular, ler a credencial da sessão SHALL NOT impedir o relato quando essa leitura falhar.

O sistema SHALL evitar repetição: o mesmo erro SHALL NOT ser enviado mais de uma vez na mesma sessão, e SHALL haver teto de envios por sessão — um erro em laço de renderização não pode virar enxurrada de requisições.

O sistema SHALL registrar também o relato que a validação recusar, com o motivo da recusa. Recusa silenciosa apaga justamente o erro que alguém viu na tela e veio perguntar.

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
- **WHEN** o erro acontecer com sessão iniciada no navegador
- **THEN** o relato SHALL levar a credencial dessa sessão e o sistema SHALL registrar o tenant e o usuário

#### Scenario: Credencial ilegível
- **WHEN** a credencial da sessão não puder ser lida
- **THEN** o sistema SHALL enviar o relato sem ela, e SHALL NOT deixar de enviar

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
- **THEN** o sistema SHALL recusá-lo e SHALL registrar a recusa com o motivo

#### Scenario: Volume acima do aceitável
- **WHEN** a quantidade de registros recebidos passar do limite por minuto
- **THEN** o sistema SHALL recusar os excedentes

### Requirement: Falha de renderização vira tela de erro

O sistema SHALL apresentar uma tela de erro quando uma renderização falhar, e SHALL NOT deixar a página em branco.

A tela SHALL dizer, em português, que algo deu errado naquela tela e que **os dados no servidor não foram perdidos** — porque a primeira pergunta de quem estava no meio de um lançamento é se o trabalho sumiu.

A tela SHALL apresentar um código curto do erro, e esse código SHALL ser **o mesmo que o registro do servidor guarda para aquele erro**. Um código que a tela mostra e o log não contém não serve para nada: ele existe só para casar o telefonema da clínica com a linha do registro.

O código SHALL ser derivado da mensagem e da pilha, e SHALL ser calculado sobre exatamente os mesmos valores que o relato envia — inclusive depois de qualquer corte de tamanho aplicado ao enviar.

O mesmo erro SHALL produzir o mesmo código em usuários e navegadores diferentes, para que várias pessoas relatando o mesmo código sejam uma investigação e não várias.

A tela SHALL oferecer dois caminhos de saída: recarregar a página e voltar ao início.

#### Scenario: Erro durante a renderização
- **WHEN** um componente lançar durante a renderização
- **THEN** o sistema SHALL apresentar a tela de erro em lugar da tela que falhou

#### Scenario: A tela de erro diz o que importa
- **WHEN** a tela de erro for apresentada
- **THEN** o sistema SHALL informar que os dados no servidor não foram perdidos e SHALL exibir o código do erro

#### Scenario: O código da tela encontra o registro
- **WHEN** alguém procurar no registro do servidor pelo código que a tela mostrou
- **THEN** SHALL encontrar o registro daquele erro

#### Scenario: Pilha longa
- **WHEN** a pilha do erro for maior do que o relato envia
- **THEN** o código da tela SHALL continuar igual ao do registro, porque os dois são calculados sobre o valor enviado

#### Scenario: Voltar ao início
- **WHEN** o usuário escolher voltar ao início
- **THEN** o sistema SHALL navegar para a tela inicial e SHALL voltar a renderizar normalmente, sem recarregar a página

#### Scenario: Recarregar
- **WHEN** o usuário escolher recarregar
- **THEN** o sistema SHALL recarregar a página

#### Scenario: Mudar de rota recupera
- **WHEN** a rota mudar depois de um erro
- **THEN** o sistema SHALL sair da tela de erro e renderizar a rota nova
