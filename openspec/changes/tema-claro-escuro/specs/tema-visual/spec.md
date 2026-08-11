## ADDED Requirements

### Requirement: Temas visuais claro e escuro
O sistema SHALL oferecer dois temas visuais no frontend — `escuro` e `claro` — aplicados por um atributo `data-theme` no elemento raiz do documento, sem alterar a estrutura ou o comportamento das telas.

#### Scenario: Tema padrão
- **WHEN** um usuário abrir o sistema sem nunca ter escolhido um tema
- **THEN** o sistema SHALL aplicar o tema `escuro`

#### Scenario: Cobertura de todas as telas
- **WHEN** o tema `claro` estiver ativo
- **THEN** o sistema SHALL aplicar a paleta clara em todas as telas, modais e listas suspensas, mantendo texto legível sobre os fundos correspondentes

### Requirement: Troca de tema em Configurações
O sistema SHALL permitir que o usuário escolha o tema na aba Geral da tela de Configurações.

#### Scenario: Selecionar tema claro
- **WHEN** o usuário selecionar o tema claro em Configurações → Geral
- **THEN** o sistema SHALL aplicar o tema claro imediatamente, sem recarregar a página

#### Scenario: Indicar o tema ativo
- **WHEN** o usuário abrir Configurações → Geral
- **THEN** o sistema SHALL indicar visualmente qual tema está ativo

### Requirement: Persistência da preferência de tema
O sistema SHALL persistir a preferência de tema no navegador do usuário e aplicá-la antes da primeira renderização da interface.

#### Scenario: Retorno ao sistema
- **WHEN** o usuário que escolheu um tema abrir o sistema novamente no mesmo navegador
- **THEN** o sistema SHALL aplicar o tema escolhido

#### Scenario: Sem flash de tema incorreto
- **WHEN** a página for carregada com o tema claro persistido
- **THEN** o sistema SHALL aplicar o tema antes da primeira pintura, sem exibir o tema escuro momentaneamente

### Requirement: Impressão independente do tema
O sistema SHALL manter os layouts de impressão com fundo branco e texto escuro, independentemente do tema ativo na tela.

#### Scenario: Imprimir com tema claro ativo
- **WHEN** o usuário imprimir um documento (ex.: recibo de sessões) com qualquer tema ativo
- **THEN** o sistema SHALL gerar a impressão com fundo branco e texto escuro
