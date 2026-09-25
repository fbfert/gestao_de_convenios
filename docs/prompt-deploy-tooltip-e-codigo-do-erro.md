# Prompt de deploy na VPS — `tooltip-que-nao-entra-em-laco` + `codigo-de-erro-que-encontra-o-log`

> Para colar numa sessão do Claude Code rodando **na VPS**, dentro de `/opt/gescon`.
> Copie tudo abaixo da linha. Este prompt **substitui** o `prompt-deploy-codigo-do-erro.md`: as duas
> changes sobem juntas, porque nenhuma das duas foi para produção ainda.

---

Você está na VPS de produção do Gescon, em `/opt/gescon`. Vamos publicar duas changes de uma vez:

1. **`tooltip-que-nao-entra-em-laco`** — a correção do erro que derrubou a tela de Solicitações em 24 e
   25/09 (seis quedas, sempre ao passar o mouse na dica da coluna Info).
2. **`codigo-de-erro-que-encontra-o-log`** — o código da tela de erro passa a ser o mesmo do registro;
   o registro passa a identificar clínica e usuário; relato recusado deixa rastro.

**Existe um único tenant em produção (NeuroKids), com a automação da Unimed rodando em guias reais
todos os dias.** É bundle novo — quem estiver com a tela aberta só recebe a versão nova no próximo
carregamento. **Faça fora do horário de uso da clínica.**

Um passo por vez, com a saída na tela antes do próximo — não encadeie os passos.

## O que este deploy leva

| O quê | Detalhe |
|---|---|
| **Tooltip** | Mede a posição uma vez por abertura, em vez de realimentar a própria medição. Era um laço de renderização (erro 185 do React) em zoom/escala fracionária |
| **Código do erro** | O número da tela é o número do log (FNV-1a nos dois lados). Antes eram dois hashes diferentes |
| **Relato identificado** | O envio leva o token; `tenant_id` e `user_id` deixam de ser nulos |
| **Recusa com rastro** | Relato descartado pela validação vira `erro-cliente-recusado` no log, com motivos e tamanhos |
| **Migration** | **Nenhuma.** Só código e bundle |
| **Dependência nova** | Nenhuma |

**Códigos anotados entre 22 e 25/09 ficaram no formato antigo.** Para esses, o que localiza o registro é
a data e a hora. A novidade avisa a clínica.

## Passo 0 — Onde a VPS está

O intervalo de commits sai **daqui**, não do último commit conhecido de quem escreveu o prompt — foi
um erro de 23/09:

```bash
git -C /opt/gescon fetch origin
git -C /opt/gescon rev-parse --short HEAD
git -C /opt/gescon log --oneline HEAD..origin/main
git -C /opt/gescon status --short
docker ps --format '{{.Names}}\t{{.Status}}'
```

O `log HEAD..origin/main` lista o que vai subir. **Me mostre a lista antes de seguir**: precisa conter
`df02d3a` (hash unificado), `06e12df` (token), `c686d92` (recusa) e o commit do tooltip. Se listar
commits que você não reconhece, pare e me diga — subir mais do que o prompt descreve foi o erro de
23/09.

Se o `git status` mostrar alteração local não commitada, me mostre antes de qualquer pull: o
`redeploy.sh` faz `--ff-only` e vai falhar.

Guarde o estado de hoje do registro de erros:

```bash
docker exec gescon-app sh -c 'grep -c "erro-cliente" storage/logs/laravel.log'
docker exec gescon-app sh -c 'grep -c "erro-cliente-recusado" storage/logs/laravel.log'
```

O segundo tem que ser **0** — a etiqueta ainda não existe.

## Passo 1 — Backup

Este deploy **não escreve no banco**. O backup é o ponto de retorno anotado:

```bash
set -a; . /opt/gescon/deploy/.secrets.env; set +a
STAMP=pre_tooltip_codigo_erro_$(date +%Y%m%d_%H%M%S)
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

Me mostre a saída inteira. Além do HTTP 200: **o bundle servido tem de ser o da imagem nova.** As duas
correções são frontend; se o servido diferir do da imagem, nada disto está valendo.

## Passo 3 — O código bate dos dois lados

Dá para conferir sem esperar erro nenhum. Mande um relato conhecido e leia o código que o servidor grava:

```bash
docker exec gescon-app sh -c 'curl -s -o /dev/null -w "%{http_code}\n" \
  -X POST -H "Content-Type: application/json" \
  -d "{\"message\":\"insertBefore\",\"stack\":\"at Botao\"}" \
  http://127.0.0.1/api/erros-cliente'

docker exec gescon-app sh -c 'grep "erro-cliente" storage/logs/laravel.log | tail -1'
```

A primeira tem que voltar `204`, e a segunda tem que conter **`"codigo":"D35CEE"`** — é o valor de
referência fixado nos testes dos dois lados. **Outro valor significa que os lados voltaram a divergir:
pare e me diga.**

## Passo 4 — Recusa deixa rastro

```bash
docker exec gescon-app sh -c 'curl -s -o /dev/null -w "%{http_code}\n" \
  -X POST -H "Content-Type: application/json" \
  -d "{\"stack\":\"relato sem mensagem\"}" \
  http://127.0.0.1/api/erros-cliente'

docker exec gescon-app sh -c 'grep "erro-cliente-recusado" storage/logs/laravel.log | tail -1'
```

`422` na primeira; a segunda existe, com `"motivos"` e `"tamanhos"` (`message` = `"ausente"`). **A
pilha recusada NÃO pode aparecer no log** — só o tamanho dela.

## Passo 5 — Limpar o cache e conferir pela interface

```bash
docker exec gescon-app php artisan cache:clear
```

> Não rode `config:cache` nem `route:cache` à mão: o `entrypoint.sh` cuida do primeiro, e o segundo
> quebra o app (há rotas com Closure em `web.php`).

Logado como o admin da NeuroKids — **esta é a conferência que importa neste deploy:**

1. Abra **Solicitações**, filtre por um paciente (o mesmo `?paciente=…` que derrubava a tela).
2. Passe o mouse no **"?" da coluna Info** de uma linha. A dica abre e a tela continua de pé.
3. Repita com o navegador em **zoom 110%** e depois **125%** (Ctrl e +). Era nessa condição que caía.
4. Se a máquina da clínica tiver a escala do Windows em 125%, é o cenário exato: teste nela se puder.
5. **A novidade apareceu**: "A tela de Solicitações parou de cair ao passar o mouse na dica".
6. `/tooltip-na-borda` e `/erro-simulado` **não existem em produção**: digitados na barra, caem no
   painel. Se aparecer outra coisa, o bundle foi buildado no modo errado — pare e me diga.

## Passo 6 — O próximo erro chega identificado

Não há como forçar um erro de propósito. Na próxima vez que houver um:

```bash
docker exec gescon-app sh -c 'grep "erro-cliente" storage/logs/laravel.log | tail -3 | cut -c1-400'
```

Num registro **depois** deste deploy, `"tenant_id"` tem que ser `1` e `"user_id"` o da pessoa — não
`null` como nos de 24 e 25/09. Os relatos de teste dos Passos 3 e 4 continuam anônimos, porque saem
do `curl` sem sessão: esperado.

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

Reverter devolve o tooltip que derruba a tela — vale lembrar antes de fazer isso por um problema
cosmético.

## Regras para esta sessão

- Um passo por vez, com a saída na tela antes do próximo
- **Fora do horário de uso da clínica**: é bundle novo
- Nunca imprima senha, `APP_KEY` ou conteúdo de `.secrets.env`
- Não rode `migrate` nem `migrate:rollback`: estas changes não têm migration
- Não rode `route:cache` (há rotas com Closure em `web.php`)
- Se algo divergir do esperado, **pare e me diga** em vez de tentar corrigir por conta própria

## Executado em

_(preencher depois da execução: o que bateu, o que divergiu, e o que ficou pendente.)_
