# Entregas de 10/09/2026 — cadeia de bugs na automação Unimed + excluir item de solicitação

Sessão que começou com "restaura o aviso de guias negadas" e virou uma varredura de bugs na
automação RDA/SGU-Card — a maioria da mesma família (timeout curto demais num clique que dispara
navegação/popup no portal real) mais um bug de leitura de coluna que afetava praticamente toda
guia gerada no dia.

## 0. Guias negadas: banner de volta em `/guias` e no Dashboard

O banner dedicado (`GuiaAlertaNegacoes`) tinha sido absorvido pela Central de Alertas (`/alertas`,
regra `guia.negada`) no refactor de 09/09. A pedido do usuário, os dois passam a coexistir sobre a
mesma fonte de dados (`alerta_negacao_ocultado_em`) — a Central de Alertas não saiu do lugar.

## 1. Solicitação 2331 (Nicholas Stradioto Daboit): guia "sumida"

Usuário reportou que uma guia tinha sido lançada na Unimed mas não aparecia em Guias.
`automacao_execucoes` mostrava `WORKER_INTERNAL_FATAL` no clique de Finalizar
(`locator.click: Timeout 30000ms exceeded`) — o clique tinha acontecido de verdade no portal, mas o
Playwright estourou esperando a navegação de confirmação, e a exceção subia crua em vez de virar
`uncertain` (o desfecho que aciona `ConfirmarGuiaIncertaUnimedService` sozinho).

Efeito em cadeia: como `WORKER_INTERNAL_FATAL` é código "estrutural", `UnimedCircuitBreakerService`
pausou a credencial do tenant inteiro a cada ocorrência — travando a automação de **todo mundo**,
não só desse item.

Busca manual no portal (script de diagnóstico existente, `checar-guia-paciente.js`) achou **3
guias duplicadas** geradas nas 3 tentativas seguidas do dia (50144598876, 50144599063,
50144599282). Registradas as 3 no gescon depois de confirmar (ver §2) que as 3 são reais e
autorizadas — não havia como reduzir a uma só sem inventar dado.

**Fix** (`worker-unimed/src/operations/gerarGuia.js`): `finalize.click()` ganhou `try/catch`
devolvendo `status: 'uncertain'` no timeout, em vez de deixar a exceção virar `WORKER_INTERNAL_FATAL`
— mesmo desfecho que o `parseResultado(page)` vazio já produzia, só que agora cobrindo o timeout do
próprio clique, não só da espera pós-clique.

## 2. Bug de leitura na busca "Exames em aberto": falso `GUIA_NOT_FOUND`

Verificando as 3 guias do Nicholas, 2 de 3 vinham `GUIA_NOT_FOUND` mesmo estando la — usuário
confirmou ao vivo no portal (autorizadas, visíveis em "Exames em aberto"). Duas causas na mesma
função (`abrirGuiaPorFiltro`, `worker-unimed/src/operations/statusSenha.js`):

1. `waitProcessing()` só espera o texto "Processando..." sumir — o postback do filtro pode
   reescrever a tabela **depois** disso. `rowByGuia` lia o DOM stale (ainda com todos os registros
   não filtrados) e só achava a guia se ela estivesse por acaso na primeira página.
2. `fillIfVisible()` só tenta preencher o campo `s_nr_guia` se ele já estiver visível dentro de
   500ms — curto demais quando a função roda em sequência (uma guia após a outra, mesma `page`) e a
   tela ainda está voltando da guia anterior. O preenchimento era pulado em silêncio.

**Fix:** espera o rótulo "N exame(s) encontrado(s)" aparecer antes de ler a tabela; `.fill()` direto
em vez de `fillIfVisible` (espera até `DEFAULT_TIMEOUT` pelo campo ficar acionável, lança se não
conseguir). Aplicado também ao filtro equivalente em `localizarGuiaPorCadastro`.

**Efeito colateral bom:** rodando a varredura de "outras guias com esse problema" em todas as
execuções falhas do dia, achou mais **7 guias com dado desatualizado** só por causa desse bug — 1
com status errado (voltou "Em análise" quando já estava Autorizada) e 6 sem a senha de autorização
capturada. Todas corrigidas.

## 3. Mais dois cliques com timeout curto demais

Mesma família dos itens 1 e 2, achados ao vivo no item #2371 (Miguel Schweiter Zambom,
`abrirBeneficiario`, popup de "+Novo Exame") e no item #2362 (mesmo paciente,
`abrirBuscaContratado`, `#link_busca_contrt`). Timeout de 30s aplicado nos dois — mesmo padrão já
usado no login (`portal.js`) e no Finalizar (§1).

**Pendência aberta:** item #2371 continuou falhando no MESMO ponto mesmo depois do fix — 7
tentativas seguidas, sempre `browserContext.waitForEvent: Timeout ... "page"`. Confirmado que o
mecanismo funciona normalmente em todos os outros itens do dia e em testes manuais isolados; a
causa raiz dessa falha específica não foi encontrada nesta sessão. Precisa de investigação ao vivo
(navegador visível) numa sessão futura se voltar a falhar.

## 4. O bug do dia: "MEDICO NAO COOPERADO" em 32 de 33 guias

`selecionarPrestador()` tenta achar o médico solicitante por CRM, depois por nome, e só cai no
fallback genérico "MEDICO NAO COOPERADO" se as duas buscas não acharem nada. Hoje, **32 das 33**
guias geradas caíram nesse fallback — inclusive para médicos com histórico de guias autorizadas sob
o próprio nome (ex. Dr. Lucas Yuji Igarashi, CRM 25760).

Reproduzido ao vivo: a busca por CRM **achava** o médico certo ("OK - Ativo", "Prestador da Rede
Unimed") — o problema era `buscarPrestadorPorNome` ler `row.locator('td').first()` como o nome do
prestador. No portal real a 1ª coluna da tabela de resultado é "Código na Operadora" (ex.
"90025760"), a 2ª é "Nome do Prestador" — comparava o nome esperado contra um código numérico,
similaridade sempre perto de zero, nunca dava match.

A fixture de teste (`tests/fixtures/portal-gerar-guia.html`) só tinha 2 colunas com o nome
primeiro — por isso o bug nunca apareceu nos testes automatizados apesar de casos de nome
abreviado/ambíguo já estarem cobertos. Corrigida para ter as mesmas colunas do portal real.

**Fix:** ler `td.nth(1)` em vez de `td.first()`. Confirmado ao vivo depois do fix (busca isolada por
CRM continua achando o médico certo).

**Dívida que fica:** as 32 guias já submetidas hoje antes do fix foram gravadas na Unimed com
"MEDICO NAO COOPERADO" em vez do médico real. O gescon não tem como corrigir isso retroativamente —
avisado ao usuário para verificar com a operadora se isso tem consequência de autorização/faturamento.

## 5. Excluir item de solicitação (feature nova)

Pedido: corrigir cadastro errado sem reabrir a solicitação inteira. `DELETE
/solicitacoes/{solicitacao}/itens/{item}` (`SolicitacaoService::removerItem`) — permitido em
qualquer item **sem Guia gerada**, mesmo que já tenha tentativa de automação falha (é exatamente o
caso de uso). Bloqueado quando: o item já tem Guia; é o último item da solicitação (a solicitação
não pode ficar sem nenhum — negar/cancelar é o caminho pra isso); a solicitação está
negada/histórico (mesmo gate de `SolicitacaoStatus::bloqueiaAdicao`).

Tela: botão "Excluir item" por item, confirmação em duas etapas reaproveitando o
`ConfirmarExclusao` que já existia no design system (digitar **EXCLUIR**) — decisão do usuário na
sessão, não invenção nova.

## Validação

| Comando | Resultado |
|---|---|
| `worker-unimed`: `node --test` (gerarGuia, statusSenha, confirmarGuiaIncerta) | 22 de 22 |
| `php artisan test --filter=Solicitacao` | 71 de 71 |
| `npx tsc -b && npx oxlint` (web) | limpo (só os 8 warnings pré-existentes de `only-export-components`) |
| Verificação ao vivo (portal real `rda.unimedsc.com.br`) | guias do Nicholas, busca por CRM, e 7 guias com dado desatualizado — todas reconferidas depois de cada fix |

Todos os commits (`b50a976`, `6625b9d`, `2790747`, `56873d4`, `383cd44`) já em produção via
`deploy/redeploy.sh`, um deploy por fix — não em lote — para poder isolar qual mudança causou o quê
se algo desse errado no meio do caminho.
