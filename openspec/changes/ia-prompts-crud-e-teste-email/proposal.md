## Why

A tela de IA misturava duas coisas de ciclo de vida diferente num único formulário: a credencial da OpenAI, que é uma só por tenant e muda quase nunca, e os prompts, que são vários e mudam com o uso. Pior, os prompts eram uma lista **fechada** de dois registros — `ler_solicitacao_medica` e `ler_sessoes_escaneadas` —, fixada na validação por `Rule::in`. Acrescentar um terceiro tipo de documento exigia alterar código em três lugares: o form request, o seeder de defaults e o tipo do frontend.

Na tela de e-mails, faltava a única pergunta que importa depois de preencher um SMTP: *isso funciona?* Não havia como descobrir sem esperar o sistema disparar um e-mail de verdade em algum fluxo real.

## What Changes

- Separar a tela de IA em duas: `/configuracoes/ia` para a conexão OpenAI e `/configuracoes/ia/prompts` para os prompts.
- Transformar os prompts num CRUD completo, com chave livre por tenant: criar, listar, editar e excluir.
- Proteger as chaves que o código procura pelo nome: elas podem ser editadas e desativadas, mas não renomeadas nem excluídas.
- Tirar `prompts` do `PUT /configuracoes/ia`, que passa a tratar apenas a conexão.
- Adicionar campo de destino e botão de envio de e-mail de teste em `/configuracoes/emails`.

## Capabilities

### New Capabilities

- `ia-prompts-operacionais`: cadastro dos prompts que a IA usa para transformar documentos em dados, e a proteção das chaves consumidas pelo código.

### Modified Capabilities

- `configuracoes-envio-emails`: ganha o envio de teste. A configuração do SMTP em si não muda.
- `configuracoes-ia-openai`: o `PUT` deixa de aceitar a lista de prompts.

## Impact

- **API**: novo `AiPromptTemplateController` com quatro rotas sob `configuracoes.manage`; `StoreAiPromptTemplateRequest` e `UpdateAiPromptTemplateRequest`; `SendTestEmailRequest` e `EmailSettingsController::enviarTeste`; os defaults de prompt saem do controller para `AiPromptTemplate::garantirPadroes()`.
- **Frontend**: `useAiPrompts`, `ConfiguracoesIaPage`, `PromptsOperacionaisPage`, `useEnviarEmailTeste` e a seção de teste em `ConfiguracoesPage`.
- **Banco**: nenhuma migration. A tabela `ai_prompt_templates` já tinha `chave` como string livre com índice único por tenant — a restrição era só de validação.

## Decisões

- **Chaves de sistema em constante no model, não em configuração.** `AiPromptTemplate::CHAVES_SISTEMA` fica ao lado do model porque a lista existe em função do que o código PHP procura. Uma chave só entra ali quando algum serviço a lê.
- **Excluir uma chave de sistema devolve 422, não 403.** Não é falta de permissão: nenhum papel pode fazer isso. A mensagem sugere desativar, que é o efeito pretendido em quase todos os casos.
- **O envio de teste monta o mailer com o SMTP do tenant.** Em produção `MAIL_MAILER=log`; usar o mailer padrão do `.env` responderia "enviado com sucesso" sem nada sair da máquina — o oposto do que o botão existe para provar.
- **O teste usa o SMTP salvo, não o formulário na tela.** Testar o que ainda não foi gravado provaria uma configuração que o sistema não vai usar. A tela avisa isso em texto.
- **A mensagem de erro do transporte vai crua para a tela.** Autenticação recusada, DNS, TLS e porta errada produzem mensagens diferentes; suprimi-las deixaria o operador sem saber o que corrigir.
- **A semeadura dos prompts padrão roda na leitura.** Não há seeder em produção — o entrypoint só executa `migrate`. Como é `firstOrCreate`, um prompt já editado não é revertido.

## Non-Goals

- Não há execução de prompt a partir da tela: só o cadastro. Testar um prompt continua sendo pelo fluxo real de leitura de documento.
- Não há versionamento nem histórico de alteração de prompt.
- O e-mail de teste tem corpo fixo; não usa os templates cadastrados.
- Não há validação da API key contra a OpenAI ao salvar. O botão "Listar modelos" continua sendo a forma de verificar a credencial.
