# Prompt de deploy na VPS — change `tela-de-erro-em-vez-de-tela-branca`

> Para colar numa sessão do Claude Code rodando **na VPS**, dentro de `/opt/gescon`.
> Copie tudo abaixo da linha.

---

Você está na VPS de produção do Gescon, em `/opt/gescon`. Vamos publicar a change
`tela-de-erro-em-vez-de-tela-branca`: cinco commits, a partir de `eb6ff2f`.

**Existe um único tenant em produção (NeuroKids), com a automação da Unimed rodando em guias reais
todos os dias.** Quebrar isso custa atendimento de paciente. **Faça este deploy fora do horário de
uso da clínica**: é bundle novo, e quem estiver com a tela aberta só recebe a versão nova no próximo
carregamento.

Um passo por vez, com a saída na tela antes do próximo — não encadeie os passos.

## O que este deploy leva

A clínica vê a tela ficar branca "do nada", às vezes no meio de um lançamento. Eram duas falhas
empilhadas, e as duas são corrigidas aqui.

| O quê | Detalhe |
|---|---|
| **Tela de erro** | Falha de renderização passa a mostrar uma tela que explica, em vez de apagar a página |
| **Erro do navegador vira log** | `POST /erros-cliente`, público e com throttle. Até agora nenhuma queda deixava rastro |
| **`Botao` à prova de tradutor** | O rótulo passa a viver num `<span>` — era o gatilho reproduzido |
| **Migration** | **Nenhuma.** Só código e bundle |
| **Dependência nova no frontend** | `react-error-boundary`. Entra pelo `npm ci` do build da imagem — **não há passo manual** |
| **Dependência nova no backend** | Nenhuma |

### Por que a tela ficava branca

`main.tsx`, `App.tsx` e `AppRoutes.tsx` montavam a árvore inteira **sem nenhum `ErrorBoundary`**.
Qualquer exceção durante uma renderização fazia o React desmontar o `#root` — página literalmente em
branco. E como também não havia captura de erro do navegador, **produção nunca registrou uma única
dessas quedas**.

O gatilho mais comum era o tradutor do navegador, que embrulha cada texto em `<font><font>…</font>`.
O `Botao` renderizava o spinner **antes** do rótulo, então o clique seguinte em qualquer botão com
estado de carregamento fazia o React chamar `insertBefore(spinner, textoDoRótulo)` num texto que já
não era filho do botão — `NotFoundError`, tela branca.

O commit `138ba6c` (15/09) já pôs `translate="no"` no `index.html`, o que barrou o tradutor nativo do
Chrome e do Edge. Não barrava extensões, e não fechava o buraco estrutural.

### O que muda para quem já usa o sistema

- **Nada no fluxo de trabalho.** Nenhuma tela nova, nenhum campo novo, nenhuma permissão nova.
- **Quando algo falhar**, em vez da página em branco aparece a tela de erro com um código curto. A
  clínica precisa saber disso: **o código é o que o suporte pede.**
- **Uma novidade** no painel anuncia a correção.

## Passo 0 — Onde a VPS está

```bash
git -C /opt/gescon log --oneline -3
git -C /opt/gescon status --short
docker ps --format '{{.Names}}\t{{.Status}}'
```

O primeiro commit tem que ser `eb6ff2f` (ou posterior). Se o `git status` mostrar alteração local não
commitada, me mostre antes de qualquer pull: o `redeploy.sh` faz `--ff-only` e vai falhar.

E confirme que a rota nova ainda **não** existe:

```bash
docker exec gescon-app php artisan route:list --path=erros-cliente
```

Tem que sair vazio. Se já aparecer, a change subiu; **pare e me diga**.

## Passo 1 — Backup

Este deploy **não escreve no banco** — não há migration. O backup é mais leve que o de sempre, mas
não é dispensável: o rollback de código é mais tranquilo com um ponto de retorno anotado.

```bash
set -a; . /opt/gescon/deploy/.secrets.env; set +a
STAMP=pre_tela_de_erro_$(date +%Y%m%d_%H%M%S)
mkdir -p /opt/gescon/deploy/backups
cd /opt/gescon/deploy/backups

docker exec gescon-db mariadb-dump -u root -p"$DB_ROOT" \
  --single-transaction --routines --triggers gestao_convenios \
  | gzip > "${STAMP}_gestao_convenios.sql.gz"

git -C /opt/gescon rev-parse HEAD | tee "${STAMP}_commit_antes.txt"
```

Conferência:

```bash
ls -la /opt/gescon/deploy/backups/${STAMP}_*
gzip -t "${STAMP}_gestao_convenios.sql.gz" && echo "dump integro"
```

**Me mostre as duas saídas antes de seguirmos.**

## Passo 2 — Deploy

```bash
/opt/gescon/deploy/redeploy.sh
```

Me mostre a saída inteira. Duas coisas para olhar, além do HTTP 200:

- **O bundle servido é o da imagem nova.** Este deploy é essencialmente frontend: se o script disser
  que o servido difere do da imagem, a correção **não está valendo**, mesmo com HTTP 200.
- **A dependência nova entrou.** O build roda `npm ci`; se `react-error-boundary` não instalar, o
  build falha antes de gerar imagem — então um build bem-sucedido já é a prova.

## Passo 3 — A rota de registro está no ar

```bash
docker exec gescon-app php artisan route:list --path=erros-cliente
```

Tem que aparecer `POST api/erros-cliente`. E ela responde **sem autenticação**, que é o ponto:

```bash
docker exec gescon-app sh -c 'curl -s -o /dev/null -w "%{http_code}\n" \
  -X POST -H "Content-Type: application/json" \
  -d "{\"message\":\"teste de deploy\"}" \
  http://127.0.0.1/api/erros-cliente'
```

**Tem que voltar `204`.** Se vier 403, a rota não foi inscrita em `ExigeAutorizacaoDeclarada` — pare
e me diga. Se vier 419 ou 401, algo no middleware mudou.

E o registro chegou ao log:

```bash
docker exec gescon-app grep -c "erro-cliente" storage/logs/laravel.log
docker exec gescon-app sh -c 'grep "erro-cliente" storage/logs/laravel.log | tail -1'
```

A linha tem que conter `"message":"teste de deploy"` e um `"codigo"` de seis caracteres.

## Passo 4 — Limpar o cache de aplicação

A novidade fica em cache por uma hora:

```bash
docker exec gescon-app php artisan cache:clear
```

> Não rode `config:cache` nem `route:cache` à mão: o `entrypoint.sh` já cuida do primeiro, e o
> segundo quebra o app (há rotas com Closure em `web.php`).

## Passo 5 — Conferência pela interface

Logado como o admin da NeuroKids:

1. **O sistema funciona normalmente.** Abra o painel, Guias e Sessões. Nada deve ter mudado de
   aparência — a correção do `Botao` é invisível.
2. **Clique num botão que mostra "carregando"** — Salvar em qualquer formulário, ou Sair. Deve
   funcionar como sempre.
3. **A novidade apareceu.** No painel, o card de Novidades traz "Tela de erro em vez de tela branca".
4. **A rota de erro simulado NÃO existe em produção.** Digite `/erro-simulado` na barra de endereço:
   deve cair no painel ou em rota inexistente, **nunca** numa tela de erro. Ela só existe no modo de
   teste; se aparecer, o bundle foi buildado no modo errado — **pare e me diga**.

### Se quiser ver a tela de erro funcionando

Não há como forçá-la em produção de propósito (é justamente o ponto). A prova de que ela existe é o
bundle conter o boundary, e isso o build garante. O comportamento está coberto por teste e2e.

## Passo 6 — A automação continua de pé

Este deploy não toca em automação, mas ela é o que não pode parar:

```bash
docker ps --format '{{.Names}}\t{{.Status}}' | grep gescon-worker
docker exec gescon-app tail -40 storage/logs/laravel.log

docker exec gescon-db mariadb -u root -p"$DB_ROOT" gestao_convenios \
  -e "SELECT convenio_id, ativo, automation_paused_at FROM convenio_credenciais WHERE tenant_id = 1;"
```

E, no painel, o card de saúde dos componentes deve continuar verde.

## Passo 7 — Uma semana depois, olhe o log

Este é o passo que dá retorno de verdade sobre a change. Daqui a alguns dias:

```bash
docker exec gescon-app sh -c 'grep -c "erro-cliente" storage/logs/laravel.log'
docker exec gescon-app sh -c 'grep "erro-cliente" storage/logs/laravel.log | tail -20'
```

O que estava invisível até agora passa a estar ali. Se aparecer o mesmo `codigo` repetidas vezes, é
um erro real da clínica que vale investigar — e é a primeira vez que temos essa informação.

## Rollback

Não há migration, então o rollback é só código:

```bash
git -C /opt/gescon checkout $(cat /opt/gescon/deploy/backups/<STAMP>_commit_antes.txt)
/opt/gescon/deploy/redeploy.sh
```

Isso devolve a tela branca junto — vale lembrar disso antes de reverter por um problema cosmético.

## Regras para esta sessão

- Um passo por vez, com a saída na tela antes do próximo
- **Fora do horário de uso da clínica**: é bundle novo
- Nunca imprima senha, `APP_KEY` ou conteúdo de `.secrets.env`
- Não rode `migrate` nem `migrate:rollback`: esta change não tem migration
- Não rode `route:cache` (há rotas com Closure em `web.php`)
- Se algo divergir do esperado, **pare e me diga** em vez de tentar corrigir por conta própria

## Executado em 22/09/2026

Rodado numa sessão do Claude Code na própria VPS, um passo por vez como pedido.

- **Antes do Passo 0**, o prompt colado na sessão era o errado — o de
  `automacao-unimed-finalizar-guia` + `conferir-guias-finalizadas-unimed`, que já tinha rodado hoje
  de manhã (o backup `pre_unimed_finalizar_conferir_20260922_005227_*` já existia, e as duas colunas
  que o Passo 0 daquele prompt esperava ver em 0 já estavam em 1). Identificado antes de qualquer
  ação e parado para pedir o prompt certo — nada foi executado a partir daquele engano.
- Passo 0 deste prompt bateu: HEAD em `eb6ff2f` (o esperado), `git status` limpo, rota
  `erros-cliente` ainda inexistente.
- **Horário do deploy**: o relógio da VPS marcava ~17:54 em Brasília, dentro do expediente provável
  da NeuroKids — o prompt pede fora do horário de uso. Perguntei; a decisão foi seguir mesmo assim.
- Passos 1 a 4 bateram sem ressalvas: backup íntegro (`pre_tela_de_erro_20260922_205506_*`), `git
  pull` trouxe `eb6ff2f..e39be99`, `npm ci` instalou `react-error-boundary` sem passo manual, build
  ok, smoke test HTTP 200 com bundle novo (`index-BK3X3K2d.js`), rota `POST api/erros-cliente`
  respondeu 204 sem autenticação e o log capturou o teste (`codigo: C00709`), cache limpo.
- Passo 5 (conferência pela interface) feito pelo usuário fora desta sessão — confirmado como ok.
- Passo 6 bateu: `gescon-worker` saudável, credencial Unimed do tenant 1 sem pausa do disjuntor
  (`ativo=1`, `automation_paused_at=NULL`).
- **Achado à parte, não relacionado a este deploy**: no log havia um erro repetido em
  `ClinicaSyncController::confirmarPushPendencia` (`Duplicate entry` em
  `pacientes_tenant_id_clinica_id_unique`, `userId 4`), com timestamp **anterior** ao deploy
  (20:27 UTC, deploy rodou 20:56 UTC) — pré-existente, não bloqueou nada, mas fica registrado para
  investigar depois.
- **Pendente**: Passo 7 (revisar `storage/logs/laravel.log` por `erro-cliente` daqui a alguns dias,
  para ver se algum código de erro real se repete).
