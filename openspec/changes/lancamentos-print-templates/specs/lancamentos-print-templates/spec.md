## ADDED Requirements

### Requirement: Gerenciamento de templates de impressão
O sistema SHALL disponibilizar uma página de Templates em Lançamentos para editar e salvar o HTML do modelo de impressão do Registro de Sessões por tenant.

#### Scenario: Abrir templates a partir de lançamentos
- **WHEN** o operador clicar no botão "Templates" em Registro de Sessões
- **THEN** o sistema SHALL navegar para `/lancamentos/templates`

#### Scenario: Editar HTML do template
- **WHEN** o operador alterar o HTML do template e salvar
- **THEN** o sistema SHALL persistir o HTML para o tenant atual

### Requirement: Placeholders funcionais no template
O sistema SHALL substituir placeholders simples e blocos de sessões ao renderizar o template de Registro de Sessões.

#### Scenario: Renderizar campos simples
- **WHEN** o HTML contiver placeholders como `{{paciente}}` e `{{profissional_executante}}`
- **THEN** o sistema SHALL substituir os placeholders pelos valores disponíveis ou por campo em branco no modelo vazio

#### Scenario: Renderizar linhas de sessões
- **WHEN** o HTML contiver o bloco `{{#sessoes}}...{{/sessoes}}`
- **THEN** o sistema SHALL repetir o bloco para as linhas de sessão disponíveis

### Requirement: Impressão usando template salvo
O sistema SHALL usar o template salvo ao imprimir o modelo em branco de Registro de Sessões.

#### Scenario: Imprimir modelo em branco
- **WHEN** o operador clicar em "Imprimir modelo em branco"
- **THEN** o sistema SHALL renderizar o template salvo com placeholders vazios e linhas em branco antes de chamar a impressão
