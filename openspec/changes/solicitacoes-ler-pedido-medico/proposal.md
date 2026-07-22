## Why

Operadores recebem pedidos médicos em PDF ou imagem e precisam transcrever dados para criar solicitações. Automatizar a leitura com IA reduz digitação, mas precisa manter revisão humana, sugestões de cadastros existentes e o anexo acessível no histórico da solicitação.

## What Changes

- Adicionar botão "Ler pedido médico" ao lado de "Novo" na tela de Solicitações.
- Criar a rota `/solicitacoes/ler-pedido-medico` com upload de PDF, JPG ou PNG.
- Salvar o arquivo do pedido médico e vinculá-lo à solicitação criada.
- Usar a configuração/prompt `ler_solicitacao_medica` para extrair dados do pedido.
- Pré-preencher a criação de solicitação com dados extraídos, exigindo confirmação do operador.
- Sugerir paciente e médico por similaridade de nome, com até cinco sugestões.
- Permitir criação rápida de paciente, especialidade e médico em modais quando o item não existir.
- Mostrar o anexo do pedido médico ao abrir a solicitação pelo nome do paciente na listagem.

## Capabilities

### New Capabilities
- `solicitacoes-ler-pedido-medico`: Leitura assistida por IA de pedidos médicos para pré-preencher solicitações.

### Modified Capabilities

## Impact

- API Laravel: upload/análise do pedido, persistência do anexo na solicitação e endpoint para acessar o arquivo.
- Frontend React: nova tela de leitura, sugestões e modais de criação rápida.
- Banco de dados: campos de anexo/metadados de IA em solicitações.
- Integração OpenAI: uso do Responses API com PDF/imagem enviados como entrada multimodal.
