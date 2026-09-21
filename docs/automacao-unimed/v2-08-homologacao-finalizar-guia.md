# Homologação da finalização de guia na Unimed

Roteiro da primeira execução em produção da operação `finalizar_guia`.

## Por que este documento existe

**A Unimed não tem ambiente de homologação.** Todo o código desta operação foi
escrito contra a descrição de quem opera o portal e uma fixture montada a partir
do HTML fornecido — o portal real só é visto em produção, contra uma guia real
de um paciente real.

Duas telas continuam desconhecidas no momento em que isto foi escrito:

1. A **tela de execução preenchida** — em particular o formato que `dt_serie_N`
   aceita. `dd/mm/aaaa hh:mm` é o palpite de quem opera, não um fato observado.
2. O **popup de anexos** da tela de execução (o de `gerarGuia` é outro, de outra
   tela).

O worker foi escrito para que a primeira execução **responda** essas duas
perguntas em vez de apenas falhar: cada passo confere o efeito do que fez e
devolve um código próprio com o que enviou e o que o portal aceitou.

## Antes de começar

- [ ] Migração aplicada (`automacao_finalizar_guia_simulacao_ativo` existe em
      `configuracoes_globais`, com padrão `true`).
- [ ] Worker reimplantado com `finalizarGuia.js` — conferir em
      `POST /operations/finalizar_guia` que a resposta **não** traz `mock: true`.
- [ ] Credencial Unimed do convênio ativa e não pausada pelo disjuntor.
- [ ] **Confirmar que o modo simulação está LIGADO.** É o padrão, mas confira:
      é a única coisa entre a primeira execução e uma guia finalizada errada na
      operadora.

## Escolher a guia da primeira execução

Escolha a guia menos arriscada que existir:

- Sessões registradas **iguais** à quantidade autorizada (sem diálogo de
  decisão no meio).
- **Uma folha só** anexada (o caminho de duas folhas vem depois).
- Sessões com horário preenchido e bem espaçado — o intervalo mínimo de 50
  minutos já é conferido pelo pré-voo, mas uma guia folgada elimina uma
  variável.
- De preferência uma guia que já iria ser finalizada de qualquer forma.

## Rodada 1 — simulação

Em **Sessões**, filtre pela guia e clique em **Finalizar na Unimed**. O painel
deve mostrar o aviso amarelo de simulação. Confirme e acompanhe.

Depois, no detalhe da guia, abra **Finalizações na Unimed**.

### Se der certo

A execução aparece como **Simulação**, e a guia **não** muda de situação.
Confira no portal, com os próprios olhos:

- [ ] A guia certa foi aberta.
- [ ] Regime = `01 - Ambulatorial`, Tipo = `03 - Outras Terapias`.
- [ ] As datas de série, na ordem e **com a hora** que as sessões têm no Gescon.
- [ ] A folha aparece na lista de anexos da guia.
- [ ] A guia **continua em aberto** no portal (nada foi gravado).

A evidência da execução guarda um screenshot da tela preenchida e a lista de
datas que ficaram nos campos (`resultado.evidencia.datas_preenchidas`) — use-a
para conferir o formato que o portal aceitou.

### Se falhar

Os códigos que esta rodada provavelmente vai produzir, e o que fazer com cada
um. Todos vêm com `diagnostico.url` e `diagnostico.pagina`, que é o texto
visível da tela onde parou.

| Código | O que significa | O que fazer |
| --- | --- | --- |
| `TELA_BUSCA_GUIA_NAO_ENCONTRADA` | Depois do login, `s_nr_guia` não está na página. | O caminho até a tela de busca é diferente do presumido. Pegar a URL real no portal e ajustar `abrirExecucaoDaGuia`. |
| `GUIA_NAO_ENCONTRADA_NO_PORTAL` | O filtro rodou e não devolveu a guia. | Conferir se o número da guia no Gescon é o mesmo do portal, e se o link da linha é mesmo `a.MagnetoDataLink` com o número como texto. |
| `TELA_EXECUCAO_NAO_ABRIU` | O clique aconteceu mas `#DM_REGIME_ATEND` não apareceu. | Provavelmente a execução abre em **popup**, não na mesma aba. Trocar por `waitForPopup`, como `gerarGuia` faz. |
| `DT_SERIE_FORMATO_RECUSADO` | **O mais esperado.** O campo devolveu algo diferente do que enviamos. | Ler `enviado` e `aceito` no resultado — é a resposta da pergunta do formato. Ajustar apenas `formatarDataSerie` e, se necessário, `valorFoiAceito`. |
| `DT_SERIE_CAMPO_INDISPONIVEL` | O campo não existe ou está desabilitado. | Quem opera informou que os dez já nascem habilitados. Sendo diferente, é preciso descobrir o gatilho que libera o campo seguinte. |
| `ANEXO_BOTAO_NAO_ENCONTRADO` | Nem `[title="Item anexos"]` nem `#item_anexos_1` na tela. | Capturar o HTML do ícone de anexos nesta tela e ajustar o seletor. |
| `ANEXO_NAO_CONFIRMADO` | A folha subiu mas o popup não mostrou "Total de registros". | O popup desta tela pode confirmar de outro jeito. Capturar o HTML dele depois de anexar. |
| `QT_AUTORIZADA_DIVERGENTE` | Gescon e portal discordam da quantidade autorizada. | **Não é bug**: é dado divergente. Corrigir a guia no Gescon antes de tentar de novo. |

Corrija, redeploy do worker, e repita a rodada 1 até passar limpa.

## Rodada 2 — duas folhas, ainda em simulação

Com uma guia que tenha **duas folhas** anexadas, repita. O que se confere aqui
é só que as duas aparecem na lista de anexos do portal — o worker abre o popup
uma vez por folha, e este é o único momento em que dá para saber se o portal
aceita isso.

## Rodada 3 — a real

Só depois das duas anteriores passarem:

1. Desligar `automacao_finalizar_guia_simulacao_ativo` em Configurações.
2. Rodar contra a mesma guia da rodada 1.
3. Conferir no portal que a guia ficou finalizada.
4. Conferir no Gescon que a guia está `finalized`, com data de finalização, e
   que o histórico de status registra origem **automação**.

**Se algo der errado nesta rodada**, religar a flag de simulação desarma o passo
irreversível sem precisar de deploy.

## O que NÃO acontece em caso de falha

Por construção, e vale conferir se alguma dessas premissas se quebrar:

- Falha **não** finaliza a guia no Gescon — ela fica como estava.
- Simulação **não** finaliza a guia no Gescon, mesmo terminando com sucesso.
- Falha de finalização **não** pausa a credencial do convênio: nenhum dos
  códigos desta operação é estrutural (ver `AutomationErrorCatalog`). Uma data
  mal formatada não pode derrubar a automação inteira do convênio.
- A finalização pode ser acionada de novo depois da falha, sem refazer o
  registro das sessões.

## Depois da homologação

Registrar aqui, ao final: o formato real de `dt_serie_N`, o caminho da tela de
busca, e o HTML do popup de anexos. São as três coisas que faltavam quando isto
foi escrito, e quem vier depois não deveria ter de descobri-las de novo.
