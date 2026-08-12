## 1. CRUD de prompts na API

- [x] 1.1 Declarar `AiPromptTemplate::CHAVES_SISTEMA` e `ehDeSistema()`.
- [x] 1.2 Mover os prompts padrão do `AiSettingsController` para `AiPromptTemplate::garantirPadroes()`, chamada na leitura das duas telas.
- [x] 1.3 Criar `AiPromptTemplateController` com index, store, update e destroy, expondo `sistema` em cada registro.
- [x] 1.4 Criar os form requests, com chave em formato de slug e única por tenant.
- [x] 1.5 Recusar a troca de chave de prompt de sistema no update e a exclusão no destroy.
- [x] 1.6 Registrar as quatro rotas sob `permission:configuracoes.manage`.
- [x] 1.7 Remover `prompts` do `UpdateAiSettingsRequest` e do `AiSettingsController::update`.

## 2. Telas de IA

- [x] 2.1 Criar `useAiPrompts` com list, create, update e delete.
- [x] 2.2 Criar `ConfiguracoesIaPage` com a conexão OpenAI e o botão de listar modelos.
- [x] 2.3 Criar `PromptsOperacionaisPage` com a listagem, o editor e a exclusão em duas etapas.
- [x] 2.4 Travar a chave e esconder a exclusão nos prompts marcados como sistema.
- [x] 2.5 Remover a seção de IA de `ConfiguracoesPage` e o estado que ficou órfão.
- [x] 2.6 Registrar as rotas e os dois itens no submenu de Configurações.

## 3. Teste de envio de e-mail

- [x] 3.1 Criar `SendTestEmailRequest` e `EmailSettingsController::enviarTeste`, montando o mailer com o SMTP do tenant.
- [x] 3.2 Recusar com mensagem própria quando o SMTP não estiver preenchido ou estiver inativo.
- [x] 3.3 Devolver a mensagem do transporte quando o envio falhar.
- [x] 3.4 Criar `useEnviarEmailTeste` e a seção de teste na tela de e-mails, com Enter disparando o teste e não o salvamento.

## 4. Validação

- [x] 4.1 `tsc -b` e `oxlint` no `web/` (0 erros, 0 avisos).
- [x] 4.2 `php artisan test` — 167 passam; as 5 falhas são anteriores a esta mudança.
- [x] 4.3 CRUD exercitado contra a API no ar: criar 201, editar 200, excluir 204, chave duplicada e chave inválida recusadas, troca de chave e exclusão de prompt de sistema recusadas. Base devolvida ao estado inicial.
- [x] 4.4 Envio de teste verificado no ar, com entrega real, e e-mail inválido recusado.
- [ ] 4.5 Testes automatizados do CRUD em `api/tests/Feature`.
