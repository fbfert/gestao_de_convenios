## ADDED Requirements

### Requirement: Confirmação em duas etapas para exclusão sem volta
O sistema SHALL exigir, antes de qualquer exclusão sem volta, uma confirmação em duas etapas: um diálogo identificando o que será apagado, com a consequência em uma frase, e a digitação da palavra `EXCLUIR`.

A exigência vale para toda exclusão irreversível, e não só para anexo: anexo de solicitação, perfil de acesso e template de e-mail estão cobertos hoje.

#### Scenario: Excluir um anexo
- **WHEN** o usuário acionar a exclusão de um anexo
- **THEN** o sistema SHALL abrir um diálogo com o nome do arquivo e a consequência da exclusão

#### Scenario: Excluir um perfil de acesso
- **WHEN** o usuário acionar a exclusão de um perfil
- **THEN** o sistema SHALL abrir o mesmo diálogo, nomeando o perfil e avisando que quem o tiver perde as permissões dele

#### Scenario: Excluir um template de e-mail
- **WHEN** o usuário acionar a exclusão de um template de e-mail
- **THEN** o sistema SHALL abrir o mesmo diálogo, nomeando o template e avisando que os avisos que dependiam dele passam a usar o texto padrão de reserva

#### Scenario: Liberar a exclusão
- **WHEN** o usuário digitar a palavra de confirmação
- **THEN** o sistema SHALL habilitar a ação de excluir

#### Scenario: Desistir
- **WHEN** o usuário cancelar ou fechar o diálogo
- **THEN** o sistema SHALL manter intacto o que seria apagado

### Requirement: Confirmação proporcional ao risco da ação

O sistema SHALL pedir confirmação para ação que muda estado de forma relevante sem ser destrutiva, e nesses casos SHALL NOT exigir a digitação da palavra — o diálogo basta.

Separar os dois pesos é o ponto: exigir digitar `EXCLUIR` para trocar um status vira atrito sem motivo e ensina a pessoa a ignorar o pedido, que é justamente o que a confirmação de exclusão tenta evitar.

#### Scenario: Trocar o status de uma solicitação
- **WHEN** o usuário escolher um novo status para uma solicitação
- **THEN** o sistema SHALL confirmar em um diálogo dizendo qual será o novo status, sem exigir palavra digitada

#### Scenario: Desistir da troca
- **WHEN** o usuário cancelar o diálogo
- **THEN** o sistema SHALL manter o status atual

### Requirement: Confirmação e aviso na própria tela

O sistema SHALL usar diálogos e avisos do próprio aplicativo, e SHALL NOT usar as caixas nativas do navegador (`window.confirm`, `window.alert`) para confirmar ação ou relatar erro.

Duas razões: a caixa nativa trava a aba inteira até alguém clicar, e some sem deixar rastro na tela — quem fechou o aviso perde a mensagem de erro e não tem como reler.

#### Scenario: Erro de uma ação da listagem
- **WHEN** uma ação disparada pela listagem falhar
- **THEN** o sistema SHALL exibir o erro na própria tela, onde ele permaneça legível

#### Scenario: Nenhuma caixa nativa
- **WHEN** o sistema precisar confirmar uma ação ou relatar um erro
- **THEN** o sistema SHALL fazê-lo por componente próprio, e SHALL NOT abrir caixa nativa do navegador
