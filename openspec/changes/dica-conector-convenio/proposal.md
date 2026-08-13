## Why

O campo de conector do cadastro de convênio oferece `Manual`, `API` e `Scraping` sem nenhuma explicação na tela. As três opções não são equivalentes: só `manual` tem conector implementado, e a automação da Unimed é ligada em Configurações → Unimed, não neste campo. Sem essa informação, o operador escolhe pelo nome e pode deixar o convênio numa opção que derruba a verificação diária de guias.

## What Changes

- Criar um componente de dica reutilizável (botão de lupa) que abre no hover e no foco de teclado.
- Usar essa dica no campo de conector do formulário de convênio, explicando as três opções e a ressalva sobre API e Scraping.
- Nenhuma mudança de comportamento, validação ou payload: a dica é só apresentação.

## Capabilities

### New Capabilities

- `dica-campos-formulario`: dica de ajuda por campo, acessível por mouse e por teclado.

### Modified Capabilities

- Nenhuma.

## Impact

- Frontend React/Tailwind: novo `web/src/components/ui/Tooltip.tsx` e o formulário de convênio.
- Nenhum impacto em API, banco ou permissões.
