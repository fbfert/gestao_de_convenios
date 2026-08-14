## Why

Só a listagem de Pacientes ordenava pelos cabeçalhos. Nas demais, a ordem era fixa — quase sempre o id decrescente —, então achar a guia mais antiga, o profissional com maior repasse ou a conciliação de maior valor exigia percorrer a lista inteira.

## What Changes

- Cabeçalhos ordenam ao clique em Guias, Solicitações, Sessões, Antecipações, Conciliações, Médicos, Profissionais, Especialidades, Usuários e Analíticos.
- A ordenação é resolvida no servidor: ordenar só a página visível daria a impressão de listar tudo em ordem quando não é o caso.
- Colunas de relação ordenam pelo nome, com `join` — ordenar guias por paciente é ordenar por `pacientes.nome`, não por `paciente_id`.
- A lista de colunas aceitas é fechada por listagem, porque o nome vem da query string e vai direto para o `ORDER BY`.

## Capabilities

### New Capabilities

- `listagens-ordenaveis`: ordenação por cabeçalho, resolvida no servidor, com lista fechada de colunas.

### Modified Capabilities

- Nenhuma.

## Non-goals

- Ordenar por coluna que a API não sabe ordenar: o cabeçalho continua texto simples, sem seta. Prometer e não cumprir é pior que não oferecer.
- Guardar a ordenação escolhida entre visitas: a escolha vale enquanto a tela está aberta.

## Impact

- API: `App\Support\OrdenaListagem` e os serviços e controllers de cada listagem.
- Frontend: `ColunaOrdenavel`, `useOrdenacao` e as dez telas.
