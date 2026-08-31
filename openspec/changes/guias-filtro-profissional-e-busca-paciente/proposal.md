## Why

Na lista de Guias, o filtro por paciente era um dropdown com todos os pacientes do tenant — em clínicas com muitos pacientes, achar um nome específico na lista exigia rolar o combo inteiro. Ao mesmo tempo, não havia como filtrar a lista pelo profissional executante, algo que quem gerencia repasse ou agenda por profissional precisa com frequência.

## What Changes

- O dropdown "Paciente" no filtro da lista de Guias vira dropdown "Profissional", listando os profissionais ativos do tenant, independente de outros filtros.
- Novo campo de texto "Paciente" no mesmo filtro, para busca por nome (parcial, sem diferenciar maiúsculas/minúsculas), aplicado junto com os demais filtros ao clicar em "Aplicar".
- API `GET /guias` passa a aceitar `profissional_id` (equivalente ao `paciente_id` já existente) e `paciente_nome` (busca parcial no nome do paciente vinculado).
- O dropdown de Paciente do formulário "Nova guia" (cadastro, não filtro) não muda.

## Capabilities

### New Capabilities

- `guias-filtro-profissional-e-busca-paciente`: filtro de Guias por profissional executante e busca por nome de paciente.

### Modified Capabilities

- Nenhuma.

## Non-goals

- Não se adiciona busca por nome de paciente em outras listagens (Solicitações, Antecipações, etc.) — escopo restrito a Guias.
- A busca por nome não é "ao vivo" (debounced): segue o mesmo padrão dos demais filtros da tela, aplicados pelo botão "Aplicar".
- Não se filtra a lista de profissionais do dropdown pelo Convênio selecionado — lista todos os ativos, como o dropdown de Convênio já faz hoje.

## Impact

- API: `App\Http\Controllers\GuiaController`, `App\Services\GuiaService`.
- Frontend: `web/src/features/guias/GuiasPage.tsx`, `web/src/features/guias/types.ts`.
