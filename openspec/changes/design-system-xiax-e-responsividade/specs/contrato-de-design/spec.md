## ADDED Requirements

### Requirement: Contraste verificado a partir do CSS
O sistema SHALL calcular a razão de contraste de cada par semântico documentado a partir do próprio
arquivo de tokens, resolvendo referências encadeadas, e SHALL reprovar quando um par ficar abaixo do
piso (4,5:1 para texto; 3:1 para borda de campo e anel de foco).

#### Scenario: Token alterado para um tom mais claro
- **WHEN** alguém alterar um token e o par cair abaixo do piso
- **THEN** a verificação SHALL falhar

### Requirement: Nenhum valor mágico
O sistema SHALL reprovar hex literal, valor arbitrário entre colchetes, escala crua de texto,
sombra e camada de outro projeto, e a variante `dark:` nos arquivos de componente.

#### Scenario: Hex dentro de comentário
- **WHEN** um hex literal aparecer, inclusive em comentário
- **THEN** a verificação SHALL falhar, porque é assim que um hex acaba copiado para o código

#### Scenario: Exceção legítima
- **WHEN** um arquivo precisar de literal por natureza
- **THEN** ele SHALL constar de uma lista de isenções com o motivo registrado

### Requirement: Classe com cara de token que não existe
O sistema SHALL reprovar utilitário de cor cujo nome não corresponda a token declarado nem à paleta
nativa conhecida, porque tal classe não gera CSS e o componente renderiza sem a pele da casa.

#### Scenario: Nome de token de outro projeto
- **WHEN** um componente usar `border-borda` em vez de `border-borda-campo`
- **THEN** a verificação SHALL falhar

### Requirement: Compositor de classes preserva o papel
O sistema SHALL declarar os papéis de tamanho ao compositor de classes, para que um papel de
tamanho não seja classificado como cor e descarte a cor na composição.

#### Scenario: Botão compõe cor e tamanho
- **WHEN** um componente compuser um utilitário de cor com um de tamanho
- **THEN** ambos SHALL sobreviver à composição
