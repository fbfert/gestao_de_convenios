# Procedência — ui-ux-pro-max

Código de terceiro, copiado para dentro deste repositório. Este arquivo existe para a cópia não
virar código órfão: sem ele, daqui a alguns meses ninguém sabe de onde isto veio nem se envelheceu.

| | |
|---|---|
| **Origem** | https://github.com/nextlevelbuilder/ui-ux-pro-max-skill |
| **Revisão copiada** | `e353a508767c6d39f0e7698b084dbfc8699fffd3` (25/08/2026) |
| **Licença** | MIT — © 2024 Next Level Builder (`LICENSE`, ao lado) |
| **Copiado em** | 25/08/2026 |
| **O que é** | Base consultável de estilos de UI, paletas, pares tipográficos e diretrizes de UX |

## O que foi alterado na cópia

Duas mudanças, e só elas:

1. **`scripts/tests/` não veio.** São os testes do próprio projeto de origem; não rodam aqui e
   pesariam à toa.
2. **Os caminhos no `SKILL.md` foram reescritos.** O arquivo original aponta para
   `${CLAUDE_PLUGIN_ROOT}/.claude/skills/ui-ux-pro-max/`, que só resolve quando a skill é instalada
   como *plugin*. Aqui ela é skill de projeto, então os 11 caminhos passaram a ser relativos à raiz
   do repositório. **Sem esse ajuste a ferramenta de busca não roda.**

Nenhum dado foi editado. A base (`data/*.csv`, `data/*.json`) está como veio.

## Auditoria feita antes de aceitar

Foi verificado que os scripts não fazem acesso à rede, não executam shell e não usam `eval`/`exec` —
é busca local em CSV. A única importação que parece rede (`urllib.parse` em `validate_data.py`) é só
para separar componentes de URL na validação dos dados.

## Como atualizar

```bash
git clone --depth 1 https://github.com/nextlevelbuilder/ui-ux-pro-max-skill /tmp/uipro
rsync -a --exclude 'scripts/tests' --exclude '__pycache__' \
  /tmp/uipro/.claude/skills/ui-ux-pro-max/ .claude/skills/ui-ux-pro-max/
```

Depois **refazer o ajuste de caminho do item 2** — o `SKILL.md` volta com
`${CLAUDE_PLUGIN_ROOT}` — e conferir que a busca ainda responde:

```bash
python3 .claude/skills/ui-ux-pro-max/scripts/search.py "responsive layout" --domain ux
```

E registre aqui a nova revisão. Uma tabela desatualizada é pior que nenhuma.

## Por que está versionada

`.claude/skills/` já era versionado neste repositório (as skills do OpenSpec vivem lá), e quem
clonar precisa do mesmo ferramental. São 3,4 MB na árvore de trabalho, mas ~0,7 MB comprimidos no
git — CSV comprime bem.
