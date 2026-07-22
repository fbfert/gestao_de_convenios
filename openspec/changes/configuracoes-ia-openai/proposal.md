## Why

A clínica quer configurar a conexão com a OpenAI e controlar prompts operacionais sem alterar código. Isso permite evoluir a leitura de solicitações médicas e sessões escaneadas com rastreabilidade por tenant.

## What Changes

- Criar uma subaba "Configurações de IA" dentro de Configurações.
- Permitir salvar credenciais e cabeçalhos opcionais da OpenAI por tenant.
- Listar modelos disponíveis pela API da OpenAI usando a credencial salva.
- Permitir cadastrar e editar prompts para:
  - ler solicitações médicas e converter em Solicitações;
  - ler sessões escaneadas e converter em dados estruturados para o banco.
- Não expor a API key salva na resposta da API.

## Capabilities

### New Capabilities
- `configuracoes-ia-openai`: Configuração da conexão OpenAI e prompts operacionais por tenant.

### Modified Capabilities

## Impact

- API Laravel: novas persistências, endpoints autenticados e integração HTTP com a API de modelos da OpenAI.
- Frontend React: nova subaba em Configurações com formulário de conexão, listagem de modelos e edição de prompts.
- Banco de dados: novas tabelas para credenciais OpenAI e prompts de IA.
