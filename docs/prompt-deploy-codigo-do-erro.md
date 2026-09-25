# Prompt de deploy na VPS — change `codigo-de-erro-que-encontra-o-log`

> **SUBSTITUÍDO em 25/09/2026** por `prompt-deploy-tooltip-e-codigo-do-erro.md`, que sobe esta change
> junto com a correção do tooltip que derrubava Solicitações. Use aquele. Este fica como registro.

> Para colar numa sessão do Claude Code rodando **na VPS**, dentro de `/opt/gescon`.
> Copie tudo abaixo da linha.

---

Você está na VPS de produção do Gescon, em `/opt/gescon`. Vamos publicar a change
`codigo-de-erro-que-encontra-o-log`: quatro commits, a partir de `8171f8e`.

**Existe um único tenant em produção (NeuroKids), com a automação da Unimed rodando em guias reais
todos os dias.** É bundle novo, então quem estiver com a tela aberta só recebe a versão nova no
próximo carregamento — **faça fora do horário de uso da clínica**.

Um passo por vez, com a saída na tela antes do próximo — não encadeie os passos.

## O que este deploy leva

A tela de erro subiu em 22/09 e em **24/09 registrou o primeiro erro real**, na tela de Solicitações.
Os três defeitos apareceram de uma vez, e os três são consertados aqui.

| O quê | Detalhe |
|---|---|
| **Código do erro** | O número da tela passa a ser o número do registro. Eram dois hashes diferentes |
| **Relato identificado** | O envio passa a levar o token, e o registro ganha `tenant_id` e `user_id` |
| **Recusa deixa rastro** | Relato descartado pela validação passa a ser registrado, com o motivo |
| **Migration** | **Nenhuma.** Só código e bundle |
| **Dependência nova** | Nenhuma, nem no frontend nem no backend |

### Os três defeitos, para saber o que conferir

**O código não achava nada.** A clínica leu `836920` na tela; o log tinha `5F6052` para o mesmo erro.
A tela usava FNV-1a, o servidor sha256, o cliente nunca mandava o código que exibiu e o servidor não
o guardava. O código existe só para casar o telefonema com a linha do registro.

**O registro saía anônimo.** Os três relatos de 24/09 vieram de `/solicitacoes` — usuário logado — e
gravaram `tenant_id: null`. O envio usa `fetch` cru para não depender do axios, e o token nunca foi
anexado.

**Recusa era silenciosa.** Relato fora dos limites virava 422 e nada mais. Um erro que a clínica viu
podia desaparecer sem sinal algum.

### O que muda para quem usa

- **Nada no fluxo de trabalho.** Nenhuma tela nova, nenhum campo novo, nenhuma permissão nova.
- **O código da tela passa a valer.** Continua sendo o que o suporte pede.
- **Uma novidade** no painel explica, e avisa que códigos anotados de 22 a 24/09 ficaram no formato
  antigo — para esses, o que localiza o registro é a data e a hora.

## Passo 0 — Onde a VPS está

```bash
git -C /opt/gescon log --oneline -3
git -C /opt/gescon status --short
docker ps --format '{{.Names}}\t{{.Status}}'
```

O primeiro commit tem que ser `8171f8e` ou posterior. Se o `git status` mostrar alteração local não
commitada, me mostre antes de qualquer pull: o `redeploy.sh` faz `--ff-only` e vai falhar.

E **guarde o estado de hoje do registro de erros**, que é a referência do Passo 5:

```bash
docker exec gescon-app sh -c 'grep -c "erro-cliente" storage/logs/laravel.log'
docker exec gescon-app sh -c 'grep -c "erro-cliente-recusado" storage/logs/laravel.log'
```

O segundo tem que ser **0** — a etiqueta ainda não existe. Se já vier maior que zero, a change subiu;
**pare e me diga**.

## Passo 1 — Backup

Este deploy **não escreve no banco**. O backup é mais leve, mas o ponto de retorno anotado é o que
torna o rollback tranquilo:

```bash
set -a; . /opt/gescon/deploy/.secrets.env; set +a
STAMP=pre_codigo_do_erro_$(date +%Y%m%d_%H%M%S)
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

Me mostre a saída inteira. Além do HTTP 200: **o bundle servido tem de ser o da imagem nova.** Metade
desta correção é frontend — se o servido diferir do da imagem, o código da tela continua o antigo.

## Passo 3 — O código bate dos dois lados

É a conferência mais importante, e dá para fazer sem esperar erro nenhum. Mande um relato conhecido e
confira o código que o servidor grava:

```bash
docker exec gescon-app sh -c 'curl -s -o /dev/null -w "%{http_code}\n" \
  -X POST -H "Content-Type: application/json" \
  -d "{\"message\":\"insertBefore\",\"stack\":\"at Botao\"}" \
  http://127.0.0.1/api/erros-cliente'

docker exec gescon-app sh -c 'grep "erro-cliente" storage/logs/laravel.log | tail -1'
```

A primeira linha tem que voltar `204`, e a segunda tem que conter **`"codigo":"D35CEE"`**.

`D35CEE` é o valor que a implementação do navegador produz para essa mensagem e essa pilha — está
fixado nos testes dos dois lados. **Se vier outro valor, os lados voltaram a divergir: pare e me
diga.**

## Passo 4 — Recusa deixa rastro

```bash
docker exec gescon-app sh -c 'curl -s -o /dev/null -w "%{http_code}\n" \
  -X POST -H "Content-Type: application/json" \
  -d "{\"stack\":\"relato sem mensagem\"}" \
  http://127.0.0.1/api/erros-cliente'

docker exec gescon-app sh -c 'grep "erro-cliente-recusado" storage/logs/laravel.log | tail -1'
```

A primeira tem que voltar `422`, e a segunda tem que existir, com `"motivos"` e `"tamanhos"`. Dentro
de `tamanhos`, `message` tem que ser `"ausente"`.

**Confira também que a pilha recusada NÃO aparece no log** — o registro guarda o tamanho de cada
campo, nunca o conteúdo. Se aparecer texto do payload ali, pare e me diga.

## Passo 5 — Limpar o cache e conferir pela interface

```bash
docker exec gescon-app php artisan cache:clear
```

> Não rode `config:cache` nem `route:cache` à mão: o `entrypoint.sh` cuida do primeiro, e o segundo
> quebra o app (há rotas com Closure em `web.php`).

Logado como o admin da NeuroKids:

1. **O sistema funciona normalmente.** Painel, Guias, Solicitações e Sessões. Nada muda de aparência.
2. **A novidade apareceu**: "O código do erro agora encontra o registro".
3. **`/erro-simulado` continua não existindo em produção.** Digite na barra de endereço: deve cair no
   painel, **nunca** numa tela de erro. Se aparecer, o bundle foi buildado no modo errado — pare e me
   diga.

## Passo 6 — O relato passa a dizer de qual clínica veio

Este é o defeito que só aparece com uso real: o registro do erro tem de sair com `tenant_id`. Não há
como forçar um erro de propósito, então a conferência é **na próxima vez que acontecer um**:

```bash
docker exec gescon-app sh -c 'grep "erro-cliente" storage/logs/laravel.log | tail -5'
```

Num erro registrado **depois** deste deploy, `"tenant_id"` tem de ser `1` e `"user_id"` o da pessoa —
e não `null` como nos de 24/09. Os relatos de teste dos Passos 3 e 4 continuam anônimos, porque saem
do `curl` sem sessão: isso é o esperado.

## Passo 7 — A automação continua de pé

```bash
docker ps --format '{{.Names}}\t{{.Status}}' | grep gescon-worker
docker exec gescon-app tail -40 storage/logs/laravel.log

docker exec gescon-db mariadb -u root -p"$DB_ROOT" gestao_convenios \
  -e "SELECT convenio_id, ativo, automation_paused_at FROM convenio_credenciais WHERE tenant_id = 1;"
```

E, no painel, o card de saúde dos componentes deve continuar verde.

## Rollback

Sem migration, é só código:

```bash
git -C /opt/gescon checkout $(cat /opt/gescon/deploy/backups/<STAMP>_commit_antes.txt)
/opt/gescon/deploy/redeploy.sh
```

Reverter devolve o código que não encontra nada e o registro anônimo — vale lembrar antes de fazer
isso por um problema cosmético.

## Regras para esta sessão

- Um passo por vez, com a saída na tela antes do próximo
- **Fora do horário de uso da clínica**: é bundle novo
- Nunca imprima senha, `APP_KEY` ou conteúdo de `.secrets.env`
- Não rode `migrate` nem `migrate:rollback`: esta change não tem migration
- Não rode `route:cache` (há rotas com Closure em `web.php`)
- Se algo divergir do esperado, **pare e me diga** em vez de tentar corrigir por conta própria

## Executado em

_(preencher depois da execução: o que bateu, o que divergiu, e o que ficou pendente.)_
