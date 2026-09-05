# Entregas de 05/09/2026 — Pasta do paciente, anexos na criação da solicitação, aviso de convênio, sincronização com o clinica e unificação de pacientes duplicados

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

## 5. Sincronização com o clinica: pendência de vínculo por similaridade, evita duplicar paciente

Pedido do usuário: pacientes que os operadores cadastram no clinica muitas vezes já existem no
Gescon (ex: "Abner dos Santos Beiger" em #518 e #30) — quis que a sincronização verifique isso
antes de criar um cadastro novo, atualize só o que faltar (nunca substituindo o número de
carteirinha por um placeholder tipo `SYNC-CLINICA-102`), e sinalizasse duplicados já existentes.

`PacienteSyncService::pullUm()` só reconhecia paciente já existente por `clinica_id` ou CPF
exatos; sem nenhum dos dois, criava um `Paciente` novo com convênio "Particular" e carteirinha
`SYNC-CLINICA-{id}` — foi assim que o Abner ganhou um cadastro extra. Agora, antes de criar,
`buscarCandidatos()` roda `similar_text()` (mesmo padrão de `PedidoMedicoAiService`/
`CarteirinhaAiService`, corte de 90% de confiança) contra pacientes locais sem vínculo; achando
candidato, abre uma pendência (`clinica_pacientes_pendentes`) em vez de decidir sozinho.

Tela "Configurações > Sincronização com o clinica" ganhou a seção **Pendências de vinculação**:
mostra o candidato sugerido com % de similaridade, um clique confirma o vínculo ou diz "não é a
mesma pessoa" (cria o cadastro novo normalmente). Ao confirmar (`ClinicaPacientePendenteService::confirmar`),
só preenche CPF/nascimento se estiverem vazios no Gescon; telefone é atualizado mesmo se já
preenchido (é contato); **carteirinha nunca é tocada**. Rejeitar deixa a próxima sincronização
criar o paciente normalmente (com o placeholder), vinculando pelo `clinica_id` a partir daí.

Endpoints novos: `GET/POST /configuracoes/clinica-sync/pendencias/{id}/confirmar|rejeitar`, mesma
permissão `configuracoes.clinica.manage`. Testes: `PacienteSyncServiceTest` (8 casos — match exato
não gera pendência, nome parecido sem CPF gera pendência em vez de duplicar, nome diferente cria
normal, pendência não duplica em nova rodada, confirmar preenche só vazios e nunca sobrescreve
carteirinha, rejeitar libera criação) + `ClinicaSyncPendenciasApiTest` (rotas).

Commit: `0350016`.

## 6. Relatório de pacientes duplicados já existentes na base

Complemento do item 5, mas cobrindo duplicados que já existiam **antes** da sincronização (não só
os que a sincronização evitaria dali pra frente) — pedido do usuário: "verifique todos nomes
duplicados". `PacienteDuplicadoService::buscar()` compara todos os pacientes do tenant por
similaridade de nome (mesmo corte de 90%), devolvendo pares para revisão manual.

Bug encontrado testando contra a base real de produção: o serviço filtrava `ativo=true`, e o caso
que motivou o relatório (#518, o duplicado do Abner) já estava desativado sem o histórico ter sido
migrado — o filtro escondia exatamente o par que deveria aparecer. Corrigido para nunca filtrar por
`ativo`, mostrando o status de cada lado na UI para dar contexto. Validado direto em produção via
`artisan tinker`: o par real #30/#518 passou a aparecer com 100% de similaridade depois da correção.

Commit: `50786d1` (endpoint inicial já ia no `0350016`, exposto em Configurações — depois movido
para Pacientes, ver item 7).

## 7. Unificar pacientes duplicados — botão "Buscar Duplicados" em Pacientes

Usuário pediu, sobre o relatório do item 6: um jeito de efetivamente unir os cadastros, e perguntou
como ficam solicitações/guias/sessões de quem for absorvido. Investigação de schema revelou que
`paciente_id` está **denormalizado** em `solicitacoes`, `guias` e `antecipacoes` de forma
independente (não herda via `solicitacao_id`/`guia_id`) — unificar exige repontar as três tabelas
separadamente, mais `paciente_telefones`, `paciente_documentos`, `paciente_arquivos`,
`paciente_import_linhas.matched_paciente_id` e `clinica_pacientes_pendentes.candidato_paciente_id`.
`lancamentos` (sessões) não tem `paciente_id` próprio — pendura em `antecipacao_id` e segue sozinho
quando a antecipação é repontada.

Decisões (confirmadas com o usuário antes de implementar): o "perdedor" nunca é apagado de verdade
(o app não tem soft-delete em lugar nenhum) — fica `ativo=false` e ganha `mesclado_em_id`/
`mesclado_em`, apontando pro vencedor, mesmo padrão que "inativo" já usa pra não perder histórico;
o vencedor de cada par é **sempre escolha manual**, mesmo numa unificação em lote; se os dois lados
tiverem `clinica_id` diferente e não-nulo, a UI pede pra escolher qual vínculo manter antes de
liberar o par.

`PacienteMergeService::mesclar()` reponta as tabelas via Eloquent (`->get()->each(fn($m) =>
$m->update(...))`, não `DB::table()->update()` cru) para manter o `Auditable` disparando por linha;
preenche no vencedor só os campos vazios (CPF, nascimento; telefone sempre atualiza, é contato);
**carteirinha nunca é sobrescrita**. `PacienteDuplicadoService` e
`PacienteSyncService::buscarCandidatos()` passaram a excluir pacientes já mesclados
(`whereNull('mesclado_em_id')`), pra um par resolvido não voltar a aparecer nem virar candidato de
sync de novo.

UI: botão **Buscar Duplicados** na tela de Pacientes (ao lado dos indicadores Total/Ativos/
Inativos) — a seção antiga em Configurações (só relatório) foi removida pra não ter duas telas
fazendo a mesma coisa. Cada par mostra dois chips "Manter #X nome" pra escolher o vencedor
(reaproveitando o padrão visual de sugestão de `LerPedidoMedicoPage`); checkbox por par (só
habilita depois de escolher vencedor) + "Selecionar todos" + "Unificar selecionados", que mostra
quantas solicitações/guias/antecipações serão movidas antes de confirmar
(`useConfirm`/`ConfirmDialog`).

Endpoints novos (`PacienteMergeController`, permissão `dashboard.pacientes`, mesma da mutação de
Pacientes): `GET /pacientes/duplicados`, `POST /pacientes/duplicados/preview`,
`POST /pacientes/duplicados/mesclar`. Testes: `PacienteMergeServiceTest` (12 casos — repontagem das
3 tabelas denormalizadas + telefones/documentos/arquivos, perdedor desativado e marcado, carteirinha
nunca sobrescrita, campos vazios preenchidos, conflito de `clinica_id` com/sem escolha, não deixa
mesclar duas vezes) + `PacientesDuplicadosApiTest` (rotas, inclui inativos, exclui par já mesclado).

Validado em produção via `artisan tinker` (só leitura): `preview()` do par real #30/#518 confirma
zero solicitações/guias/antecipações penduradas no perdedor — unificação desse caso específico é
segura e rápida. A unificação de verdade desse par **não foi executada** — fica disponível pro
usuário rodar pela tela quando quiser.

Commit: `cd9838d`.

## 8. Ajustes de UX na tela de duplicados

Dois ajustes pedidos pelo usuário depois de ver o fluxo do item 7: (a) com a seção de duplicados
aberta, a lista/filtros normais de Pacientes somem — evita rolar duas telas grandes ao mesmo tempo;
(b) ao clicar "Unificar selecionados", depois da confirmação, abre um modal listando cada par com
status ao vivo (aguardando → unificando → unificado/falhou) conforme processa em sequência, em vez
do botão só ficar "carregando" sem detalhe do que está rodando.

Commit: `506e4c1`.

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
- Itens 5–8: mesma suíte (`run-tests.sh`, agora 376/377 verdes — a mesma falha pré-existente de
  `UsuariosApiTest`) e `tsc -b`/`oxlint`/`verificar-design-system.mjs` limpos antes de cada um dos 4
  deploys (rodados via `docker run` com `node:20-alpine`). Migração `mesclado_em_id`/`mesclado_em`
  é aditiva (coluna nova nullable) — sem risco de dado existente, não precisou do processo de backup
  + restauração do item 1. Os pares reais #30/#518 (Abner) e a correção do item 6 foram conferidos
  direto em produção via `artisan tinker` (leitura), não só em teste automatizado.

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
- Itens 5–8: nenhuma verificação visual em navegador de verdade — só testes automatizados + smoke
  test HTTP + leituras via `tinker`. As telas "Pendências de vinculação" (item 5), "Buscar
  Duplicados"/unificar (itens 7–8) e o novo modal de progresso nunca foram clicados de verdade.
  Vale testar manualmente: confirmar/rejeitar uma pendência de sync pela tela, e rodar uma
  unificação real (o par #30/#518 do Abner está pronto e sem risco — `preview()` confirma zero
  solicitações/guias/antecipações no perdedor — mas ainda não foi executado, fica por conta do
  usuário decidir quando).
- `docs/clinica-sync.md`, citado no docblock de `PacienteSyncService` desde 20/08/2026 como a fonte
  do design da sincronização, continua **não existindo** no repositório (confirmado nesta sessão) —
  não foi criado agora por estar fora do escopo pedido, mas seria o lugar certo para consolidar o
  desenho completo (pull/push, anti-loop, e agora pendências de vínculo) em vez de só comentários
  espalhados pelo código.
