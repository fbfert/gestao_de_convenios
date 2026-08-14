## Why

Cada operadora usa um código próprio para o mesmo procedimento: a Psicologia ABA é `2250005286` na Unimed e `13107208` na SC Saúde. Sem esse código, a guia não é aceita.

Até aqui o código só existia dentro de Configurações → Unimed RDA, como parte da automação. Quem cadastrava uma especialidade não tinha onde informar o código dela em cada convênio, e quem cadastrava um convênio novo não tinha como preencher os códigos das especialidades já existentes.

## What Changes

- O cadastro de especialidade passa a mostrar um campo por convênio cadastrado.
- Convênio novo aparece sozinho como campo em todas as especialidades: a lista sai do próprio cadastro de convênios, então não há passo manual nem migração quando surge uma operadora.
- Código em branco significa que aquele convênio não atende a especialidade.
- A listagem de especialidades mostra os códigos já preenchidos, para conferir de relance qual convênio ainda está sem.
- Reaproveita `convenio_especialidade_mapeamentos`, a mesma tabela que a automação da Unimed já usa. **Duas fontes para o mesmo código sairiam do ar uma da outra**, e o sintoma seria a operadora recusando a guia.

## Capabilities

### New Capabilities

- `codigo-por-convenio`: código do procedimento por par convênio × especialidade, editável no cadastro da especialidade.

### Modified Capabilities

- Nenhuma.

## Non-goals

- Editar quantidade padrão e descrição da operadora fora da tela da Unimed: esses campos continuam onde estão, e a gravação pela especialidade preserva o que já foi configurado lá.
- Validar o código contra a tabela da operadora: não há fonte para conferir, e um dígito errado só aparece na recusa da guia.

## Impact

- API: `EspecialidadeController` (leitura com `com_codigos`, gravação sincronizando os mapeamentos), `EspecialidadeResource`, requests de criação e edição.
- Frontend: formulário e listagem de especialidades.
- Sem migration: a tabela existe desde a fundação da automação Unimed, com a chave `(tenant, convênio, especialidade)`.
