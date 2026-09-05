# Entregas de 05/09/2026 — Pasta do paciente, anexos na criação da solicitação, aviso de convênio

## 1. Pasta do paciente — biblioteca de documentos reaproveitável entre solicitações

Pedido do usuário: clicar no nome do paciente na listagem deveria abrir uma "pasta" com todos os
documentos dele (Pedido Médico, Laudo Médico, Plano Individualizado, Relatório de Evolução), com
um botão **Gerar Solicitação** que permite ler um pedido novo ou anexar um documento já existente.

Isso exigiu um refactor de modelo, não só uma tela nova: até então `solicitacao_documentos`
guardava o arquivo em si (path/mime/nome_original), preso a **uma única** solicitação. Nova tabela
`paciente_arquivos` passa a guardar o arquivo, por paciente, independente de solicitação;
`solicitacao_documentos` virou um vínculo puro (`solicitacao_id`, `solicitacao_item_id`,
`paciente_arquivo_id`) — o mesmo arquivo pode estar vinculado a várias solicitações ao mesmo tempo.
A trava "depois da guia" (anexo vira evidência do envio e não pode mais ser removido) passou a
valer **por vínculo**, não por arquivo: uma guia gerada numa solicitação não impede o mesmo
documento de servir outra.

Migração de dados (produção real): 33 `solicitacao_documentos` existentes viraram 33
`paciente_arquivos` + seus vínculos, zero órfãos (todo pedido médico legado, inclusive de
solicitações anteriores à própria tabela `solicitacao_documentos`, já tinha uma linha
correspondente). Colunas legadas `pedido_medico_*` em `solicitacoes` foram derrubadas depois do
backfill — `SolicitacaoResource` e a rota de download passaram a derivar do vínculo.

Novos endpoints: `GET/POST /pacientes/{paciente}/arquivos` (lista e upload solto, sem solicitação
nenhuma), `GET/DELETE /pacientes/{paciente}/arquivos/{arquivo}` (download, exclusão só permitida
com zero vínculos — trava permanente enquanto existir vínculo com guia gerada, por design), e
`POST /solicitacoes/{solicitacao}/documentos/vincular` (anexa um arquivo já existente na pasta, sem
reenviar). Tela nova: `PastaDoPacienteDrawer.tsx`, painel lateral aberto ao clicar no nome do
paciente em `PacientesPage.tsx`.

Testes novos: `PacienteArquivoApiTest` (upload solto, exclusão bloqueada com vínculo, mesmo arquivo
vinculado a duas solicitações com trava independente por vínculo). Testes existentes
(`SolicitacaoDocumentosApiTest`, `PedidoMedicoSolicitacaoApiTest`, `GerarGuiaUnimedApiTest`,
`ConfirmarGuiaIncertaUnimedApiTest`) reescritos pro novo modelo.

Commit: `7105016`.

## 2. Dados cadastrais e botão "Editar cadastro" na pasta do paciente

A pasta (item 1) só mostrava nome/carteirinha/convênio. Pedido de ajuste do usuário logo depois de
ver a tela ao vivo: trazer CPF, data de nascimento, telefone principal, validade da carteirinha
(com aviso de vencida) e status ativo/inativo, mais um botão que leva direto pra edição do
cadastro — sem precisar fechar a pasta e procurar o paciente de novo na lista.

Commit: `786e7c4`.

## 3. Anexar Laudo, Plano e Relatório já na tela de criação da solicitação

Até aqui, tanto o cadastro manual quanto o assistente "Ler pedido médico" criavam a solicitação
sem anexo nenhum (nem Pedido Médico, no caminho manual) — tudo entrava depois, editando a
solicitação já criada. Pedido do usuário: poder anexar Laudo Médico (pedido inteiro) e Plano
Individualizado/Relatório de Evolução (por especialidade) já no fluxo de criação, e ser avisado
automaticamente quando o paciente escolhido já tiver algum desses documentos na pasta.

Solução: como um documento só pode ser vinculado depois que a solicitação (e os itens) existem
com id real, as duas telas passaram a, **logo após criar a solicitação**, trocar o formulário pela
mesma etapa de anexos que já existia na edição (`SolicitacaoAnexos`, reaproveitada via novo
componente `SolicitacaoAnexosStep`) — sem navegar pra outro lugar, só um "Concluir" no final. Um
aviso automático (`ResumoPastaPaciente`) aparece assim que o paciente é escolhido, contando quantos
documentos de cada tipo já existem na pasta dele, antes mesmo de a solicitação existir.

Nenhuma mudança de backend — reaproveitou 100% os endpoints e componentes do item 1. E2E
(`mvp-flow.spec.ts`) ajustado pro novo fluxo (criar não navega mais direto pra lista).

Commit: `6932fa1`.

## 4. Aviso quando o paciente existe, mas em outro convênio

Usuário reportou: buscou "Adrian Lucca Medeiros" no modal de seleção de paciente (Nova
Solicitação) e não achou, embora o cadastro existisse. Investigação (log de acesso do nginx +
reprodução via tinker contra a query real) confirmou a causa: a busca é escopada pelo convênio
selecionado na solicitação, e a busca real foi feita com `convenio_id=4` (Particular) enquanto o
paciente está cadastrado em `convenio_id=1` (Unimed) — comportamento correto (a solicitação usa o
convênio do próprio paciente), mas a tela não avisava, e "nenhum paciente encontrado" ficava
ambíguo com "cadastre um novo", risco de duplicar cadastro.

Correção: quando a busca não acha ninguém no convênio atual, `SelecionarPacienteModal` refaz a
busca sem esse filtro; se achar o nome em outro convênio, mostra um aviso (nome + convênio real)
antes do bloco de "cadastrar novo".

Commit: `0ca5d6b`.

## Verificação — como cada entrega foi validada

- Backend: `run-tests.sh` (346/347 verdes; a 1 falha, `UsuariosApiTest`, é pré-existente e não
  relacionada — confirmado rodando a mesma suíte com `git stash` antes de qualquer mudança desta
  sessão).
- Frontend: `tsc -b` e `oxlint` limpos antes de cada deploy (rodados via `docker run` com a imagem
  `node:22-alpine`, montando `web/`).
- Migração do item 1: backup real de produção (`mariadb-dump`) antes de qualquer escrita; migração
  primeiro testada contra uma **restauração** desse backup num container MariaDB descartável
  (nunca no banco real), com conferência de contagens (33/33, zero órfãos) e uma linha específica
  comparada byte a byte contra o dump original; só depois disso aplicada no `gescon-db` real, com
  as mesmas conferências pós-migração batendo exatamente.
- Deploy: `deploy/redeploy.sh` a cada entrega, com smoke test do próprio script (HTTP 200 + hash do
  bundle servido == bundle buildado) — 4 deploys nesta sessão (itens 1–4).
- Item 4: causa raiz confirmada com evidência de log real (`nginx access.log`) e reprodução exata
  da query via `artisan tinker`, não só inspeção de código.

## Pendente

- Verificação visual ao vivo da pasta do paciente (item 1) e do fluxo de anexos na criação (item 3)
  não foi feita em navegador de verdade nesta sessão — só smoke test HTTP + testes automatizados.
  Usuário confirmou "aparentemente deu certo" após o primeiro deploy, mas vale um teste manual
  completo (upload solto, vincular a duas solicitações, tentar excluir arquivo travado).
- Suíte E2E (`web/tests/e2e/mvp-flow.spec.ts`) foi **atualizada** pro novo fluxo do item 3, mas
  **não executada** nesta sessão: o ambiente não tem `api/.env.testing`, e `php artisan
  migrate:fresh --seed --env=testing` sem esse arquivo cairia no `.env` de produção — risco real
  de derrubar as tabelas reais. Precisa de um `.env.testing` próprio antes de rodar o e2e aqui.
- Item 4 cobre só o modal de seleção de paciente do cadastro manual (`SelecionarPacienteModal`); o
  `<select>` de paciente do assistente "Ler pedido médico" (`LerPedidoMedicoPage`) já lista todos
  os pacientes do convênio sem paginação, então o mesmo tipo de "sumiço" não deveria acontecer lá,
  mas não ganhou o mesmo aviso explícito de "existe em outro convênio".
