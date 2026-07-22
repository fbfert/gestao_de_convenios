## MODIFIED Requirements

### Requirement: Importação persistente do analítico da Unimed
O sistema SHALL permitir importar o Excel do analítico da Unimed e SHALL persistir o lote importado para conferência posterior. A interface principal desse fluxo SHALL ficar concentrada na página de Analíticos.

#### Scenario: Importar analítico e salvar lote
- **WHEN** o operador enviar o arquivo Excel do analítico da Unimed pela página de Analíticos
- **THEN** o sistema SHALL ler o arquivo, normalizar o conteúdo e salvar o lote importado

#### Scenario: Importação removida de Sessões
- **WHEN** o operador abrir a página de Sessões
- **THEN** o sistema SHALL NOT exibir a interface de importação do analítico da Unimed nessa página

#### Scenario: Reimportação rastreável
- **WHEN** o operador importar um novo Excel do mesmo período
- **THEN** o sistema SHALL manter o histórico do lote anterior ou registrar explicitamente a substituição

### Requirement: Pré-visualização e conferência operacional
O sistema SHALL manter a pré-visualização da importação para revisão humana antes do fechamento financeiro, com ações explícitas de salvar ou recusar.

#### Scenario: Conferir antes de fechar
- **WHEN** o operador revisar o lote importado
- **THEN** o sistema SHALL permitir conferir os dados antes de registrar o fechamento

#### Scenario: Resumir totais do analítico
- **WHEN** a pré-visualização for exibida
- **THEN** o sistema SHALL mostrar os totais de pago, glosado e saldo
