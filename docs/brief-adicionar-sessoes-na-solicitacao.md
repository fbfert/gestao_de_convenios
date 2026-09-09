# Brief de implementação — "Adicionar sessões" numa solicitação existente

Repo: `fbfert/gestao_de_convenios` · Change do openspec: **`solicitacao-adicionar-sessoes`**
Corre em paralelo ao plano de Saúde/Alertas e ao change `solicitacao-guias-por-item-e-info`.

> **Como usar:** salve como `docs/brief-adicionar-sessoes-na-solicitacao.md` no repo e rode os
> prompts da seção 8 no Claude Code, um por vez. Eles referenciam este caminho, então você não
> precisa colar o conteúdo.

---

## 1. O problema real

A Unimed libera **10 sessões por guia**. Paciente que precisa de 20 no mês exige duas guias para a mesma especialidade e o mesmo profissional — pedidas em momentos diferentes, sob o **mesmo pedido médico**.

Hoje não há como acrescentar nada a uma solicitação já criada: `SolicitacaoService::atualizar()` mexe em médico, CIDs, data e observações, e não toca em `itens`. A única saída da atendente é criar outra solicitação do zero, duplicando paciente, médico, CID e anexos.

Este change cria a ação **"Adicionar sessões"** — e conserta, no caminho, dois defeitos que a bloqueariam.

---

## 2. Diagnóstico — verificado no código em 08/09/2026

Confirme cada item antes de implementar. Se algum não bater, **pare e avise**.

### 2.1 A solicitação aprovada não deixa enviar item novo

```php
// api/app/Services/SolicitacaoService.php:271
public function sincronizarStatusComGuias(Solicitacao $solicitacao): void
{
    if (! in_array($solicitacao->status, ['ready_for_automation', 'guia_gerada'], true)) {
        return;   // <- solicitação 'approved' sai por aqui
    }
```

```tsx
// web/src/features/solicitacoes/SolicitacoesPage.tsx:~832
const canSend = isUnimedRda
  && solicitacao.status === 'ready_for_automation'   // <- gate pela solicitação inteira
  && !item.guia_id
  && !hasActiveExecution
```

O fluxo do caso principal é: pediu 10 → aprovou (`approved`) → quer mais 10. O item novo entra numa solicitação `approved`, o sync retorna cedo, o status não muda, e **"Enviar para Unimed" nunca habilita**. Sem consertar isso, a feature nasce inútil.

O envio sempre foi por item (`POST /solicitacao-itens/{item}/enviar-unimed`) — só o gate é que olha a solicitação.

### 2.2 Convênio manual só gera guia para o primeiro item

```php
// api/app/Services/SolicitacaoService.php:294
private function sincronizarGuiaDaSolicitacao(Solicitacao $solicitacao): void
{
    if ($solicitacao->convenio?->connector_driver === 'unimed_rda') { return; }

    $item = $solicitacao->itens()->orderBy('id')->first();   // <- só o primeiro
```

Isto já é bug hoje (multi-especialidade em convênio manual gera uma guia só). Com esta feature ele fica evidente: adicionar item em convênio manual não geraria guia nenhuma.

### 2.3 O placeholder de número colide quando vira laço

```php
// api/app/Services/SolicitacaoService.php:335
private function numeroGuiaDaSolicitacao(Solicitacao $solicitacao): string
{
    return 'GUIA-SOLICITACAO-'.$solicitacao->id;   // por SOLICITAÇÃO, não por item
}
```

Transformando §2.2 num laço sem tocar aqui, as N guias da mesma solicitação nascem todas com `GUIA-SOLICITACAO-12`. O índice `(convenio_id, numero_guia)` **não é unique** — não estoura, só cria silenciosamente N guias com o mesmo "número", e qualquer busca por número devolve a errada. Troca um bug visível por um invisível.

O placeholder tem que passar a ser por item.

### 2.4 A quantidade padrão 10 está no código, não na regra

```php
// api/app/Services/SolicitacaoService.php:141
'quantidade' => $item['quantidade'] ?? 10,
```

Dez é exatamente o limite da Unimed — uma regra de convênio hardcoded em Service, contra a regra de ouro do projeto ("regras de convênio são dado configurável, nunca código", `README.md` e `openspec/config.yaml`).

### 2.5 ~~`qtd_autorizada_por_ciclo` já existe e está órfão~~ — CORRIGIDO EM 09/09/2026

> **Esta constatação estava errada, e o erro foi corrigido no commit 3.**
>
> `qtd_autorizada_por_ciclo` **não** é "quantas sessões o convênio libera por vez". É uma **taxa de lançamento**, emparelhada com `frequencia_lancamento`. Duas evidências independentes:
>
> - `AntecipacaoService.php:107-114`: *"qtd_autorizada_por_ciclo do convenio (que descreve o ritmo de liberacao, ex.: '1 por dia', nao o total da guia)"*.
> - As observações que o próprio `ConvenioRegraSeeder` grava: `qtd = 1` com `frequencia = diaria` vira *"liberação diária e 1 sessão por dia"*; `qtd = 2` vira *"duas autorizações por dia"*.
>
> Usá-lo como quantidade padrão poria **1** onde a Unimed libera 10 — um número errado configurável, pior que um número errado no código porque parece decidido de propósito.
>
> **O campo certo é `convenio_regras.sessoes_por_guia`**, criado no commit 3: nullable, ao lado do antigo, sem tocá-lo. Nulo significa "não sabemos", e não zero.
>
> `qtd_autorizada_por_ciclo` segue com um único consumidor — `AntecipacaoService:115` — e continua sendo dele. Leia `sessoes_por_guia` onde este brief disser `qtd_autorizada_por_ciclo`.

---

## 3. Decisões já tomadas (não reabrir)

| Tema | Decisão |
|---|---|
| Vínculo | `solicitacao_itens.renovacao_de_item_id` — o item novo aponta para o de origem |
| Envio | `canSend` passa a ser por item; deixa de olhar o status da solicitação |
| Status | `sincronizarStatusComGuias` passa a **refletir** os itens, podendo regredir para `ready_for_automation` quando surge item sem guia |
| Convênio manual | corrigir junto, no primeiro commit |
| Ação | ícone `+` na coluna Ações da listagem **e** botão no modal de detalhes |
| Rótulo | "Adicionar sessões" |
| Modal | dois caminhos: repetir especialidade já pedida (pré-preenche) ou adicionar especialidade nova |
| Repetição | avisa e **permite** — é o caso de uso principal, nunca bloqueia |
| Avisos | os quatro da §4 R8, todos não bloqueantes |

---

## 4. Requisitos

### R1 — Guia por item em convênio manual *(commit 1)*

- `sincronizarGuiaDaSolicitacao` passa a percorrer **todos** os itens sem guia, criando uma guia para cada, em vez de só o primeiro.
- O placeholder de `numero_guia` passa a ser **por item** (ex.: `GUIA-SOLICITACAO-{solicitacao_id}-{item_id}`).
- Extraia o prefixo `GUIA-SOLICITACAO-` para uma **constante compartilhada**. O change `solicitacao-guias-por-item-e-info` também precisa dela para não exibir o placeholder como número de guia — **procure se ela já existe antes de criar**.
- Itens que já têm guia não são tocados.
- Antes de mexer, rode em produção e me traga o resultado:

```sql
SELECT s.id, s.convenio_id, COUNT(DISTINCT i.id) itens, COUNT(DISTINCT g.id) guias
FROM solicitacoes s
JOIN solicitacao_itens i ON i.solicitacao_id = s.id
LEFT JOIN guias g ON g.solicitacao_item_id = i.id
JOIN convenios c ON c.id = s.convenio_id
WHERE c.connector_driver IS NULL OR c.connector_driver <> 'unimed_rda'
GROUP BY s.id, s.convenio_id
HAVING itens > guias;
```

Se vier vazio (esperado — a NeuroKids opera só Unimed), o conserto é só para frente e não há backfill a decidir.

### R2 — Gate de envio por item *(commit 2)*

- `canSend` deixa de exigir `solicitacao.status === 'ready_for_automation'`.
- Passa a ser: convênio com automação **e** item sem guia **e** sem execução ativa **e** solicitação não está em `under_review`, `denied` nem `historico`.
- Espelhe a mesma regra no backend (`SolicitacaoController::enviarItemUnimed` / o service correspondente): o botão some, mas a rota continua exposta.

### R3 — Status reflete os itens *(commit 2)*

`sincronizarStatusComGuias` passa a poder **regredir**:

- há item sem guia e a solicitação está em `guia_gerada` ou `approved` → volta para `ready_for_automation`
- todos os itens têm guia, nem todas aprovadas → `guia_gerada`
- todos aprovados/finalizados → `approved`
- `under_review`, `denied` e `historico` continuam intocados — nunca pular análise nem reabrir negada

**Efeito colateral a documentar no design.md:** a transição para `ready_for_automation` passa a acontecer muito mais vezes, e é ela que dispara `sincronizarGuiaDaSolicitacao`. Em convênio manual, uma solicitação antiga que receba item novo vai gerar de uma vez as guias que faltavam nos itens antigos. Provavelmente é o comportamento certo — mas tem que ser decisão explícita, não surpresa em produção.

### R4 — Coluna de vínculo *(commit 3)*

- `solicitacao_itens.renovacao_de_item_id`, nullable, FK para `solicitacao_itens.id`.
- Preenchida quando a atendente usa o caminho "repetir especialidade já pedida"; nula quando adiciona especialidade nova.
- Aponta sempre para o item **de origem da cadeia** (o primeiro), não para o anterior — assim somar o ciclo é uma query, não uma recursão.

### R5 — Endpoint de adição *(commit 3)*

`POST /solicitacoes/{solicitacao}/itens`, permissão `solicitacoes.manage`.

- Corpo: `especialidade_id`, `profissional_id`, `quantidade`, `observacoes?`, `renovacao_de_item_id?`.
- Validação no padrão do `StoreSolicitacaoRequest` (existência escopada por `tenant_id`).
- Recusa em solicitação `denied` ou `historico`. Aceita em `under_review`, `ready_for_automation`, `guia_gerada` e `approved`.
- Repetição de especialidade/profissional **é permitida** — não crie unique nem validação de duplicata.
- `renovacao_de_item_id`, quando vier, tem que ser item da **mesma** solicitação.
- Após criar, chama `sincronizarStatusComGuias` (R3).

### R6 — Quantidade padrão vem da regra *(commit 3)*

- O `?? 10` de `SolicitacaoService:141` sai do código. O padrão passa a vir de `convenio_regras.sessoes_por_guia` da regra **vigente** do convênio (respeitando `vigente_desde`/`vigente_ate`).
- Sem regra vigente cadastrada, o campo vem vazio e a atendente digita — **não** invente um número.
- Vale para o endpoint novo e para a criação de solicitação.

### R7 — Entradas na interface *(commit 4)*

- Ícone `+` na coluna **Ações** da listagem (`SolicitacoesPage`), dentro do `DropdownMenu` já existente ou ao lado dele — mantenha o padrão da tela.
- Botão "Adicionar sessões" no cabeçalho do modal de detalhes (`SolicitacaoGuiaModal`).
- Ambos abrem o mesmo modal e só aparecem com `solicitacoes.manage` e com a solicitação em status que aceita (R5).

### R8 — Modal "Adicionar sessões" *(commit 4)*

Dois caminhos, escolhidos logo na abertura:

**A. Repetir especialidade já pedida** — lista os itens atuais da solicitação; escolher um pré-preenche especialidade, profissional e quantidade, e grava `renovacao_de_item_id` apontando para a origem da cadeia.

**B. Adicionar especialidade nova** — formulário em branco, no padrão do `SolicitacaoItensFields`.

Quatro avisos, **todos não bloqueantes**, exibidos antes de confirmar:

| Aviso | Regra | Texto |
|---|---|---|
| Repetição | já existe item com a mesma especialidade **e** profissional | "Já existe Fonoaudiologia com Ana Paula nesta solicitação." |
| Pedido médico | idade de `solicitado_em` em dias | "Pedido médico de 12/06 — 88 dias atrás." |
| Limite do ciclo | `sessoes_por_guia` da regra vigente | "Este convênio libera 10 sessões por guia." |
| Já pedido no ciclo | soma de `quantidade` dos itens da mesma cadeia de `renovacao_de_item_id` | "Já há 10 sessões de Fonoaudiologia nesta solicitação." |

- Nenhum aviso desabilita o botão de confirmar. Bloquear repetição bloquearia o caso de uso principal.
- Avisos que não se aplicam simplesmente não aparecem (sem regra vigente → sem aviso de limite).
- O quarto aviso **só é exato por causa do R4**: sem o vínculo, somar itens que coincidem em especialidade e profissional contaria junto duas terapias legitimamente separadas.

### R9 — Exibir a cadeia *(commit 4)*

Na coluna Itens da listagem e nas abas do modal, itens de renovação aparecem identificados como continuação (ex.: "Fonoaudiologia · 2ª remessa"), calculado pela posição na cadeia. Sem isso, a tela mostra duas linhas iguais e ninguém entende por quê.

---

## 5. Não-objetivos

Registre no `proposal.md`, não implemente:

- **Modelar quantidade pedida + parcelas** (item guarda 20, guias somam 10+10). Foi avaliado e adiado: mexeria no modelo, na automação e na conciliação. A coluna de vínculo do R4 é o degrau barato que mantém essa porta aberta.
- **Alerta automático de saldo baixo.** Consome o vínculo do R4, mas pertence ao change `central-de-alertas` (ver `docs/plano-alertas-saude-e-novidades.md`).
- **Editar ou remover item existente.** Este change só adiciona.
- **Mexer em antecipação e conciliação.** N guias onde antes havia 1 muda o que a antecipação enxerga — merece atenção própria, com dado real na mão.
- **Recalcular status de solicitações antigas em massa.** O R3 vale a partir da próxima transição de cada solicitação.

---

## 6. Critérios de aceite

- [ ] Convênio manual com 3 itens gera **3** guias, cada uma com placeholder distinto
- [ ] Nenhuma guia nova nasce com `numero_guia` igual à de outra guia da mesma solicitação
- [ ] Solicitação `approved` que recebe item novo volta a `ready_for_automation` e o item novo pode ser enviado
- [ ] "Enviar para Unimed" continua desabilitado em solicitação `under_review`, `denied` e `historico` — e a **rota** também recusa, não só o botão
- [ ] Item criado pelo caminho "repetir" grava `renovacao_de_item_id` apontando para a origem da cadeia
- [ ] Item criado pelo caminho "especialidade nova" grava `renovacao_de_item_id` nulo
- [ ] Adicionar especialidade + profissional repetidos **funciona**, com aviso
- [ ] Os quatro avisos aparecem quando cabem e somem quando não cabem
- [ ] Convênio sem regra vigente: quantidade vem vazia, sem aviso de limite, sem número inventado
- [ ] `?? 10` não existe mais em `SolicitacaoService`
- [ ] Item de renovação aparece identificado como continuação na listagem e no modal
- [ ] `php artisan test`, `npm run lint` e `npm run test:e2e` passam

---

## 7. Arquivos envolvidos

**Backend**
```
api/app/Services/SolicitacaoService.php          R1, R3, R6 — sincronizarGuiaDaSolicitacao,
                                                 numeroGuiaDaSolicitacao, sincronizarStatusComGuias, criar
api/app/Http/Controllers/SolicitacaoController.php   R2, R5 — rota nova e gate no envio
api/app/Http/Requests/                           StoreSolicitacaoItemRequest (novo)
api/app/Models/SolicitacaoItem.php               R4 — fillable + relações renovacaoDe / renovacoes
api/app/Http/Resources/SolicitacaoResource.php   R4, R9 — expor renovacao_de_item_id e posição na cadeia
api/routes/api.php                               R5
api/database/migrations/                         R4
api/app/Services/ConvenioRegraService.php        R6 — buscar a regra vigente (só leitura)
```

**Frontend**
```
web/src/features/solicitacoes/SolicitacoesPage.tsx           R2, R7, R9
web/src/features/solicitacoes/SolicitacaoGuiaModal.tsx       R7
web/src/features/solicitacoes/AdicionarSessoesModal.tsx      R8 (novo)
web/src/features/solicitacoes/SolicitacaoItensFields.tsx     R8 — reaproveitar no caminho B
web/src/features/solicitacoes/useSolicitacoes.ts             R5 — mutation
web/src/features/solicitacoes/types.ts                       R4, R9
```

---

## 8. Prompts para o Claude Code

Um por vez. Revise a spec antes de mandar implementar.

### Prompt 1 — Spec

```
Leia docs/brief-adicionar-sessoes-na-solicitacao.md, AGENTS.md e openspec/config.yaml.
Leia também openspec/changes/solicitacoes-multi-especialidade-anexos/ para copiar o formato.

Antes de escrever, confirme no código as cinco constatações da seção 2 do brief:
o guard de sincronizarStatusComGuias, o canSend amarrado ao status da solicitação,
o itens()->orderBy('id')->first() em sincronizarGuiaDaSolicitacao, o placeholder
GUIA-SOLICITACAO- por solicitação, o '?? 10' na criação de itens, e o uso único de
sessoes_por_guia. Se alguma não bater, PARE e me avise.

Crie o change "solicitacao-adicionar-sessoes" com proposal.md, design.md, tasks.md e
specs/, cobrindo R1 a R9 e os não-objetivos da seção 5.

No design.md, trate explicitamente o efeito colateral descrito em R3: com o status
refletindo os itens, a transição para ready_for_automation passa a acontecer muito mais
vezes, e é ela que dispara a criação de guias em convênio manual.

Organize tasks.md em quatro commits, na ordem da seção 4.
Não implemente nada. Rode openspec validate e me mostre a saída.
```

### Prompt 2 — Commit 1: guia por item em convênio manual

```
Leia docs/brief-adicionar-sessoes-na-solicitacao.md e a spec aprovada em
openspec/changes/solicitacao-adicionar-sessoes/.

ANTES DE CODAR: me mostre a query SQL do R1 e espere eu trazer o resultado da produção.
Não siga sem isso — ela decide se existe dado a consertar ou se o ajuste é só para frente.

Implemente o R1 em api/app/Services/SolicitacaoService.php:
- sincronizarGuiaDaSolicitacao percorre TODOS os itens sem guia, não só o primeiro.
- Placeholder de numero_guia por item, não por solicitação. Sem isso, as N guias nascem
  com o mesmo "número" e qualquer busca por número devolve a guia errada — o índice
  (convenio_id, numero_guia) não é unique, então falha em silêncio.
- Extraia o prefixo GUIA-SOLICITACAO- para uma constante compartilhada. O change
  solicitacao-guias-por-item-e-info também precisa dela: PROCURE se já existe antes de
  criar outra.
- Itens que já têm guia não são tocados.

Testes: solicitação de convênio manual com 3 itens gera 3 guias com números distintos;
rodar o método duas vezes não duplica guia.

Rode php artisan test. Liste as specs lidas e os comandos executados.
```

### Prompt 3 — Commit 2: gate por item + status reflete itens

```
Leia docs/brief-adicionar-sessoes-na-solicitacao.md e a spec do change.
Depende do commit 1.

Implemente R2 e R3. É a mudança de comportamento mais arriscada do change — vai sozinha.

R2: canSend deixa de exigir solicitacao.status === 'ready_for_automation'. Passa a ser
convênio com automação + item sem guia + sem execução ativa + solicitação fora de
under_review/denied/historico. Espelhe a MESMA regra no backend: hoje o botão some mas a
rota POST /solicitacao-itens/{item}/enviar-unimed continua aceitando.

R3: sincronizarStatusComGuias passa a refletir os itens em vez de só evoluir, podendo
regredir de guia_gerada/approved para ready_for_automation quando houver item sem guia.
under_review, denied e historico continuam intocados.

Testes obrigatórios:
- solicitação approved recebe item sem guia -> volta a ready_for_automation
- todos os itens com guia, nem todas aprovadas -> guia_gerada
- todos aprovados -> approved
- denied que recebe item NÃO muda de status
- a rota de envio recusa item de solicitação under_review

Rode php artisan test e npm run test:e2e.
```

### Prompt 4 — Commit 3: vínculo, endpoint e quantidade da regra

```
Leia docs/brief-adicionar-sessoes-na-solicitacao.md e a spec do change.
Depende dos commits 1 e 2.

Implemente R4, R5 e R6 (só backend):
- Migration da coluna renovacao_de_item_id em solicitacao_itens, nullable, FK para a
  própria tabela. Relações renovacaoDe e renovacoes no model.
- POST /solicitacoes/{solicitacao}/itens com permissão solicitacoes.manage, validação no
  padrão do StoreSolicitacaoRequest. Recusa em denied e historico. Repetição de
  especialidade/profissional É PERMITIDA — não crie unique nem validação de duplicata.
  renovacao_de_item_id, quando vier, tem que ser item da mesma solicitação e apontar para
  a ORIGEM da cadeia.
- Após criar, chamar sincronizarStatusComGuias.
- Remover o '?? 10' de SolicitacaoService: a quantidade padrão passa a vir de
  convenio_regras.sessoes_por_guia da regra VIGENTE (respeitando vigente_desde /
  vigente_ate). Sem regra vigente, o padrão é vazio — NÃO invente número.
- Expor no SolicitacaoResource: renovacao_de_item_id e a posição do item na cadeia (1ª,
  2ª, 3ª remessa), para o R9.
- Endpoint ou payload com os dados dos quatro avisos do R8, calculados no backend:
  idade do pedido médico, limite do ciclo, soma já pedida na cadeia e existência de item
  repetido. Calcule no servidor — o front não deve reimplementar regra de convênio.

Testes: item repetido é aceito; renovacao_de_item_id de outra solicitação é recusado;
convênio sem regra vigente não recebe quantidade padrão; status é sincronizado após criar.

Rode php artisan test.
```

### Prompt 5 — Commit 4: modal e entradas

```
Leia docs/brief-adicionar-sessoes-na-solicitacao.md, a spec do change, e
design-system-xiax-agenda.md (§11, contrato de design). Depende do commit 3.

Implemente R7, R8 e R9:
- Ícone + na coluna Ações da listagem e botão "Adicionar sessões" no cabeçalho do
  SolicitacaoGuiaModal. Ambos abrem o mesmo modal; só aparecem com solicitacoes.manage e
  em status que aceita.
- AdicionarSessoesModal com dois caminhos: (A) repetir especialidade já pedida, listando
  os itens atuais e pré-preenchendo especialidade, profissional e quantidade, gravando
  renovacao_de_item_id; (B) adicionar especialidade nova, reaproveitando
  SolicitacaoItensFields.
- Os quatro avisos da tabela do R8, vindos do backend. TODOS não bloqueantes: nenhum
  desabilita o botão de confirmar. Bloquear repetição bloquearia o caso de uso principal
  desta feature.
- Aviso que não se aplica não aparece (convênio sem regra vigente = sem aviso de limite).
- R9: item de renovação identificado como continuação ("Fonoaudiologia · 2ª remessa") na
  coluna Itens e nas abas do modal. Sem isso a tela mostra duas linhas iguais e ninguém
  entende por quê.

Tokens do design system, sem hex — npm run ds:check reprova. Ícone do lucide-react.

Teste Playwright: adicionar repetido mostra o aviso e conclui; o item novo aparece como
2ª remessa; em convênio Unimed o botão de enviar habilita para o item novo.

Rode npm run lint e npm run test:e2e.
```

### Prompt 6 — Fechamento

```
Leia docs/brief-adicionar-sessoes-na-solicitacao.md.

1. Percorra os critérios de aceite da seção 6 e diga o estado de cada um COM EVIDÊNCIA
   (arquivo/linha ou saída de teste). Nada marcado como pronto sem evidência.
2. Busque no repo se sobrou algum '?? 10' ou outro limite de convênio hardcoded em
   Service/Controller — é violação da regra de ouro do projeto.
3. Confirme que existe uma única constante do prefixo GUIA-SOLICITACAO- em todo o repo.
4. Escreva docs/resumo-entregas-AAAA-MM-DD.md no formato dos resumos já em docs/.
5. Arquive o change conforme o fluxo do openspec do projeto.

Rode php artisan test, npm run lint e npm run test:e2e, e me mostre a saída de cada um.
```

---

## 9. Riscos

| Risco | Como evita |
|---|---|
| Laço em convênio manual gerando N guias com o mesmo número | §2.3 e o Prompt 2 tratam o placeholder explicitamente; critério de aceite cobre |
| Status regredindo e disparando criação de guias em solicitação antiga | R3 obriga a documentar no design.md; a query do R1 mostra se há dado afetado |
| Botão escondido mas rota aberta | R2 exige a regra espelhada no backend, com teste |
| Bloquear repetição "para proteger o usuário" | escrito em três lugares do brief: repetição é o caso de uso, nunca bloqueia |
| Reintroduzir o 10 hardcoded no endpoint novo | R6 + varredura no Prompt 6 |
| Duas constantes do prefixo, uma por change | Prompt 2 manda procurar antes de criar; Prompt 6 confere |
| Somar o ciclo por especialidade+profissional em vez do vínculo | R8 explica por que o quarto aviso depende do R4 |
