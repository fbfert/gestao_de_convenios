## Why

O usuário consegue ver as solicitações na lista, mas hoje precisa sair da tela para abrir a guia e conferir detalhes. Um popup no próprio contexto reduz atrito operacional e acelera a triagem, principalmente quando a equipe só precisa validar dados básicos antes de aprovar ou investigar.

## What Changes

- Tornar o nome do paciente na lista de Solicitações clicável.
- Abrir um popup/modal com os dados resumidos da guia vinculada à solicitação.
- Exibir estados de carregamento, erro e ausência de guia vinculada de forma explícita.
- Reaproveitar os dados e o formato da guia já existentes, sem criar uma experiência paralela para leitura de guia.

## Capabilities

### New Capabilities
- `solicitacoes-guia-popup`: Visualização em popup dos detalhes da guia a partir da lista de solicitações.

### Modified Capabilities
- Nenhuma.

## Impact

- Frontend React: ajuste na tela de Solicitações e novo componente modal/popup de leitura.
- API Laravel: exposição do vínculo entre solicitação e guia no resource da listagem.
- Testes: cobertura do clique no paciente e do estado sem guia vinculada.
