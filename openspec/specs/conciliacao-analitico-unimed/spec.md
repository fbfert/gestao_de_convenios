# conciliacao-analitico-unimed Specification

## Purpose
TBD - created by archiving change conciliacao-analitico-unimed. Update Purpose after archive.
## Requirements
### Requirement: Importação persistente do analítico da Unimed
O sistema SHALL permitir importar o Excel do analítico da Unimed e SHALL persistir o lote importado para conferência posterior.

#### Scenario: Importar analítico e salvar lote
- **WHEN** o operador enviar o arquivo Excel do analítico da Unimed
- **THEN** o sistema SHALL ler o arquivo, normalizar o conteúdo e salvar o lote importado

#### Scenario: Reimportação rastreável
- **WHEN** o operador importar um novo Excel do mesmo período
- **THEN** o sistema SHALL manter o histórico do lote anterior ou registrar explicitamente a substituição

### Requirement: Normalização de linhas do analítico e das glosas
O sistema SHALL transformar as linhas do analítico e das glosas em registros processáveis por guia, quantidade e valor.

#### Scenario: Linha paga vira registro processável
- **WHEN** o Excel trouxer uma linha paga no analítico
- **THEN** o sistema SHALL normalizar a linha com guia, quantidade, valor e referência de origem

#### Scenario: Linha glosada vira registro processável
- **WHEN** o Excel trouxer uma linha na aba de glosas
- **THEN** o sistema SHALL normalizar a linha com guia, motivo e valor glosado

### Requirement: Conciliação por guia com distinção de profissionais
O sistema SHALL gerar e exibir a conciliação financeira por guia, distinguindo o profissional informado ao plano do profissional executor.

#### Scenario: Profissional informado e executor
- **WHEN** a guia tiver um profissional informado ao convênio e um executor diferente
- **THEN** o sistema SHALL registrar e exibir ambos na conciliação

#### Scenario: Fechamento financeiro por guia
- **WHEN** a conciliação de uma guia for processada
- **THEN** o sistema SHALL registrar o total recebido, o total repassado e o saldo

### Requirement: Repasse por sessão paga
O sistema SHALL calcular o repasse financeiro usando a quantidade de sessões pagas e o percentual configurável por profissional.

#### Scenario: Repassar por sessão paga
- **WHEN** uma linha do analítico indicar uma sessão paga
- **THEN** o sistema SHALL calcular o repasse correspondente ao profissional executor

#### Scenario: Percentual configurável
- **WHEN** o profissional possuir percentual de repasse configurado
- **THEN** o sistema SHALL usar esse percentual para o cálculo financeiro

### Requirement: Pré-visualização e conferência operacional
O sistema SHALL manter a pré-visualização da importação para revisão humana antes do fechamento financeiro.

#### Scenario: Conferir antes de fechar
- **WHEN** o operador revisar o lote importado
- **THEN** o sistema SHALL permitir conferir os dados antes de registrar o fechamento

#### Scenario: Resumir totais do analítico
- **WHEN** a pré-visualização for exibida
- **THEN** o sistema SHALL mostrar os totais de pago, glosado e saldo

