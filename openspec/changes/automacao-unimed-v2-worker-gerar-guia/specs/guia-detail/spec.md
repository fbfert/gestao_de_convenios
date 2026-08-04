## ADDED Requirements

### Requirement: Resultado real de geração Unimed na guia
O sistema SHALL persistir e exibir na guia os dados reais retornados pela operação Unimed de geração, sem criar número fictício quando o portal não retornar número de guia.

#### Scenario: Guia Unimed aprovada pelo worker
- **WHEN** a operação `gerar_guia` retornar número de guia, protocolo, situação, sessões e senha
- **THEN** a API e a UI de guia SHALL exibir esses valores nos campos operacionais correspondentes

#### Scenario: Guia Unimed em restrição administrativa
- **WHEN** a operação `gerar_guia` retornar restrição administrativa ou pendência administrativa
- **THEN** a guia local SHALL ficar sem `numero_guia`, com status `needs_verification`, e a UI SHALL exibir "Verificar Restrição" sem inventar número

#### Scenario: Resultado incerto não cria número
- **WHEN** a operação `gerar_guia` retornar resultado `uncertain`
- **THEN** o sistema SHALL manter a execução marcada como incerta e SHALL NOT criar ou alterar guia com número fictício
