# Entregas de 14/09/2026 — backup pré-atualização, atualização do Git e cadeia de bugs na automação Unimed

Sessão que começou com um pedido de backup antes de uma atualização, seguiu para o deploy da
atualização em si, e virou uma investigação ao vivo de uma automação Unimed que falhava sem causa
raiz aparente — puxando um fio que terminou em dois bugs reais corrigidos e um ajuste de UX que
existia desde sempre, mas só ficou visível por causa do primeiro caso real.

## 0. Backup pré-atualização

Antes de qualquer mudança: dump do banco (`gestao_convenios`, 73 tabelas), volume de storage do
Laravel (`deploy_gescon_storage`) e segredos (`api/.env` com o `APP_KEY`, `deploy/.secrets.env`),
salvos em `deploy/backups/*_pre_atualizacao_20260914_131234*` com o commit atual (`3d4c3f2`)
registrado à parte para recuperação de código. `gzip -t` e contagem de tabelas conferidos.

Em seguida, rodado também o `backup-gescon.sh` (o backup diário automático, 01:15) manualmente, pra
ter uma cópia off-server no Drive coincidindo com o momento exato pré-atualização, além da cópia
local já rotineira das 01:15.

## 1. Atualização do Git (`3d4c3f2` → `70e6659`)

Revisão do diff antes de aplicar: sem migrations novas, sem mudança em `docker-compose`/`deploy`/
dependências — só correções de aplicação (paginação que ignorava `itens_por_pagina` do tenant,
edição de usuário quebrando por link direto, ordenações faltando, e um bug de tenant scope em
`ConfiguracaoGlobal::doTenant()` que afetava o `AvaliarAlertasJob` do worker). Deploy via
`redeploy.sh`: só `gescon-app` recriado, `gescon-db` e `gescon-worker` intocados. `Nothing to
migrate`, 73 tabelas antes e depois, smoke test OK.

## 2. Execução #541 (item 2448, Larissa de Almeida Pereira): investigação da falha sem causa raiz

Mesma classe de falha não resolvida em 10/09 (item #2371, ver
`resumo-entregas-2026-09-10.md` §3): `browserContext.waitForEvent: Timeout 30000ms exceeded
while waiting for event "page"` em `abrirBeneficiario()` — o clique em "+ Novo Exame" "acontece",
mas a popup do `window.open` nunca chega a abrir, sem nenhum rastro do motivo.

**Fix de diagnóstico** (`worker-unimed/src/portal.js`, `gerarGuia.js` — commit `a42c49f`): os 4
pontos do worker com esse mesmo padrão (`abrirBeneficiario`, `abrirBuscaContratado`,
`abrirBuscaPrestador`, `uploadAnexo`) fatorados num helper `waitForPopup()` que, só quando o
timeout estoura, anexa ao erro qualquer diálogo nativo visto nesse meio tempo (hipótese mais forte:
um `confirm()` seria dispensado silenciosamente pelo Playwright por padrão, sem popup abrir) e o
texto visível da página. Caminho feliz sem mudança de comportamento — 29/29 testes.

**Efeito colateral do circuit breaker:** `WORKER_INTERNAL_FATAL` é erro "estrutural", então
`UnimedCircuitBreakerService` pausou a credencial Unimed do tenant inteiro (`ativo=false`) a cada
falha — travando a automação de todos os itens, não só o da Larissa. Reativado duas vezes ao longo
da sessão pelo mesmo caminho do endpoint oficial (`POST /configuracoes/unimed/reativar`, com
auditoria), a pedido do usuário depois de confirmado que é freio local nosso, não bloqueio da
Unimed.

## 3. Causa raiz real: restrição administrativa não reconhecida no plural

Com o diagnóstico do item 2, a *próxima* tentativa já revelou a causa de verdade na primeira
tentativa: a Unimed bloqueia esse beneficiário com **"Este beneficiário possui restrições
administrativas e não pode realizar atendimentos."** — texto real capturado pelo `waitForPopup()`
no ponto de falha (`abrirBuscaContratado`, tela de digitação SP/SADT).

O código já tinha uma checagem pra restrição administrativa (`textoRestricao()`), mas só cobria o
singular ("Restrição Administrativa") logo após abrir o beneficiário — o aviso real veio no plural
e numa tela mais adiante do fluxo, então passava batido e a automação quebrava com timeout técnico
em vez de parar com o motivo real.

**Fix** (commit `3d5ebcb`): regex ampliado para singular/plural de restrição e pendência
administrativa; segunda checagem adicionada logo após `abrirSpSadt()`, onde o aviso realmente
aparece. Novo cenário de teste (`restriction-sp-sadt`) reproduzindo a mensagem real. 30/30 testes.

Resultado depois do fix, verificado ao vivo contra o portal real (execução #551): guia local criada
com status `needs_verification`, sem número, sem quebrar — item ainda precisa de contato da clínica
com a Unimed pra regularizar o cadastro da beneficiária. **Isso não é algo que o gescon resolve
sozinho** — fica registrado como pendência externa, não como bug.

## 4. Modal de progresso da automação não mostrava o resultado de execuções "succeeded"

Consequência direta do item 3: a guia da Larissa ficou com `guia_status: needs_verification` mas a
execução em si terminou `status: succeeded` do ponto de vista técnico (o robô rodou até o fim sem
quebrar) — e o modal de acompanhamento (`AutomacaoProgressoModal.tsx`) mostrava só "Concluído com
sucesso / O robô concluiu a execução." pra qualquer status `succeeded`, sem olhar o resultado de
negócio.

**Fix** (commit `c2fe511`, só frontend): o modal agora lê `guia_status`/`numero_guia`/
`unimed_status`/`message`/`senha` do resultado (mesmos nomes usados pelas 4 operações — gerar guia,
confirmar guia incerta, consultar status, capturar senha/validade) e escolhe o tom certo
(sucesso/alerta/erro) por `guia_status`, não só pelo status técnico. `needs_verification` e `denied`
deixam de aparecer como "sucesso" genérico e passam a mostrar o motivo real, o número da guia, o
status no portal e a senha/validade quando existirem. `tsc`, `oxlint` e o verificador do design
system passam; lógica reconferida rodando a função real contra o JSON de produção da execução #551.

**Nota de suporte:** depois do deploy, uma verificação de status (execução #552) ainda apareceu com
o texto genérico antigo no navegador do usuário — não era bug, era o bundle JS antigo ainda em
memória numa aba aberta antes do deploy (confirmado conferindo o bundle publicado, que já tinha o
fix). `index.html` é servido `no-cache` de propósito (ver `deploy/nginx.conf`) exatamente pra isso
não precisar de instrução manual de cache — só precisa de um F5.

## 5. Execução #568 (item 2414): `PRESTADOR_NOME_AMBIGUO` mesmo com o CRM já confirmando o médico

O worker já tinha achado o médico certo pelo CRM no portal ("VOLNEI CORREA DA SILVA"), mas a
checagem de segurança que confirma o nome deu só 80% de similaridade contra o esperado ("VOLNEI
CORRÊA DA SILVA") — abaixo do limiar de 90% pra aceitar sozinho, caindo em confirmação manual sem
necessidade.

**Causa:** `compararNomes()`/`NomeMedicoNormalizer::similaridadeAproximada()` (worker e API,
precisam ficar em sincronia) filtravam conectores ("da", "de", "dos"...) do nome **candidato**
antes de comparar, mas não do nome **lido**. Os dois nomes têm um "da" no meio na mesma posição —
só um lado perdia a palavra, sobrando um "da" sem par pra casar, derrubando o score de 100 pra 80
mesmo a única diferença real sendo o acento em "Corrêa" (já removido antes da comparação).

**Fix** (commit `5c36169`): filtra conectores dos dois lados. Testes de regressão nos dois lugares
(worker e API); reprocessada a execução #568 contra o portal real depois do fix — guia gerada com
sucesso, médico confirmado por CRM sem pedir confirmação manual. 31/31 no worker, 568/568 no
backend.

## 6. Confirmar médico ambíguo direto no modal de Solicitações/Guias (não só em Automações)

Até então, resolver um `PRESTADOR_NOME_AMBIGUO` (como o do item 5) só dava pra fazer navegando até
Automações — o modal usado em Solicitações/Guias só mostrava o código do erro cru, sem ação
nenhuma.

**Fix** (commit `28341b1`): `AutomacaoProgressoModal.tsx` ganhou o mesmo bloco de confirmação que já
existia em `AutomacoesPage.tsx` (nome lido, sugestão do portal, % de similaridade, campo editável,
"Confirmar e tentar novamente"), extraído pra `medicoAmbiguo.ts` pra não duplicar a lógica entre as
duas telas. Confirmar atualiza o cadastro do médico e reprocessa a execução sem sair da tela — o
modal passa a acompanhar a nova execução no lugar da original por um id interno, transparente pra
quem abriu o modal. Bônus: botão genérico "Tentar novamente" pra qualquer outra falha
(`failed`/`needs_attention`).

## 7. Execução #535 (item 2454, Matheus Sa Silva): `UNCERTAIN_AFTER_SUBMIT` recorrente — causa raiz achada, correção fica só na Unimed

**Nota de correção:** ao longo da investigação o resumo verbal chamou o paciente de "Mariana dos
Santos" por engano — o modal mostra "paciente · especialidade · profissional", e **Mariana dos
Santos é a profissional executante**, não a paciente. O paciente/beneficiário é **Matheus Sa
Silva**. Confirmado na correção do item 1 da tabela de entregas (`AutomacaoExecucaoResource`)
rodada contra a execução real: `paciente_nome: "Matheus Sa Silva"`, `profissional_executante_nome:
"Mariana dos Santos"`.

Três tentativas reais no portal, cada uma com diagnóstico melhor que a anterior:

1. **1ª e 2ª tentativas:** `UNCERTAIN_AFTER_SUBMIT` no Finalizar. Diagnóstico genérico (`debug`, já
   existente desde a sessão de 10/09) capturou "O valor do campo Celular (SMS) é inválido", mas só o
   texto — sem dizer qual campo de verdade nem seu valor.
2. **Melhoria de diagnóstico** (commit `9dac4d3`): `capturarDiagnosticoResultado()` passa a extrair o
   rótulo citado em qualquer mensagem "O valor do campo X é inválido/obrigatório" e achar o
   input/select/textarea real ligado a ele (mesma linha de tabela, irmão direto, ou primeiro campo do
   mesmo pai). Puramente aditivo — não muda nenhum comportamento do fluxo. 32/32 testes.
3. **3ª tentativa**, já com o diagnóstico novo: revelou o campo real —
   `id="nr_fone"`, valor `"(47) 3135-0000"`. **Mas essa pista era enganosa**: uma investigação ao
   vivo mais cuidadosa (leitura do HTML real da tela, sem clicar Finalizar) achou que `nr_fone` é na
   verdade o campo **"Telefone"**, não "Celular (SMS)" — a heurística de achar o campo pelo rótulo
   tinha casado o rótulo errado numa tela com dois rótulos ("Telefone"/"Celular (SMS)") próximos.
   O campo real de "Celular (SMS)" é `nr_celular`, com valor `"(48) 99182-008"` — **8 dígitos, um a
   menos que o formato de celular atual (9 dígitos)**. A própria máscara do campo no portal chama
   isso de `celularNonoMask` ("máscara do nono dígito").

**Duas tentativas de contorno testadas ao vivo contra o portal real, ambas revertidas por não
funcionarem:**
- Limpar o campo e reenviar Finalizar (commit `67f2c56`, revertido em `394113a`): mesmo erro, campo
  vazio não passa — o campo é obrigatório com formato válido, não uma checagem condicional sobre
  valor presente.
- Mesma coisa incluindo um `Tab` real (blur) antes do segundo Finalizar, pra descartar qualquer
  estado de validação client-side preso (commit `206334c`, revertido em `026c4cd`): mesmo resultado.

**Conclusão final:** não é bug do gescon — o worker nunca preenche esse campo, ele já vem incompleto
do cadastro da beneficiária na Unimed. Preencher um número inventado só pra passar na validação foi
descartado de propósito (gravaria um dado de contato falso no cadastro oficial de um paciente real
num sistema de saúde). A única correção possível é no cadastro do Matheus na Unimed, completando o
celular pra 9 dígitos. Confirmado com segurança que nenhuma das 4 tentativas (2 normais + 2 com
contorno) duplicou guia nenhuma — item permanece `pending`, pronto pra reenvio assim que corrigido lá.

## 8. Seis melhorias de tela pedidas pelo usuário

Pedido único cobrindo Automações, Solicitações, Guias e Lançamentos (commit `12e2bf5`):

1. **Detalhe de `/automacoes/:id`**: mostra Paciente, Médico e Profissional executante — lidos das
   relações do banco (`guia`/`solicitacaoItem` com toda a cadeia carregada), não do payload bruto,
   porque só a operação `gerar_guia` carrega essas 3 informações no payload; as outras 3
   (`consultar_status`, `capturar_senha_validade`, `confirmar_guia_incerta`) não. Funciona igual pra
   qualquer operação agora.
2. **`/automacoes` e `/solicitacoes`**: filtro por ID (busca exata).
3. **`/guias`**: filtro por número da guia (busca parcial) — reaproveita o `numero_guia LIKE` que já
   existia no `busca` livre do modal de seleção de guia em Lançamentos, como campo dedicado.
4. **`/lancamentos`**: um único campo de busca cobrindo guia, profissional executante, paciente,
   médico ou id — médico só alcançável via `guia -> solicitacaoItem -> solicitacao -> medico` (Guia
   não tem médico direto).
5. **`/lancamentos` agrupado por Guia**: decisão do usuário — grupos **fechados por padrão**
   (expande ao clicar) e **paginação por guia**, não por sessão, pra uma guia com muitas sessões
   nunca ficar cortada entre duas páginas. Muda o formato de resposta de `GET /api/lancamentos` (de
   lista plana pra paginador de grupos) — único consumidor é `LancamentosPage.tsx`, conferido antes
   de mudar.
6. Removida a ordenação por coluna clicável de Lançamentos (`ColunaOrdenavel`): não fazia mais
   sentido com paginação por guia — cada grupo ordena pela sessão mais recente.

12 testes novos cobrindo os 6 itens (inclusive busca por médico via a cadeia completa de relações, e
paginação por guia garantindo que nenhuma sessão fica cortada entre páginas). Suíte completa:
575/575. Depois do deploy, conferido contra dados reais de produção: execução #535 retornando
`paciente_nome`/`medico_nome`/`profissional_executante_nome` corretos, e a consulta de agrupamento
achando a guia real (#14, 10 sessões) certinho.

## Validação

| Comando | Resultado |
|---|---|
| `worker-unimed`: `node --test` (imagem `mcr.microsoft.com/playwright:v1.62.1-jammy`) | 29/29 → 30/30 → 31/31 → 32/32 → 33/33 (contorno) → 32/32 (revertido), conforme cada fix |
| `api`: `php artisan test` via `deploy/run-tests.sh` | 568 → 571 → 575, sempre verde |
| `web`: `tsc -b`, `oxlint`, verificador do design system, build de produção | limpo (só os 8 warnings pré-existentes de `only-export-components`) |
| Verificação ao vivo (portal real `rda.unimedsc.com.br`) | execuções #551, #568, #571, #535 (×4) reprocessadas contra o portal de verdade; nenhuma duplicou guia |
| Verificação contra dados reais pós-deploy (§8) | `AutomacaoExecucaoResource` e agrupamento de Lançamentos conferidos com tinker contra o banco de produção |

Commits (`a42c49f`, `3d5ebcb`, `c2fe511`, `5c36169`, `28341b1`, `9dac4d3`, `67f2c56`+`394113a` revert,
`206334c`+`026c4cd` revert, `12e2bf5`) em produção via `deploy/redeploy.sh`, um deploy por fix — não
em lote — para isolar qual mudança causou o quê se algo desse errado no meio do caminho. Backup
pré-atualização em `deploy/backups/*_pre_atualizacao_20260914_131234*` e no Drive
(`gescon/2026-09-14`) como rede de segurança de toda a sessão.
