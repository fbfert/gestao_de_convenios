## Why

O sistema já cobre partes do ciclo de guias, antecipações e conciliação, mas ainda não reflete o fluxo operacional real informado pelo cliente: acolhimento do paciente, solicitação/guia única, continuidade por antecipações, registro de sessões, envio à Unimed, leitura do analítico em Excel e cálculo do repasse. Formalizar esse fluxo agora evita expandir regras em telas e serviços sem um contrato claro.

## What Changes

- Consolidar o fluxo operacional de convênio como uma jornada única do paciente, da solicitação inicial ao repasse financeiro.
- Tratar guia e solicitação como a mesma entidade operacional, com leitura de PDF e preparação para controle de autorização.
- Registrar o ciclo de atendimento com antecipações, agendamento e sessões, respeitando as regras de liberação do convênio.
- Preparar o registro de sessões para finalização e envio à Unimed, incluindo variações por regional.
- Importar o analítico retornado pela Unimed em Excel e transformá-lo em dados processáveis para conciliação.
- Calcular entradas e saídas financeiras por sessão paga e registrar o repasse do profissional.
- **BREAKING**: ampliar o vocabulário de status operacional das guias/solicitações para refletir o fluxo real da clínica.

## Capabilities

### New Capabilities
- `fluxo-operacional-convenio`: Jornada completa de autorização, atendimento, envio ao convênio, conciliação e repasse financeiro.

### Modified Capabilities
- Nenhuma. O novo fluxo será especificado como capability própria para evitar acoplamento com specs ainda não formalizadas.

## Impact

- API Laravel: evolução dos recursos de guia, antecipação, conciliação e possivelmente novos endpoints para sessão e importação do analítico.
- Frontend React: ajustes de navegação, telas operacionais, traduções de status e estados de acompanhamento.
- Banco de dados: suporte ao histórico de sessões, importação do analítico e rastreio financeiro por guia/sessão/profissional.
- Integrações: processamento de PDF/foto para leitura automática e importação de Excel da Unimed.
