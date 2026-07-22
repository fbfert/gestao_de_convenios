# Resumo de entregas - 2026-07-22

Este documento consolida as entregas recentes do sistema de Gestão de Convênios.

## Solicitações

- A tela de Solicitações passou a exibir três ações de status: Em análise, Aprovado e Negado.
- Cada alteração de status exige confirmação do operador antes de chamar a API.
- O status Negado não bloqueia mais a troca para Aprovado ou Em análise.

## Leitura de pedido médico

- Foi adicionada a rota web `/solicitacoes/ler-pedido-medico`.
- A tela permite anexar pedido médico em PDF, JPG, JPEG ou PNG.
- A API usa a configuração OpenAI e o prompt `ler_solicitacao_medica` para extrair dados do pedido.
- O operador revisa e confirma os campos antes de criar a Solicitação.
- O fluxo sugere até 5 pacientes, médicos solicitantes e especialidades por similaridade.
- O operador pode criar rapidamente paciente, médico solicitante e especialidade por modal.
- O arquivo original do pedido médico fica salvo na Solicitação.
- Ao clicar no paciente/nome da Solicitação na listagem, o modal exibe o anexo do pedido médico quando existir.

## Configurações

### Envio de e-mails

- Foi adicionada uma subaba de Configurações para Envio de E-mails.
- A API salva dados SMTP por tenant.
- A tela permite gerenciar templates de e-mail com chave, nome, assunto, corpo e status ativo.

### Configurações de IA

- Foi adicionada uma aba de Configurações de IA.
- A API salva a conexão OpenAI por tenant, incluindo API key, base URL, organização, projeto e status.
- A tela permite listar modelos configurados pela OpenAI.
- A tela permite cadastrar prompts para:
  - leitura de solicitações médicas;
  - leitura de sessões escaneadas.

## Sessões e Analíticos

- A importação do analítico Unimed foi retirada da tela de Sessões.
- A importação e conferência do analítico passaram para a área de Analíticos.
- O menu recebeu item específico para Analíticos.
- A conciliação do analítico mantém o fluxo de leitura das linhas pagas e glosadas antes da conferência.

## Templates de impressão de sessões

- A tela de Registro de Sessões recebeu o botão Templates.
- Foi criada a rota web `/lancamentos/templates`.
- A API salva o template de impressão `registro_sessoes` por tenant.
- O operador pode editar livremente o HTML do modelo de impressão.
- A tela mostra preview do HTML renderizado com dados de exemplo.
- A impressão do modelo em branco passou a usar o template salvo.
- Placeholders funcionais:
  - `{{guia_numero}}`
  - `{{clinica}}`
  - `{{paciente}}`
  - `{{numero_cartao}}`
  - `{{profissional_executante}}`
  - `{{terapia_aplicada}}`
  - `{{data_impressao}}`
  - bloco `{{#sessoes}}...{{/sessoes}}`
  - `{{numero}}`
  - `{{data_sessao}}`
  - `{{hora_inicio}}`
  - `{{hora_fim}}`
  - `{{acompanhante}}`
  - `{{resumo_atividades}}`

## Manual e dashboard

- Foram adicionadas estruturas para manual interno e mapa mental.
- O dashboard recebeu melhorias de atalhos e visão operacional.
- A auditoria ganhou área própria no frontend e endpoints de apoio.

## Banco de dados

Novas estruturas adicionadas:

- `email_smtp_settings`
- `email_templates`
- `ai_openai_settings`
- `ai_prompt_templates`
- campos de anexo do pedido médico em `solicitacoes`
- `lancamento_print_templates`
- estruturas de manual interno

## Validações executadas

Durante as entregas foram executadas validações OpenSpec, sintaxe PHP, migrations, testes de API e verificações frontend.

Comandos principais executados:

- `openspec validate solicitacao-status-buttons`
- `openspec validate configuracoes-envio-emails`
- `openspec validate configuracoes-ia-openai`
- `openspec validate menu-analiticos-importacao`
- `openspec validate solicitacoes-ler-pedido-medico`
- `openspec validate lancamentos-print-templates`
- `php -l` nos arquivos PHP novos e alterados
- `php artisan migrate:fresh --seed --env=testing --force`
- `php artisan migrate --force`
- `php artisan test --filter=LancamentosApiTest`
- `php artisan test --filter=PedidoMedicoSolicitacaoApiTest`
- `npm run lint`
- `npm run build`
- `git diff --check`

## OpenSpec

Mudanças documentadas em OpenSpec:

- `openspec/changes/solicitacao-status-buttons`
- `openspec/changes/configuracoes-envio-emails`
- `openspec/changes/configuracoes-ia-openai`
- `openspec/changes/menu-analiticos-importacao`
- `openspec/changes/solicitacoes-ler-pedido-medico`
- `openspec/changes/lancamentos-print-templates`
