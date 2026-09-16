# Prompt de deploy na VPS — 16/09/2026

> Para colar numa sessão do Claude Code rodando **na VPS**, dentro de `/opt/gescon`.
> Copie tudo abaixo da linha.

---

Você está na VPS de produção do Gescon, em `/opt/gescon`. Vamos publicar dois commits.

**Existe um único tenant em produção (NeuroKids), com a automação da Unimed rodando em guias reais
todos os dias.** Um passo por vez, com a saída na tela antes do próximo.

## O que este deploy leva

| Commit | O quê |
|---|---|
| `abd28ca` | O item **Novidades** sai do menu. A rota `/novidades` continua, e o card do painel leva até ela. |
| `677da0e` | O histórico de **Antecipações** passa a mostrar as guias geradas, com link. O botão **Excluir** e a rota `DELETE /api/antecipacoes/{id}` saem. |

**Nenhuma migration, nenhuma mudança de schema, `.env` ou dependência.** É deploy de aplicação: só
código e bundle.

Uma consequência que vale registrar antes: **`DELETE /api/antecipacoes/{id}` deixa de existir** e
passa a responder 405. Ela apagava o registro do histórico sem desfazer o item nem a guia gerados —
sumia a prova e ficavam os efeitos. Pelo que se viu no código, só a própria tela a chamava; se
existir algum script ou integração sua que a use, me diga antes de seguir.

## Passo 0 — Onde a VPS está

```bash
git -C /opt/gescon log --oneline -1
git -C /opt/gescon status --short
docker ps --format '{{.Names}}\t{{.Status}}'
```

Agora a pergunta que decide o resto:

```bash
git -C /opt/gescon merge-base --is-ancestor b499f73 HEAD && echo "JA TEM a change de credenciais" || echo "NAO TEM a change de credenciais"
```

- **"JA TEM"** → siga para o passo 1. O deploy é leve, sem migrations.
- **"NAO TEM"** → **PARE.** A change `credenciais-por-convenio` ainda não subiu, e ela mexe em
  credencial de automação com migração de dados. Use antes o roteiro de
  `docs/prompt-deploy-credenciais-por-convenio.md`, que tem backup e ensaio da migração. Só depois
  volte aqui.

Se o `git status` mostrar alteração local não commitada em `/opt/gescon`, me mostre antes de
qualquer pull — o `redeploy.sh` faz `--ff-only` e vai falhar.

## Passo 1 — Ponto de retorno

Sem migration, o rollback é voltar o código. Para isso basta guardar onde estamos:

```bash
git -C /opt/gescon rev-parse HEAD | tee /opt/gescon/deploy/backups/commit_antes_20260916.txt
```

Um dump do banco é barato e não custa nada ter, mas **não é o que salva aqui**: nada neste deploy
escreve no banco. O que salva é o commit acima.

## Passo 2 — Deploy

```bash
/opt/gescon/deploy/redeploy.sh
```

Me mostre a saída inteira. O script faz `git pull --ff-only origin main` → build → `up -d` → smoke
test, e confere três coisas além do HTTP 200: identidade da SPA, hash do bundle servido contra o da
imagem, e o estado do `gescon-worker`.

**Olhe o bundle.** É um deploy de frontend: se a linha do bundle disser que o servido é diferente do
da imagem, o container no ar não é o que acabou de ser buildado, e nenhuma das duas mudanças está
valendo — mesmo com HTTP 200.

## Passo 3 — Conferência

Pela interface, logado:

1. **O menu não tem mais "Novidades".** O card de Novidades no painel continua lá e leva à página; a
   URL `/novidades` também continua abrindo direto.
2. **Antecipações → Histórico:** cada linha gerada mostra `Guias: <número>` com link para a guia.
   Clique num deles e confirme que abre a guia certa.
   - Item de convênio automatizado cujo pedido ainda não voltou da operadora aparece como
     `aguardando a operadora`, com a especialidade. **Isso não é erro** — é o item existindo antes
     da guia.
3. **O botão Excluir sumiu** das linhas do histórico.

Uma conferência rápida pela API, se quiser confirmar que a rota saiu:

```bash
curl -s -o /dev/null -w "%{http_code}\n" -X DELETE \
  https://gescon.gestaonossa.com.br/api/antecipacoes/1
```

401 ou 405 — qualquer um dos dois serve. O que **não** pode vir é 204.

## Rollback

Nada a restaurar no banco:

```bash
git -C /opt/gescon checkout $(cat /opt/gescon/deploy/backups/commit_antes_20260916.txt)
/opt/gescon/deploy/redeploy.sh
```

## Regras para esta sessão

- Um passo por vez, com a saída na tela antes do próximo
- Nunca imprima senha, `APP_KEY` ou conteúdo de `.secrets.env`
- Não rode migration, `migrate:fresh`, `migrate:rollback` nem `db:wipe` — este deploy não tem
  migration nenhuma, então qualquer uma dessas seria fora de escopo
- Se algo divergir do esperado, **pare e me diga** em vez de tentar corrigir por conta própria
