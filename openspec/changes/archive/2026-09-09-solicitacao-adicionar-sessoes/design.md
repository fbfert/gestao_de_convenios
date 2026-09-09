# Design — Adicionar sessões numa solicitação existente

## 1. O efeito colateral central: o status passa a disparar criação de guia

Esta é a decisão que precisa ser explícita, e não descoberta em produção.

`sincronizarStatusComGuias()` hoje só **evolui**: sai cedo quando o status não é
`ready_for_automation` nem `guia_gerada`. Passando a **refletir** os itens, ela pode regredir de
`approved`/`guia_gerada` para `ready_for_automation` sempre que aparecer item sem guia.

E `ready_for_automation` não é um rótulo passivo: é a transição que dispara
`sincronizarGuiaDaSolicitacao()` — hoje pelo `alterarStatus()`. Somando as duas mudanças, em
**convênio manual** uma solicitação antiga que receba um item novo vai gerar, de uma vez, as guias
que faltavam nos itens antigos (porque o R1 passa a percorrer todos).

**Decisão: é o comportamento certo, e ele fica.** Aquelas guias deveriam existir desde o dia em que
a multi-especialidade entrou; a ausência delas é o defeito do R1, não um estado desejado. Criá-las
na primeira transição seguinte é a correção chegando junto com o próximo toque humano na
solicitação — não um backfill em massa, que continua fora de escopo.

**O que limita o risco:** a query de conferência do R1 mede quantas solicitações de convênio manual
têm menos guias que itens. Se vier vazia — esperado, porque a NeuroKids opera só Unimed —, não há
dado a corrigir e o efeito é só para frente. **Rodar essa query em produção é pré-condição do
commit 1.**

Guarda que fecha o resto: `ready_for_automation` só dispara criação para item **sem** guia. Item que
já tem guia nunca é tocado, então nenhuma transição reescreve guia existente.

## 2. Por que a checagem é afrouxada nos dois lados, e não só no front

O brief supunha rota aberta. Não é o caso: `GerarGuiaUnimedService::avaliar()` recusa quando
`status !== 'ready_for_automation'`. Se afrouxássemos só o `canSend`, o botão habilitaria e a API
recusaria — pior que hoje, porque troca um botão honestamente desabilitado por um erro depois do
clique.

A regra passa a ser a mesma nos dois lugares, e ela mora no backend como fonte de verdade:

```
convênio com automação
  E item sem guia
  E item sem execução ativa
  E solicitação fora de under_review, denied e historico
```

`under_review` fica de fora porque enviar antes da análise pularia a etapa que existe para conferir
o pedido. `denied` e `historico` ficam de fora porque enviar reabriria, pela porta dos fundos, algo
que foi encerrado.

## 3. Por que o vínculo aponta para a origem, e não para o anterior

`renovacao_de_item_id` aponta sempre para o **primeiro** item da cadeia. Somar o que já foi pedido
no ciclo vira uma query — `where(renovacao_de_item_id, origem)` mais o próprio item de origem — em
vez de uma recursão que sobe de item em item.

O custo é uma invariante a manter: ao renovar a partir de um item que já é renovação, o
`renovacao_de_item_id` gravado é o **da origem dele**, não o id do item escolhido. Isso é regra de
servidor, e o teste tem que travá-la.

O quarto aviso do R8 ("já há 10 sessões de Fonoaudiologia nesta solicitação") **só é exato por causa
disso**. Somar por especialidade + profissional contaria junto duas terapias legitimamente separadas
— o mesmo par pode aparecer por dois motivos clínicos distintos na mesma solicitação, e a
repetição é justamente o caso de uso que não bloqueamos.

## 4. Por que um campo novo, e não `qtd_autorizada_por_ciclo`

`qtd_autorizada_por_ciclo` é taxa de lançamento, emparelhada com `frequencia_lancamento`: "1 por
dia", "4 por mês". O comentário em `AntecipacaoService.php:107-114` afirma isso, e as observações do
`ConvenioRegraSeeder` também ("liberação diária e 1 sessão por dia"; "duas autorizações por dia").

Reaproveitá-lo como quantidade padrão poria **1** na Unimed especializada. Sairia de um número
errado no código para um número errado no banco — pior, porque parece configurado de propósito.

`convenio_regras.sessoes_por_guia` entra ao lado, nullable, sem tocar no campo existente e sem
migração de dado. Nulo significa "não sabemos", e não zero: a quantidade vem **vazia** e a atendente
digita. Nunca inventar número é o ponto — inventar é o que o `?? 10` fazia.

A regra é escolhida por `(convenio_id, tipo_terapia)` com vigência, como `AntecipacaoService` já faz.
`tipo_terapia` do item vem do mapeamento especialidade × convênio; sem mapeamento, não há regra e a
quantidade fica vazia — mesmo caminho do campo não preenchido.

## 5. Os quatro avisos são calculados no servidor

Regra de convênio é dado, e o front não pode reimplementá-la (`openspec/config.yaml`). Os quatro
avisos vêm prontos do backend, junto com o payload que abre o modal:

| Aviso | Fonte |
|---|---|
| Repetição | item existente com mesma especialidade **e** profissional |
| Pedido médico | idade de `solicitado_em` em dias |
| Limite do ciclo | `sessoes_por_guia` da regra vigente |
| Já pedido na cadeia | soma de `quantidade` na cadeia de `renovacao_de_item_id` |

Nenhum bloqueia. Bloquear repetição bloquearia o caso de uso principal — está escrito três vezes no
brief, e vale repetir aqui: o aviso informa, quem decide é a atendente.

Aviso sem fonte não aparece. Convênio sem regra vigente não gera aviso de limite, em vez de gerar um
aviso com número inventado.

## 6. Ordem dos commits

O R1 vai sozinho e primeiro porque é correção de defeito existente, verificável sem nada do resto.

O R2+R3 vão juntos e sozinhos por serem a mudança de comportamento mais arriscada: gate e status se
sustentam mutuamente, e separá-los deixaria um estado intermediário em que o botão habilita para um
item que o status ainda não permite.

O R4+R5+R6 são o backend da feature, sem interface — testáveis por requisição.

O R7+R8+R9 são a interface, que não faz sentido antes do endpoint existir.

## 7. Alternativas descartadas

**Modelar quantidade pedida + parcelas** (o item guarda 20 e as guias somam 10+10) descreve melhor a
realidade, mas mexe no modelo, na automação e na conciliação de uma vez. `renovacao_de_item_id` é o
degrau barato: resolve o caso de uso agora e não fecha aquela porta.

**Bloquear repetição de especialidade + profissional** foi descartado explicitamente: é exatamente o
caso de uso principal.

**Backfill das guias faltantes em convênio manual** fica fora até a query do R1 mostrar que há dado
afetado.
