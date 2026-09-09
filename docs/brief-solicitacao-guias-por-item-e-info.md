# Brief de implementação — Guias por item + coluna Info em Solicitações

Repo: `fbfert/gestao_de_convenios` · Change do openspec: **`solicitacao-guias-por-item-e-info`**
Corre em paralelo ao plano de Saúde/Alertas — não depende dele e não é dependido por ele.

> **Como usar este documento:** salve como `docs/brief-solicitacao-guias-por-item-e-info.md` no repo.
> Depois rode os prompts da seção 8, um por vez, no Claude Code dentro do VSCode.
> Os prompts referenciam este arquivo, então o Claude Code lê o brief inteiro sem você colar tudo.

---

> ## ⚠️ Revisão de 09/09/2026 — este brief está CONCLUÍDO
>
> **O change `solicitacao-guias-por-item-e-info` foi implementado e arquivado em 08/09/2026**
> (`openspec/changes/archive/2026-09-08-solicitacao-guias-por-item-e-info`). Os seis prompts da
> seção 8 foram rodados. Este documento vale como registro do raciocínio, **não como plano de
> trabalho** — rodar os prompts de novo produziria trabalho já feito, ou pior, desfeito.
>
> Dois changes posteriores mexeram no que ele diagnostica, e o diagnóstico ficou desatualizado:
>
> | Change | Data | O que mudou aqui |
> |---|---|---|
> | `remover-relacao-guia-legada` (ADR-27) | 08/09 | `Solicitacao::guia()` **não existe mais** — virou `guias()`, um `hasMany`. Os oito eager loads da §5 **já caíram**. A chave `guia` saiu do payload. |
> | `solicitacao-adicionar-sessoes` | 09/09 | O valor de preenchimento virou **por item**; `numeroGuiaDaSolicitacao()` virou `numeroGuiaDoItem()`. Itens ganharam `renovacao_de_item_id` e `posicao_na_cadeia`. |
>
> As seções revisadas trazem o estado atual **e** o que o brief dizia, porque a diferença é o
> registro do que aconteceu. O que foi corrigido: §2.1, §2.3, §2.4, §5, R8 e o Prompt 2.

---

## 1. Objetivo em uma frase

Uma solicitação com várias especialidades gera várias guias, mas o sistema mostra só uma — e mostra o **id interno** no lugar do número da guia. Este change corrige as duas coisas e adiciona uma coluna de informação rápida na listagem.

---

## 2. Diagnóstico — leia antes de decidir qualquer coisa

Três constatações verificadas no código em 08/09/2026. Confirme cada uma antes de implementar; se alguma não bater, **pare e avise** em vez de seguir.

### 2.1 ~~`Solicitacao::guia()` é uma relação legada e devolve guia arbitrária~~ — RESOLVIDO

> **Estado em 09/09/2026:** a relação **não existe mais**. `api/app/Models/Solicitacao.php:77`
> declara `guias()`, um `hasMany`:
>
> ```php
> public function guias()
> {
>     return $this->hasMany(Guia::class);
> }
> ```
>
> O `hasOne` sem ordenação foi removido no change `remover-relacao-guia-legada` (ADR-27, "A guia
> pertence ao item, e não à solicitação"). A relação não foi apagada, e sim **corrigida**: a
> pergunta "esta solicitação já tem guia?" continua existindo — é ela que trava a remoção do anexo
> do pedido —, e um `hasMany` responde isso sem eleger uma principal que não existe. Há um caso que
> só ele cobre: guia anterior à multi-especialidade fica presa à solicitação com
> `solicitacao_item_id` nulo.
>
> Para **exibir**, a fonte é `itens[].guia`, como este brief pedia. O modal já monta as abas assim.

O diagnóstico original, que segue válido como história do problema: desde a multi-especialidade cada `SolicitacaoItem` gera sua própria guia (`guias.solicitacao_item_id`), e aquele `hasOne` **não tinha ordenação** — devolvia qualquer uma das guias da solicitação, variando com a ordem física das linhas. O modal usava `solicitacao?.guia?.id`: não era "mostra a primeira", era "mostra uma qualquer".

**A correção não era adicionar abas. Era trocar a fonte.**

### 2.2 A tela mostra o id interno como se fosse número de guia

```tsx
// web/src/features/solicitacoes/SolicitacoesPage.tsx:863
Guia #{item.guia_id}
```

`guias.id` é sequencial interno. O número que a operadora conhece é `guias.numero_guia`. Hoje a atendente lê "#12" na tela e liga para a Unimed com um número que não existe lá.

### 2.3 O `numero_guia` tem três estados, não dois

Isto é a pegadinha do change. Trocar `guia_id` por `numero_guia` sem tratar os três casos **substitui um número errado por outro**.

| Caso | Origem | O que exibir |
|---|---|---|
| Número real da operadora | automação Unimed confirmada | o número |
| `GUIA-SOLICITACAO-{solicitacao}-{item}` | valor de preenchimento gerado em `SolicitacaoService::numeroGuiaDoItem()` (`api/app/Services/SolicitacaoService.php:567`) para convênio manual | **não é número de operadora** → "Guia gerada · sem nº da operadora" |
| `null` | `numero_guia` virou nullable na migration `2026_08_03_200000_add_unimed_v2_foundation_fields`; o worker cria a guia antes de confirmar o número, e existe o estado `uncertain` | "Guia gerada · nº pendente" |

Trate o prefixo `GUIA-SOLICITACAO-` como ausência de número, com uma constante compartilhada — não com string solta espalhada pelo front.

> **09/09/2026:** a constante existe e é única em todo o código —
> `api/app/Support/GuiaStatus.php:19`, `PREFIXO_NUMERO_PLACEHOLDER`, com os auxiliares
> `numeroEhPlaceholder()` e `numeroDaOperadora()` ao lado. O formato mudou para
> **por item** (`{prefixo}{solicitacao_id}-{item_id}`) no change `solicitacao-adicionar-sessoes`:
> com uma guia por item, um valor por solicitação faria as N nascerem iguais, e o índice
> `(convenio_id, numero_guia)` **não é unique** — não estouraria, só faria qualquer busca por
> número devolver uma guia arbitrária. `numeroEhPlaceholder()` usa `str_starts_with`, então o
> formato novo continua reconhecido como ausência de número.

### 2.4 O badge "Guia gerada" só existe para Unimed

```tsx
// SolicitacoesPage.tsx:866 — estado em 08/09, já corrigido
{isUnimedRda && item.guia_id ? (<span>Guia gerada</span>) : null}
```

Convênio manual com guia gerada não mostra indicação nenhuma. E ao lado do número da guia, "Guia gerada" é redundante — se tem guia, foi gerada.

> **09/09/2026:** corrigido. O badge exibe o status real via `translateStatus('guias', …)` para
> qualquer convênio, e `item.guia_id` não existe mais no payload — a condição passou a ser
> `item.guia` (ver R8).

### 2.5 Boa notícia: a coluna Info não custa nada

`SolicitacaoService::listar()` (linha 38) já faz eager load de tudo que a coluna Info precisa:

```php
'cidCadastros', 'documentos.arquivo', 'itens.documentos.arquivo', 'itens.guia', ...
```

**Requisito rígido do change: a coluna Info não pode adicionar uma única query.** Se você precisar de dado que não está nessa lista, pare e avise — provavelmente o requisito está errado, não o eager load.

---

## 3. Decisões já tomadas (não reabrir)

| Tema | Decisão | Motivo |
|---|---|---|
| Fonte das guias | `itens[].guia`, nunca `solicitacao.guia` | a relação legada devolve guia arbitrária |
| Abas | uma por **item**, inclusive itens sem guia | ver que faltam 2 de 3 especialidades é a informação mais importante |
| Rótulo da aba | especialidade + número — "Fonoaudiologia · Guia 12345" | "Guia 1 / Guia 2" não comunica nada |
| Aba de item sem guia | estado vazio "Aguardando geração da guia" | some o sinal do que ainda não saiu |
| Número | `numero_guia` tratando os três casos da §2.3 | id interno nunca vira número de guia |
| Badge | **status real da guia**, traduzido | vale para qualquer convênio e diz mais que "gerada" |
| Coluna Info | quatro ícones **sempre visíveis**; sem conteúdo = apagado e não focável | previsibilidade de leitura, sem inflar o tab order |
| Ícone de CID | ícone neutro com token, **nunca cruz vermelha** | no sistema vermelho = perigo/negado; ADR-23 proíbe cor como único sinal |
| Data | `solicitado_em` como linha secundária sob o paciente | é o dado mais consultado; hover por linha é caro |
| Tooltip do calendário | as três datas: solicitada, cadastrada, atualizada | `solicitado_em` ≠ `created_at`; hoje ninguém sabe há quanto tempo o pedido está parado dentro do sistema |
| "Médico solicitante" | vira "Médico" | pedido do produto |

---

## 4. Requisitos

### R1 — Abas de guias por item no modal

- `SolicitacaoGuiaModal` passa a montar as abas a partir de `solicitacao.itens`, não de `solicitacao.guia`.
- Uma aba por item, na ordem em que os itens vêm da API.
- Rótulo: `{especialidade.nome} · Guia {numero}` quando houver número; `{especialidade.nome}` quando não.
- Conteúdo da aba com guia: o `GuiaDetalheResumo` já existente (`web/src/features/guias/GuiaDetalheResumo.tsx`).
- Conteúdo da aba sem guia: estado vazio "Aguardando geração da guia", reaproveitando o visual do `solicitacao-guia-empty` atual.
- O número/título da guia dentro da aba é **link** para `/guias/{id}` (rota já existe: `AppRoutes.tsx:72`).
- Solicitação sem item nenhum (legado): mantém a mensagem atual de "sem guia vinculada".
- Usar `@headlessui/react` para as abas — já é dependência, e o próprio arquivo já usa `Dialog` dela.

### R2 — Número da guia, não id interno

- `SolicitacoesPage` deixa de renderizar `Guia #{item.guia_id}`.
- Exibe conforme a tabela da §2.3, com uma constante compartilhada para o prefixo `GUIA-SOLICITACAO-`.
- Onde não houver número da operadora, o texto deixa claro que a guia existe — nunca sugere que falhou.

### R3 — Badge de status real

- Substitui o "Guia gerada" fixo pelo status da guia traduzido via `translateStatus('guias', status)` (`web/src/lib/statusLabels.ts`).
- Tom do badge por `statusTone` (`web/src/features/guias/statusTone.ts`), como no resto do app.
- Vale para **qualquer** convênio, não só `connector_driver === 'unimed_rda'`.
- Atenção aos status `historico_*` (`api/app/Support/GuiaStatus.php`): eles têm prefixo e já têm tradução própria — não quebre.

### R4 — Coluna "Info" na listagem

Nova coluna à direita, antes de Ações, com quatro `Tooltip` (`web/src/components/ui/Tooltip.tsx`, que já trata hover, foco de teclado, toque e deslocamento para caber na viewport):

| Ícone | Conteúdo do tooltip |
|---|---|
| Calendário | solicitada em `solicitado_em`, cadastrada em `created_at`, atualizada em `updated_at` |
| Notepad | `observacoes` da solicitação |
| Clipe | anexos: rótulo do tipo (`DOCUMENTO_LABELS`) + nome de cada documento da solicitação e dos itens |
| CID | código + descrição de cada CID |

- Ícone sem conteúdo: visível, apagado, `aria-hidden`, **não focável**. Quatro botões × 15 linhas seria uma parede no tab order entre a tabela e a paginação.
- Ícones do `lucide-react` (já é dependência). Para CID use `Stethoscope` ou `Activity` — **não** cruz vermelha.
- Cores por token do design system. `npm run ds:check` reprova hex, classe arbitrária e escala crua.
- No modo cartão (`data-cartoes="lg"`), a célula precisa de `data-rotulo="Info"` como as outras.

### R5 — Data visível

`solicitado_em` como linha secundária sob o nome do paciente na listagem, em `text-meta` e cor suave — sem virar coluna nova.

### R6 — "Médico solicitante" → "Médico"

Em **três** lugares, senão o mobile fica inconsistente:

1. `titulo` do `ColunaOrdenavel` (linha ~798)
2. `data-rotulo` da célula (linha ~937) — é o rótulo do modo cartão
3. rótulo do filtro (linha ~685)

Avalie também o `DetailItem label="Médico solicitante"` do modal, por consistência.

### R7 — Larguras da tabela

A tabela é `table-fixed` e as larguras somam exatamente 100%: `5/16/10/35/11/15/8`. Info precisa caber sem estourar. Sugestão: **Itens 35→30, Médico 15→13, Info 5**.

### R8 — Backend: expor guia no item

`SolicitacaoResource` hoje devolve, no bloco de itens, apenas `guia_id`. Passa a devolver um objeto.

> **Estado em 09/09/2026 — feito, e `guia_id` JÁ FOI REMOVIDO.** O bloco de itens
> (`api/app/Http/Resources/SolicitacaoResource.php:43-70`) devolve:
>
> ```php
> 'renovacao_de_item_id' => $item->renovacao_de_item_id,   // solicitacao-adicionar-sessoes
> 'posicao_na_cadeia'    => $this->posicaoNaCadeia($item), // idem
> 'total_na_cadeia'      => $this->cadeiaDe($item)->count(),
> 'guia' => $item->relationLoaded('guia') && $item->guia ? [
>     'id'               => $item->guia->id,
>     'numero_guia'      => $item->guia->numero_guia,
>     'numero_operadora' => GuiaStatus::numeroDaOperadora($item->guia->numero_guia),
>     'status'           => $item->guia->status,
> ] : null,
> ```
>
> Duas diferenças em relação ao que o brief pediu, ambas deliberadas:
>
> - **`numero_operadora` entrou**, além de `numero_guia`. O brief mandava o front reconhecer o
>   prefixo; é mais seguro o backend decidir uma vez — é a regra mais fácil de esquecer na terceira
>   tela que precisar dela.
> - **`guia_id` saiu**, no mesmo commit em que os sete consumidores do front migraram para
>   `item.guia`. A instrução abaixo ("mantenha enquanto algum consumidor usar") foi cumprida e está
>   vencida.

- `itens.guia` **já** é eager-loaded em `listar()`, `show()`, `store()`, `update()`, `aprovar()` e `negar()`. Não adicione eager load novo.
- ~~Mantenha `guia_id` no payload enquanto algum consumidor o usar~~ — cumprido em 08/09. Nenhum `guia_id` de solicitação resta em `web/src/`; os que a busca ainda acha pertencem a antecipações, conciliações, automações e lançamentos, onde é FK legítima daquelas entidades.

---

## 5. Não-objetivos (registre no proposal, não implemente)

**~~Remover `Solicitacao::guia()`~~ — JÁ FOI FEITO, em 08/09/2026.**

> Virou o change próprio que esta seção previa: **`remover-relacao-guia-legada`**
> (`openspec/changes/archive/2026-09-08-remover-relacao-guia-legada`, ADR-27).
>
> **Os oito eager loads caíram.** A busca por `'guia.` em
> `api/app/Services/SolicitacaoService.php` não devolve nada — a listagem não carrega mais
> `guia.paciente`, `guia.convenio`, `guia.profissional`, `guia.especialidade`,
> `guia.solicitacaoItem.especialidade`, `guia.solicitacaoItem.profissional`, `guia.antecipacoes`
> nem `guia.conciliacoes`. O ganho de desempenho que esta seção prometia foi realizado.
>
> O consumidor que segurava a relação (`SolicitacaoAnexos.tsx`) migrou: hoje a guarda é
> `const algumItemComGuia = (solicitacao.itens ?? []).some((item) => Boolean(item.guia))`
> (`SolicitacaoAnexos.tsx:253`).
>
> Dois defeitos que o change encontrou ao puxar o fio, e que esta seção não previa:
>
> - `SolicitacaoService::sincronizarGuiaDaSolicitacao()` lia a guia arbitrária e, três linhas
>   abaixo, reescrevia o `solicitacao_item_id` dela para o do **primeiro** item — podia realocar
>   para o primeiro item a guia que era do terceiro.
> - A trava de anexo tem um caso que só o `hasMany` cobre: guia anterior à multi-especialidade,
>   presa à solicitação com `solicitacao_item_id` nulo. Uma checagem que olhasse só `itens.guia`
>   deixaria o anexo do pedido dessas solicitações antigas voltar a ser removível — e ele é a
>   evidência do que sustentou a autorização.
>
> O texto original desta seção segue abaixo, como registro do que foi previsto.

**Remover `Solicitacao::guia()`.** Ela ainda é usada em `SolicitacaoAnexos.tsx:254` para decidir se um anexo pode ser excluído.

Registre no `proposal.md` que, quando ela sair, caem **oito** eager loads da listagem:

```
guia.paciente, guia.convenio, guia.profissional, guia.especialidade,
guia.solicitacaoItem.especialidade, guia.solicitacaoItem.profissional,
guia.antecipacoes, guia.conciliacoes
```

Hoje toda página de Solicitações carrega antecipações e conciliações de uma guia arbitrária **para não exibir nenhuma delas na lista**. Vira change próprio, com ganho de performance de graça.

Também fora de escopo: mexer em `GuiaDetalheResumo`, alterar a geração de guias, e replicar o padrão de abas em outras telas.

---

## 6. Critérios de aceite

- [ ] Solicitação com 2 especialidades e 2 guias mostra **2 abas** no modal
- [ ] Solicitação com 3 especialidades e 1 guia mostra **3 abas**, duas com "Aguardando geração da guia"
- [ ] O número da guia dentro da aba navega para `/guias/{id}`
- [ ] Guia de convênio manual (`numero_guia` = `GUIA-SOLICITACAO-*`) **não** exibe esse texto como número
- [ ] Guia sem `numero_guia` exibe "nº pendente", nunca o id interno
- [ ] Badge mostra o status traduzido da guia, em convênio Unimed **e** manual
- [ ] Status `historico_*` continua traduzindo certo
- [ ] Coluna Info aparece com quatro ícones; os sem conteúdo estão apagados e não recebem foco por Tab
- [ ] Ícone de CID não é vermelho
- [ ] `solicitado_em` visível sob o nome do paciente
- [ ] "Médico" no header, no filtro **e** no modo cartão do mobile
- [ ] A listagem não ganhou nenhuma query nova (comprovado por teste)
- [ ] `npm run lint` (inclui `ds:check`) passa
- [ ] `php artisan test` e `npm run test:e2e` passam

---

## 7. Arquivos envolvidos

**Backend**
```
api/app/Http/Resources/SolicitacaoResource.php     R8
api/app/Services/SolicitacaoService.php            só leitura — confirmar eager loads e o placeholder (linha 335)
```

**Frontend**
```
web/src/features/solicitacoes/SolicitacaoGuiaModal.tsx   R1
web/src/features/solicitacoes/SolicitacoesPage.tsx       R2, R3, R4, R5, R6, R7
web/src/features/solicitacoes/types.ts                   tipo do item: guia_id -> guia
web/src/components/ui/Tooltip.tsx                        só uso, não alterar
web/src/lib/statusLabels.ts                              translateStatus('guias', ...)
web/src/features/guias/statusTone.ts                     tom do badge
web/src/lib/documentoTipos.ts                            DOCUMENTO_LABELS no tooltip do clipe
```

**Testes**
```
web/tests/  (Playwright)      abas, estado vazio, link da guia
api/tests/Feature/            resource com guia no item; contagem de queries da listagem
```

---

## 8. Prompts para o Claude Code

Rode um por vez. Revise a spec antes de mandar implementar — spec ruim vira código ruim.

### Prompt 1 — Spec no openspec

```
Leia docs/brief-solicitacao-guias-por-item-e-info.md, AGENTS.md e openspec/config.yaml.
Leia também um change existente (openspec/changes/solicitacoes-guia-popup/) para copiar
o formato.

Antes de escrever: confirme no código as constatações da seção 2 do brief —
o hasOne legado em Solicitacao::guia(), o "Guia #{item.guia_id}" na SolicitacoesPage,
os três estados de numero_guia (incluindo o placeholder GUIA-SOLICITACAO- gerado em
SolicitacaoService), o badge restrito a unimed_rda, e os eager loads já presentes em
SolicitacaoService::listar. Se alguma não bater com o brief, PARE e me avise.

Crie o change "solicitacao-guias-por-item-e-info" em openspec/changes/, com
proposal.md, design.md, tasks.md e specs/, cobrindo os requisitos R1 a R8 e os
não-objetivos da seção 5. Registre as constatações confirmadas no proposal.md.

Não implemente nada. Rode openspec validate e me mostre a saída.
```

### Prompt 2 — Backend

> **09/09/2026 — já executado.** A constante **existe**: `PREFIXO_NUMERO_PLACEHOLDER` em
> `api/app/Support/GuiaStatus.php:19`, única em todo o código. Qualquer prompt futuro deve mandar
> **consumi-la**, nunca criar outra — o risco real aqui é duas constantes, uma por change, e a
> distinção "isto não é número de operadora" se perdendo numa delas. O texto abaixo foi ajustado
> para refletir isso.

```
Leia docs/brief-solicitacao-guias-por-item-e-info.md, AGENTS.md e a spec aprovada em
openspec/changes/solicitacao-guias-por-item-e-info/. A spec é a fonte de verdade;
se o código conflitar com ela, registre o conflito antes de alterar.

Implemente apenas o R8 (backend):
- SolicitacaoResource: no bloco de itens, expor um objeto guia com id, numero_guia,
  numero_operadora e status. `guia_id` já foi removido do payload — não o reintroduza.
- O prefixo do valor de preenchimento GUIA-SOLICITACAO- JÁ TEM constante compartilhada:
  GuiaStatus::PREFIXO_NUMERO_PLACEHOLDER, em api/app/Support/GuiaStatus.php:19, com os
  auxiliares numeroEhPlaceholder() e numeroDaOperadora() ao lado. CONSUMA essa —
  não crie uma segunda.
- Nenhum eager load novo: itens.guia já está carregado em listar/show/store/update/
  aprovar/negar. Confirme cada um.

Teste de feature: a listagem NÃO ganha query nenhuma. Use DB::listen para contar as
queries antes e depois, se o projeto não tiver ferramenta própria para isso.

Rode php artisan test. Ao final, liste as specs lidas e os comandos executados.
```

### Prompt 3 — Modal com abas

```
Leia docs/brief-solicitacao-guias-por-item-e-info.md e a spec do change.
Depende do Prompt 2 já mergeado.

Implemente o R1 em web/src/features/solicitacoes/SolicitacaoGuiaModal.tsx:
- Abas por item usando @headlessui/react (o arquivo já usa Dialog dessa lib).
- Uma aba por item, inclusive sem guia, com o estado vazio descrito no brief.
- Rótulo: especialidade + número da guia quando houver.
- Conteúdo com guia: GuiaDetalheResumo. Não altere esse componente.
- Número/título da guia como link para /guias/{id}.
- Solicitação sem itens (legado) mantém a mensagem atual.
- Pare de usar solicitacao.guia neste arquivo. NÃO remova a relação no backend —
  ela ainda é usada em SolicitacaoAnexos.tsx.

Tokens do design system, sem hex. Teste Playwright: 2 itens com guia = 2 abas;
3 itens com 1 guia = 3 abas sendo 2 vazias; o link navega para /guias/{id}.

Rode npm run lint e npm run test:e2e.
```

### Prompt 4 — Listagem

```
Leia docs/brief-solicitacao-guias-por-item-e-info.md e a spec do change.
Depende dos Prompts 2 e 3.

Implemente R2, R3, R5, R6 e R7 em web/src/features/solicitacoes/SolicitacoesPage.tsx:
- Número da guia tratando os TRÊS casos da seção 2.3 do brief. Reler essa seção antes
  de codar: trocar o id pelo placeholder GUIA-SOLICITACAO- seria substituir um número
  errado por outro.
- Badge com o status real da guia traduzido, para qualquer convênio. Cuidado com os
  status historico_* (api/app/Support/GuiaStatus.php).
- solicitado_em como linha secundária sob o nome do paciente.
- "Médico solicitante" -> "Médico" nos TRÊS lugares (ColunaOrdenavel, data-rotulo do
  modo cartão, rótulo do filtro) e avalie o DetailItem do modal.
- Redistribuir as larguras da table-fixed deixando 5% para a coluna Info do próximo
  prompt: Itens 35->30, Médico 15->13.

Atualize os testes Playwright que dependem dos textos alterados.
Rode npm run lint e npm run test:e2e.
```

### Prompt 5 — Coluna Info

```
Leia docs/brief-solicitacao-guias-por-item-e-info.md, a spec do change, e
web/src/components/ui/Tooltip.tsx (leia os comentários do arquivo: o painel só entra
no DOM quando aberto, por causa de um bug de scroll horizontal no mobile).
Depende do Prompt 4.

Implemente o R4: coluna "Info" antes de Ações, com quatro tooltips — calendário
(as três datas), notepad (observações), clipe (anexos com DOCUMENTO_LABELS + nome) e
CID (código + descrição).

Regras que não podem ser negociadas:
- Ícone sem conteúdo: visível, apagado, aria-hidden e NÃO focável. Quatro botões
  focáveis por linha, em 15 linhas, viram uma parede de Tab entre a tabela e a
  paginação.
- Ícone de CID NÃO é cruz vermelha. No sistema vermelho significa perigo/negado
  (ver o alerta de guias negadas) e uma cruz vermelha em toda linha faz o olho ler
  "algo errado aqui". Use Stethoscope ou Activity do lucide-react com token neutro.
  O tema de alto contraste existe por requisito real de acessibilidade (ADR-23):
  cor não pode carregar significado sozinha.
- data-rotulo="Info" na célula, para o modo cartão (data-cartoes="lg").
- NENHUMA query nova: cidCadastros, documentos.arquivo e itens.documentos.arquivo
  já vêm de SolicitacaoService::listar. Se faltar algum dado, PARE e me avise.

Teste em viewport de 390px: o painel do tooltip não pode causar scroll horizontal.
Rode npm run lint e npm run test:e2e.
```

### Prompt 6 — Fechamento

```
Leia docs/brief-solicitacao-guias-por-item-e-info.md.

1. Percorra os critérios de aceite da seção 6 e me diga o estado de cada um, com
   evidência (arquivo/linha ou saída de teste). Não marque nada como pronto sem
   evidência.
2. Remova guia_id do payload dos itens se nenhum consumidor em web/src/ ainda o usa —
   verifique com busca antes.
3. Escreva docs/resumo-entregas-AAAA-MM-DD.md no formato dos resumos já existentes
   em docs/, cobrindo esta entrega.
4. Arquive o change conforme o fluxo do openspec do projeto.

Rode php artisan test, npm run lint e npm run test:e2e, e me mostre a saída de cada um.
```

---

## 9. Riscos

| Risco | Como evita |
|---|---|
| Trocar o id interno pelo placeholder `GUIA-SOLICITACAO-` — número errado vira número errado | §2.3 e o Prompt 4 tratam os três casos explicitamente |
| Remover `Solicitacao::guia()` e quebrar a exclusão de anexos | §5 marca como não-objetivo; `SolicitacaoAnexos.tsx:254` é o ponto |
| Tooltip estourando o layout no mobile | o componente já resolve; teste em 390px é critério de aceite |
| Coluna Info trazendo N+1 | eager loads já existem; o teste de contagem de queries é critério de aceite |
| `ds:check` reprovando o build por cor de ícone | tokens desde o início; se faltar token, criar no design system e registrar |
| Renomear só o header e o mobile ficar com o texto velho | R6 lista os três lugares |
