# Prompt de deploy na VPS — 16/09/2026 (antecipações + leitura de sessões)

> Para colar numa sessão do Claude Code rodando **na VPS**, dentro de `/opt/gescon`.
> Copie tudo abaixo da linha.

---

Você está na VPS de produção do Gescon, em `/opt/gescon`. Vamos publicar cinco commits.

**Existe um único tenant em produção (NeuroKids), com a automação da Unimed rodando em guias reais
todos os dias.** Um passo por vez, com a saída na tela antes do próximo.

## O que este deploy leva

| Commit | O quê |
|---|---|
| `caec649` | **Antecipações**: Ignorar passa a confirmar (com motivo opcional), *Ignoradas* podem ser desfeitas, e o histórico ganha busca por paciente, guia, convênio e período — com página e filtros no endereço da página. |
| `1414404` | **Sessões**: botão **Usar webcam** na leitura do registro, com conferência da foto antes de enviar. |
| `931f647` | **Sessões**: a leitura libera **antes** de escolher guia e executante, e o número de guia lido passa a escolher a guia. |
| `4d1f5bd` | **Sessões**: conferência do paciente entre a folha lida e a guia escolhida, com aviso fixo e justificativa registrada quando não fecham. |
| `542c5a9` | Duas novidades anunciando o que está acima. |

**Nenhuma migration, nenhuma mudança de schema, `.env` ou dependência.** É deploy de aplicação: só
código e bundle. Conferido com `git diff 14a2a86..542c5a9 --name-only` — não há arquivo em
`api/database/migrations/`, nem `composer.json`, `package.json` ou `.env`.

### Duas rotas novas, nenhuma removida

- `POST /api/lancamentos/ler-registro` — leitura sem guia no caminho.
- `DELETE /api/antecipacoes/{id}/ignorada` — desfaz uma dispensa.

A rota antiga `POST /api/guias/{guia}/lancamentos/ler-registro` **continua existindo**, de
propósito: a API e o bundle não sobem no mesmo instante, e um navegador com o front antigo em cache
chamaria a rota velha. Ela será removida num deploy futuro.

`DELETE /api/antecipacoes/{id}` (sem `/ignorada`) **continua não existindo** — o 405 do deploy
anterior segue valendo.

## Passo 0 — Onde a VPS está

```bash
git -C /opt/gescon log --oneline -1
git -C /opt/gescon status --short
docker ps --format '{{.Names}}\t{{.Status}}'
```

A pergunta que decide o resto:

```bash
git -C /opt/gescon merge-base --is-ancestor 14a2a86 HEAD && echo "BASE OK" || echo "BASE FALTANDO"
```

- **"BASE OK"** → siga para o passo 1.
- **"BASE FALTANDO"** → **PARE e me diga.** A VPS está atrás do deploy de 16/09 (`abd28ca` +
  `677da0e`), e este roteiro assume aqueles dois já no ar.

Se o `git status` mostrar alteração local não commitada, me mostre antes de qualquer pull — o
`redeploy.sh` faz `--ff-only` e vai falhar.

## Passo 1 — Ponto de retorno

Sem migration, o rollback é voltar o código:

```bash
git -C /opt/gescon rev-parse HEAD | tee /opt/gescon/deploy/backups/commit_antes_20260916b.txt
```

Nada neste deploy escreve no banco durante a publicação. O que salva é o commit acima.

## Passo 2 — Deploy

```bash
/opt/gescon/deploy/redeploy.sh
```

Me mostre a saída inteira. O script faz `git pull --ff-only origin main` → build → `up -d` → smoke
test.

**Olhe a linha do bundle.** É um deploy com bastante frontend: se disser
`bundle -> SERVIDO ... != IMAGEM ...`, o container no ar não é o que acabou de ser buildado e
**nenhuma** destas mudanças está valendo, mesmo com HTTP 200. Confira também
`gescon-worker -> Up`.

## Passo 3 — Limpar o cache das novidades

As novidades são lidas de arquivo e **cacheadas por 1 hora** (`CACHE_STORE` é `database`, então o
cache sobrevive ao redeploy). Sem isto, as duas entradas novas podem demorar até uma hora para
aparecer no painel:

```bash
docker exec gescon-app php artisan cache:forget novidades.listagem
```

Se responder que a chave não existia, está igualmente certo — quer dizer que ninguém abriu o painel
desde o restart.

## Passo 4 — Conferência

Pela interface, logado.

### Antecipações

1. **Elegíveis:** clique no **nome do paciente** de uma entrada — abre a origem (pedido, médico,
   CIDs, itens com guia). Feche: nada deve ser gerado nem dispensado.
2. **Ignorar** abre uma confirmação com paciente, convênio e data prevista, e um campo de motivo.
   Cancele: a entrada continua na lista.
3. No **Histórico**, uma linha *Ignorada* tem o botão **Desfazer**, que pede confirmação e devolve a
   solicitação aos elegíveis. Linha *Gerada* **não** tem esse botão.
4. A **busca** filtra por paciente, nº de guia, convênio e período. Aplique um filtro, abra uma guia
   pelo link e volte pelo navegador: **os filtros e a página devem voltar como estavam**.
5. A **lupa** ao lado de "Paciente · Convênio" mostra o detalhe da ação.

### Sessões → Nova

6. Com a tela recém-aberta, **sem escolher guia nem executante**, os botões
   **"Ler foto ou PDF do registro"** e **"Usar webcam"** já devem estar habilitados. O
   "Analisar texto colado" continua exigindo guia e executante — isso é o esperado.
7. **"Usar webcam" deve aparecer.** Se não aparecer, confirme que está em `https://` — o navegador
   só libera a câmera em endereço seguro. Tire uma foto: ela **congela** para conferência, com
   "Usar esta foto" e "Tirar outra". "Tirar outra" volta a imagem ao vivo.
8. Leia uma folha real. **Este é o teste que importa**, e é o único que não pôde ser automatizado:
   - a IA leu o **número da guia**? A guia foi escolhida sozinha, com o aviso "Escolhida pela
     leitura"?
   - o número veio errado? Veja se abriu a **busca já preenchida** com o número lido.
   - apareceu o aviso **"A folha não confere com esta guia"** numa folha legítima? **Anote e me
     avise** — quer dizer que a carteirinha do cadastro não corresponde ao número impresso na folha,
     e a regra precisa de ajuste.
9. Escolhendo uma guia que contradiga a folha, confirmar as sessões deve abrir a janela pedindo
   **justificativa por escrito** (mínimo 10 caracteres). Cancelar não grava nada.

### Pacientes (não mudou de comportamento, mas mexeu no código)

10. Em **Pacientes → novo**, o botão **"Usar webcam"** da carteirinha deve seguir igual ao de antes:
    abre a câmera e, ao tirar a foto, **envia direto** para a IA, sem tela de conferência. O
    componente por trás foi trocado por um compartilhado com a tela de Sessões — vale confirmar que
    nada regrediu aqui.

### Pela API, se quiser confirmar as rotas

```bash
# Deve responder 401 (existe, mas exige autenticação) — NÃO pode ser 404
curl -s -o /dev/null -w "ler-registro (novo): %{http_code}\n" -X POST \
  https://gescon.gestaonossa.com.br/api/lancamentos/ler-registro

# A rota antiga continua de pé nesta versão — também 401, não 404
curl -s -o /dev/null -w "ler-registro (antigo): %{http_code}\n" -X POST \
  https://gescon.gestaonossa.com.br/api/guias/1/lancamentos/ler-registro

# O DELETE genérico de antecipação segue fora: 401 ou 405, nunca 204
curl -s -o /dev/null -w "delete antecipacao: %{http_code}\n" -X DELETE \
  https://gescon.gestaonossa.com.br/api/antecipacoes/1
```

## Rollback

Nada a restaurar no banco:

```bash
git -C /opt/gescon checkout $(cat /opt/gescon/deploy/backups/commit_antes_20260916b.txt)
/opt/gescon/deploy/redeploy.sh
```

Se alguém já tiver dispensado uma antecipação com motivo antes do rollback, o motivo fica gravado em
`antecipacoes.observacoes` e não atrapalha a versão antiga — ela simplesmente não o exibe.

## Regras para esta sessão

- Um passo por vez, com a saída na tela antes do próximo
- Nunca imprima senha, `APP_KEY` ou conteúdo de `.secrets.env`
- Não rode migration, `migrate:fresh`, `migrate:rollback` nem `db:wipe` — este deploy não tem
  migration nenhuma, então qualquer uma dessas seria fora de escopo
- Se algo divergir do esperado, **pare e me diga** em vez de tentar corrigir por conta própria
