## 1. Componente de dica

- [x] 1.1 Criar `Tooltip` em `web/src/components/ui/`, abrindo no hover e no `focus-within`, com `role="tooltip"` e `aria-describedby`.
- [x] 1.2 Usar `type="button"` no gatilho, para a dica não submeter o formulário que a contém.

## 2. Campo de conector do convênio

- [x] 2.1 Rotular o campo como `Conector` e pendurar a dica no rótulo.
- [x] 2.2 Escrever o texto das três opções, com a ressalva de que `API` e `Scraping` não ligam automação e hoje quebram a verificação diária.

## 3. Validação

- [x] 3.1 `openspec validate dica-conector-convenio --type change --no-interactive`.
- [x] 3.2 `tsc -b`, `oxlint` e `vite build`.
- [ ] 3.3 Conferir a dica no navegador depois do deploy — hover e Tab.
