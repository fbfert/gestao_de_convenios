# Entregas de 09/09/2026 — "Adicionar sessões" numa solicitação existente

Change do openspec: `solicitacao-adicionar-sessoes`. O brief que originou o trabalho está em
`docs/brief-adicionar-sessoes-na-solicitacao.md`.

## O problema

A Unimed libera 10 sessões por guia. Paciente que precisa de 20 no mês exige duas guias para a mesma
especialidade e o mesmo profissional, sob o **mesmo pedido médico**. Não havia como acrescentar nada
a uma solicitação criada: `SolicitacaoService::atualizar()` mexe em médico, CIDs, data e observações,
e não toca em `itens`. A saída da atendente era abrir outra solicitação do zero, duplicando paciente,
médico, CID e anexos.

Dois defeitos tornariam a feature inútil no nascimento, e por isso entraram no mesmo change.

## 1. Convênio manual gerava guia só para o primeiro item

`sincronizarGuiaDaSolicitacao()` fazia `itens()->orderBy('id')->first()`. Desde a
multi-especialidade os demais itens ficavam sem guia nenhuma, e a solicitação seguia parecendo
processada — defeito silencioso. Passou a percorrer todos os itens sem guia.

O caminho que **atualizava** a guia encontrada saiu junto, e isso foi decisão: ele a devolvia para
`under_review`, e repeti-lo dentro de um laço faria uma re-sincronização resetar guias já aprovadas
dos outros itens. Agora a operação só cria o que falta, e por isso é segura de repetir.

O valor de preenchimento de `numero_guia` virou **por item** (`GUIA-SOLICITACAO-{sol}-{item}`). Sem
isso, as N guias nasceriam com o mesmo "número": o índice `(convenio_id, numero_guia)` **não é
unique**, então nada estouraria — só qualquer busca por número passaria a devolver uma guia
arbitrária entre as N. Trocaria um defeito visível por um invisível.

A conferência em produção (cópia local do dump de 08/09) voltou **vazia**, e pelo motivo certo: as
2.278 solicitações são todas da Unimed, e os quatro convênios manuais têm zero solicitações. O
conserto é só para frente — chega **antes** da primeira solicitação manual existir.

## 2. A solicitação aprovada não deixava enviar item novo

`sincronizarStatusComGuias()` saía cedo quando o status não era `ready_for_automation` ou
`guia_gerada`, e o gate de envio olhava o status da **solicitação** em vez do item. O fluxo real é
pediu 10 → aprovou → quer mais 10.

A sincronização passou a **refletir** os itens, podendo regredir de `approved`/`guia_gerada` para
`ready_for_automation`. `under_review`, `denied` e `historico` continuam intocados: são decisão
humana registrada ou rastro de migração.

Ela continua **pura de status**, e tem que continuar: grava com `update()` direto e não chama
`alterarStatus()` nem `sincronizarGuiaDaSolicitacao()`. O motivo é recursão —
`alterarStatus('ready_for_automation')` dispara criação de guias, que mexe em status de guia, que
chamaria a sincronização de novo. A criação em convênio manual é disparada **explicitamente** por
quem acrescenta o item.

**Correção ao brief:** ele partia de "o botão some mas a rota continua aceitando".
`GerarGuiaUnimedService::avaliar()` já recusava, e mais estritamente que o front. Não havia buraco de
segurança; o trabalho foi **afrouxar dos dois lados a checagem que existia**. Afrouxar só o front
deixaria o botão habilitado e a API recusando — pior que o estado anterior.

`App\Support\SolicitacaoStatus` nasceu para a regra existir num lugar só: `BLOQUEIAM_ENVIO`,
`BLOQUEIAM_ADICAO` e `DERIVADAS_DOS_ITENS`. As duas primeiras são deliberadamente diferentes —
`under_review` barra o envio e **permite** acrescentar.

## 3. Vínculo, endpoint e quantidade

`solicitacao_itens.renovacao_de_item_id` aponta sempre para a **origem** da cadeia, nunca para o item
anterior. Com a cadeia plana, somar o ciclo é uma query; em árvore, seria recursão. O servidor
normaliza: mandar o id de um item que já é renovação grava a origem dele.

`POST /solicitacoes/{solicitacao}/itens`. Repetição de especialidade e profissional é **permitida**,
sem unique e sem validação de duplicata — é o caso de uso principal.

`GET /solicitacoes/{solicitacao}/contexto-adicao` devolve os quatro dados dos avisos calculados no
servidor. Regra de convênio reimplementada em TypeScript diverge do backend em seis meses.

## 4. O campo certo, e por que não foi o que o brief pediu

O brief mandava usar `convenio_regras.qtd_autorizada_por_ciclo`. **Está errado**, e o erro foi
corrigido no brief. O campo é uma **taxa**, emparelhada com `frequencia_lancamento`:

- `AntecipacaoService.php:107-114`: *"descreve o ritmo de liberacao, ex.: '1 por dia', nao o total da
  guia"*.
- O `ConvenioRegraSeeder` grava, nas próprias observações: `qtd = 1` com `frequencia = diaria` é
  "1 sessão por dia"; `qtd = 2` é "duas autorizações por dia".

Usá-lo como quantidade padrão poria **1** onde a Unimed libera 10 — um número errado configurável,
pior que um número errado no código porque parece decidido de propósito.

Entrou `convenio_regras.sessoes_por_guia`, nullable, ao lado do antigo. Nulo significa "não sabemos",
e não zero: sem valor, a quantidade vem vazia e quem preenche é a pessoa. **A API recusa em vez de
arbitrar** — e a mensagem do 422 nomeia o convênio e a tela onde se resolve de uma vez, em vez de
mandar contornar o problema uma solicitação por vez.

Isso mudou o contrato de criação de solicitação para convênio sem a regra cadastrada.

**Migração de dados incluída.** O seeder só alcança base nova; em produção a regra vigente da Unimed
existe com o campo nulo, e sem preenchê-la o deploy levaria 422 para a atendente no primeiro uso. A
migração preenche `10` apenas nas regras **vigentes** de convênios `unimed_rda` ainda nulas —
convênio sem automação continua nulo de propósito. Deploy autocontido, sem passo de runbook.

## 5. O `10` espalhado pelo código

A varredura achou **quatro** sítios, um a mais que o previsto:

- `SolicitacaoService` — dois `?? 10`.
- `SolicitacaoImportService:571` — `?: 10` no parse. O pior dos quatro: célula em branco virava dez
  em silêncio, e a planilha entrava com um valor que a API já recusava. Agora a linha é **inválida**,
  com a mensagem nomeando o convênio; o preview já sabia mostrar linha inválida.
- `SolicitacaoImportService:74` — `'10'` no exemplo do modelo baixável. Ficou vazio, e o cabeçalho
  passou a dizer "Quantidade (vazio = sessões por guia do convênio)".
- `solicitacaoItens.ts` e `SolicitacaoItensFields.tsx` — `'10'` no item em branco e na linha nova.

Em vez de deixar tudo vazio, `sessoes_por_guia` passou a sair no `ConvenioResource` e o formulário
**pré-preenche a partir da regra**, só o que está vazio. `LerPedidoMedicoPage` mostrava
`item.quantidade || 10` numa tabela de conferência: a pessoa lia dez e levava, ao salvar, um erro
dizendo que faltou preencher. Agora mostra "a preencher".

## 6. Três defeitos que os testes pegaram antes do deploy

**A entrada `+` ao lado do menu quebrava a coluna Ações.** A tabela é `table-fixed` com as larguras
somando 100%; o segundo botão transbordava a célula e o ícone da coluna Info passava a interceptar o
clique. Foi para dentro do menu.

**Tirar o `'10'` travou o cadastro de solicitação.** `itensEstaoCompletos` exige `quantidade !== ''`,
então o botão de criar nunca habilitava. Derrubou dois cenários de `mvp-flow` em três execuções
seguidas — e quase foi creditado à instabilidade conhecida da suíte. O que desfez foi o
pré-preenchimento pela regra; a suíte caiu de 4,5 min para 1,3 min, provando que era o formulário
travado e não carga.

**O teste novo sujava o banco compartilhado.** Ele liga `unimed_rda` na Unimed (a semente não traz
convênio com automação) e não desfazia; `mvp-flow` roda depois e cria guia à mão nesse convênio,
recebendo 422. Ganhou um `afterEach` que restaura, e que roda mesmo após falha.

## Dívidas registradas

Estão no `proposal.md` do change, em resumo: `TIPO_TERAPIA_PADRAO` fixo em `especializada` (convênio
com regra `convencional` de teto diferente lê a errada em silêncio); as duas listas de status
duplicadas em `types.ts` (comentário não é trava); e `qtd_autorizada_por_ciclo` seguindo sem
consumidor além do fallback da antecipação.

Fora do escopo deste change, mas achado pela varredura e digno de um próprio:
`ConvenioEspecialidadeMapeamentoController:32,47` tem `quantidade_padrao ?: 10` — mesma família,
campo diferente —, com dois espelhos no front (`ConfiguracoesPage.tsx:50`,
`useUnimedSettings.ts:184`).

## 7. Modal não fecha mais por clique fora (ADR-28)

Fora do change, e pedido no mesmo dia. Todo `Dialog` do Headless UI fechava ao clique fora do
painel — e nossos modais não são visualizadores: editam solicitação, montam item novo, conduzem seis
etapas de leitura do pedido médico, têm busca digitada. Um clique fora enquanto se preenche
descartava tudo, sem pergunta e sem desfazer, e é fácil de dar sem querer ao mirar um campo e errar
a borda.

Esc continua fechando, de propósito: ninguém aperta Esc por acidente.

O Headless UI (v2.2) chama `onClose` nos dois casos e não distingue um do outro nem oferece prop
para desligar só um. `web/src/lib/useFechamentoExplicito.ts` neutraliza o `onClose` do componente e
reimplementa o Esc; os oito diálogos espalham o retorno do hook, o que deixa a decisão visível em
cada ponto de uso.

O hook mantém uma **pilha de modais abertos** por causa do aninhamento — `SelecionarMedicoModal`
abre dentro do `SolicitacaoGuiaModal`, e sem a pilha um Esc fecharia os dois de uma vez.

`ConfirmDialog` e `ConfirmarExclusao` não precisaram de nada: são feitos à mão, o fundo nunca teve
`onClick` de fechar, e o segundo já tratava Esc sozinho.

## Validação

| Comando | Resultado |
|---|---|
| `php artisan test` | **528 de 528** |
| `npm run lint` | contrato do design system OK |
| `npm run build` | compila (`tsc -b` limpo) |
| `npm run test:e2e` | **18 de 18** |
| `openspec validate solicitacao-adicionar-sessoes --strict` | válido |
