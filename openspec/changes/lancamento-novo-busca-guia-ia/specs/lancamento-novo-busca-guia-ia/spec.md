## ADDED Requirements

### Requirement: Busca de guia por ID, número, paciente ou profissional
Ao criar um lançamento em `/lancamentos/novo`, o sistema SHALL permitir escolher a Guia através de um modal de busca, aceitando como termo o ID da guia, o número da guia, o nome do paciente ou o nome do profissional executante.

#### Scenario: Buscar por qualquer um dos campos
- **WHEN** o usuário digitar um termo que corresponda ao ID, ao número da guia, ao nome do paciente ou ao nome do profissional executante de uma guia
- **THEN** o sistema SHALL exibir essa guia entre os resultados

#### Scenario: Campo de busca vazio
- **WHEN** o usuário abrir o modal sem digitar nada
- **THEN** o sistema SHALL exibir a listagem de guias com sessão disponível, sem exigir um termo de busca

#### Scenario: Selecionar uma guia
- **WHEN** o usuário clicar em uma guia da lista de resultados
- **THEN** o sistema SHALL preencher o campo Guia do formulário com a guia selecionada e fechar o modal

### Requirement: Executante pré-preenchido e especialidade exibida
Ao selecionar a guia, o sistema SHALL filtrar o profissional executante pela especialidade da guia, pré-selecionando-o quando houver só um, mantendo o campo editável, e SHALL exibir a especialidade da guia como informação própria da tela.

#### Scenario: Único profissional da especialidade
- **WHEN** houver apenas um profissional que atenda a especialidade da guia selecionada
- **THEN** o sistema SHALL pré-selecionar esse profissional no campo de executante, permanecendo o campo editável

#### Scenario: Vários profissionais da especialidade
- **WHEN** houver mais de um profissional que atenda a especialidade da guia selecionada
- **THEN** o sistema SHALL listar apenas esses profissionais no campo de executante, sem pré-selecionar nenhum

#### Scenario: Especialidade exibida
- **WHEN** uma guia estiver selecionada
- **THEN** o sistema SHALL exibir o nome da especialidade dessa guia na tela

### Requirement: Grade fixa de 10 sessões com preenchimento manual
O sistema SHALL exibir sempre 10 linhas para registro de sessões — o máximo físico de uma folha de registro —, permitindo preencher cada linha manualmente, total ou parcialmente, independente de qualquer leitura por IA ou texto colado.

#### Scenario: Preencher manualmente sem anexar nada
- **WHEN** o usuário abrir o formulário de novo lançamento sem anexar foto, PDF ou colar texto
- **THEN** o sistema SHALL exibir 10 linhas em branco, editáveis, prontas para preenchimento manual

#### Scenario: Confirmar com preenchimento parcial
- **WHEN** o usuário preencher apenas algumas das 10 linhas com data de sessão e confirmar o envio
- **THEN** o sistema SHALL registrar uma sessão para cada linha com data preenchida e SHALL ignorar as linhas sem data

### Requirement: Anexo de imagem ou PDF com leitura por IA na mesma tela
O sistema SHALL permitir anexar uma imagem ou PDF da folha de registro de sessões diretamente em `/lancamentos/novo`, lendo o conteúdo por IA e preenchendo a grade de 10 linhas para conferência, sem gravar nada antes da confirmação.

#### Scenario: Anexar e ler
- **WHEN** o usuário anexar uma foto ou PDF da folha de registro de sessões
- **THEN** o sistema SHALL ler o documento por IA e preencher a grade de 10 linhas com o que for reconhecido, mantendo a tela aberta para revisão antes de confirmar

#### Scenario: Nada é gravado antes da confirmação
- **WHEN** a leitura por IA ou pelo texto colado terminar
- **THEN** o sistema SHALL NOT criar nenhuma sessão antes de o usuário confirmar o envio
