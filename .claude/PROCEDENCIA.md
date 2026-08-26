# Procedência do ferramental em `.claude/`

Nem tudo aqui é nosso. O que veio de fora está listado abaixo, com origem e licença, para a cópia
não virar código órfão.

| Caminho | Origem | Licença | Copiado em |
|---|---|---|---|
| `skills/ui-ux-pro-max/` | [nextlevelbuilder/ui-ux-pro-max-skill](https://github.com/nextlevelbuilder/ui-ux-pro-max-skill) | MIT | 25/08/2026 |
| `skills/frontend-design/` | [anthropics/claude-code](https://github.com/anthropics/claude-code) → `plugins/frontend-design` | Anthropic Commercial Terms (`LICENSE.md`) | 25/08/2026 |
| `skills/webapp-testing/` | [anthropics/skills](https://github.com/anthropics/skills) → `skills/webapp-testing` | Apache 2.0 (`LICENSE.txt`) | 25/08/2026 |
| `agents/*.md` | [anthropics/claude-code](https://github.com/anthropics/claude-code) → `plugins/pr-review-toolkit/agents` | Anthropic Commercial Terms | 26/08/2026 |
| `skills/openspec-*/` | do próprio projeto | — | — |

`skills/ui-ux-pro-max/` tem detalhamento próprio em `skills/ui-ux-pro-max/PROCEDENCIA.md`: é a única
cópia que exigiu ajuste para funcionar aqui.

As demais vieram sem alteração — exceto `skills/frontend-design/`, cujo arquivo de licença veio
vazio na primeira cópia (o `curl` gravou o corpo de um 404) e foi refeito a partir do `LICENSE.md`
da raiz do repositório de origem. Para atualizar qualquer uma, baixe o arquivo correspondente do
repositório de origem e **atualize a data nesta tabela**.
