## Why

A tela de Configurações de IA listava os modelos disponíveis da OpenAI, mas não havia onde escolher: os nomes eram etiquetas estáticas. O único lugar do sistema com modelo era o `model_id` de cada prompt, e quando o prompt vinha sem modelo o `PedidoMedicoAiService` caía num literal no código (`?: 'gpt-5.6-luna'`). Trocar o modelo usado pela clínica exigia editar prompt por prompt, ou alterar código e fazer deploy.

Havia ainda um defeito ao lado: o campo Modelo da tela de Prompts declarava um `datalist`, mas a busca que o alimentava era criada desabilitada e nunca refeita — a lista estava sempre vazia.

## What Changes

- Nova coluna `ai_openai_settings.model_id`: o modelo padrão da conexão.
- Ordem de resolução no serviço: modelo do prompt → padrão da conexão → literal do código.
- Campo **Modelo padrão** na tela de conexão, com autocompletar, e a lista de modelos volta a ser clicável.
- O campo Modelo da tela de Prompts passa a receber os modelos quando o editor é aberto.

## Capabilities

### Modified Capabilities

- `configuracoes-ia-openai`: a conexão passa a definir o modelo padrão da clínica.

## Impact

- **API**: migration da coluna, model, resource, request e a ordem de resolução em `PedidoMedicoAiService`.
- **Frontend**: `ConfiguracoesIaPage`, `PromptsOperacionaisPage` e os tipos de `useAiSettings`.

## Decisões

- **O literal do código continua como último recurso.** Uma instalação recém-criada, sem conexão configurada e sem modelo no prompt, não deve quebrar sozinha.
- **A busca de modelos na tela de Prompts dispara ao abrir o editor, não na listagem.** A chamada bate na OpenAI; carregar sempre gastaria uma ida à API a cada visita, e a listagem não precisa dos modelos.
- **A mensagem de erro da OpenAI passa a ir crua para a tela.** A versão anterior dizia apenas "verifique a API key e o projeto", o que tornava um `Invalid project ID` indistinguível de chave revogada ou cota estourada — a única saída era tentar por eliminação.

## Non-Goals

- Não há validação do identificador do modelo contra a lista da OpenAI ao salvar: o campo aceita texto livre, porque modelos novos aparecem antes de qualquer lista fixa.
- Não há modelo por tipo de documento além do que o prompt já permite.
