## MODIFIED Requirements

### Requirement: Informações operacionais da guia
O sistema SHALL exibir número, status traduzido por `translateStatus('guias', ...)`, tipo de terapia, paciente e carteirinha, convênio, profissional, especialidade, datas de solicitação e finalização, senha e validade da senha.

O sistema SHALL exibir, quando a guia estiver marcada como finalizada na operadora, essa marca e a data da conferência que a produziu — separadas do status, porque dizem outra coisa: o status conta o que o Gescon sabe do ciclo da guia, e a marca conta o que o portal respondeu.

#### Scenario: Exibir uma guia finalizada
- **WHEN** uma guia finalizada for carregada
- **THEN** o sistema SHALL apresentar a senha, a validade e a data de finalização retornadas pela API

#### Scenario: Exibir a marca de finalizada na operadora
- **WHEN** a guia estiver marcada como finalizada na operadora
- **THEN** o sistema SHALL apresentar a marca e a data da conferência, sem alterar a apresentação do status

#### Scenario: Guia sem a marca
- **WHEN** a guia não estiver marcada como finalizada na operadora
- **THEN** o sistema SHALL NOT apresentar a marca

#### Scenario: Destacar validade próxima
- **WHEN** a validade da senha estiver dentro de sete dias a partir da data atual
- **THEN** o sistema SHALL aplicar o mesmo destaque visual de prazo próximo usado na lista de guias
