## ADDED Requirements

### Requirement: Tokens em dois níveis
O sistema SHALL definir a paleta como primitivos (`--neutro-*`, `--marca-*`, feedback) e os papéis
semânticos (`--texto`, `--acento`, `--perigo-suave`, ...) sobre eles, e componentes SHALL consumir
apenas os papéis semânticos, nunca os primitivos.

#### Scenario: Componente pede cor
- **WHEN** um componente precisar de uma cor
- **THEN** ele SHALL usar o utilitário do papel (`bg-superficie`, `text-texto-suave`) e SHALL NOT
  referenciar `--neutro-600` ou hex literal

#### Scenario: Ajuste de identidade
- **WHEN** um valor de primitivo mudar
- **THEN** o produto inteiro SHALL acompanhar, sem alteração em telas

### Requirement: Tema único
O sistema SHALL ter um único tema visual e SHALL NOT oferecer alternância de aparência ao usuário.

#### Scenario: Preferência antiga salva no navegador
- **WHEN** o navegador tiver a chave de tema de uma versão anterior
- **THEN** o sistema SHALL ignorá-la e SHALL apresentar o tema único

### Requirement: Sete papéis tipográficos
O sistema SHALL definir tamanho de texto apenas por `display`, `titulo`, `subtitulo`, `corpo-lg`,
`corpo`, `rotulo` e `meta`, cada um carregando a própria entrelinha, e SHALL NOT usar a escala crua
do framework nem tamanho arbitrário.

#### Scenario: Escrever texto novo
- **WHEN** uma tela precisar de um tamanho de texto
- **THEN** ela SHALL usar um dos sete papéis

### Requirement: Altura única de controle
O sistema SHALL aplicar a mesma altura a campo, seletor e botão que dividam uma fila, para que
não haja degrau visível entre eles.

#### Scenario: Fila de filtros
- **WHEN** uma tela exibir campos, seletores e um botão de ação na mesma fila
- **THEN** todos SHALL ter a mesma altura

### Requirement: Alvo de toque mínimo
O sistema SHALL garantir área clicável de ao menos 24×24 pixels para todo controle acionável,
considerando o rótulo que o envolve quando houver.

#### Scenario: Caixa de seleção com rótulo
- **WHEN** uma caixa de seleção estiver dentro de um rótulo clicável
- **THEN** o rótulo SHALL medir ao menos 24 pixels de altura
