## Why

A máscara 4+4+6+2+1 da carteirinha Unimed já existia inteira — cinco campos no formulário, validação de 17 dígitos no servidor —, mas só entrava em ação quando `convenios.connector_driver` valia `unimed_rda`. Esse campo não é um seletor de máscara: é o interruptor de **toda a automação Unimed**, com doze consumidores, entre eles `GuiaService`, `SolicitacaoService`, o job que enfileira consultas ao portal e o `VerificarGuiasDiarioJob`, que deixa de conferir manualmente as guias desse convênio por assumir que a automação cuida delas.

Em produção o campo estava `NULL` nos três convênios, então o recurso inteiro estava inerte. E ligá-lo apenas para obter a máscara acionaria a automação junto — sem credencial cadastrada, falhando em toda guia nova e tirando essas guias da verificação diária.

Formato de carteirinha é característica do convênio; qual automação usar é decisão de integração. Estavam amarrados.

## What Changes

- Nova coluna `convenios.carteirinha_blocos` (JSON): a lista de tamanhos de bloco. `null` significa texto livre.
- A máscara, a validação e a normalização passam a derivar dela. O `connector_driver` deixa de interferir no formato.
- Campo **Formato da carteirinha** na tela de Convênios, com atalho para o padrão Unimed.
- `CarteirinhaUnimedInput` vira `CarteirinhaBlocosInput`, recebendo os blocos por parâmetro; `lib/carteirinha` deixa de ser específica da Unimed.

## Capabilities

### New Capabilities

- `carteirinha-formato-por-convenio`: declaração do formato da carteirinha por convênio e sua aplicação na digitação, na validação e na exibição.

### Modified Capabilities

- `pacientes-crud`: o campo Carteirinha passa a mudar de forma conforme o convênio escolhido.

## Impact

- **API**: migration da coluna, `Convenio::blocosCarteirinha()`, `UpsertConvenioRequest`, `ConvenioResource`, `PacienteResource`, `PacienteController` e a nova trait `ValidaCarteirinhaPorConvenio`.
- **Frontend**: `lib/carteirinha`, `CarteirinhaBlocosInput`, `PacientesPage`, `LerPedidoMedicoPage`, `ConveniosPage` e os pontos que exibem carteirinha em Guias e Solicitações.
- **Banco**: uma coluna nova. Nenhuma carteirinha existente é alterada.

## Decisões

- **Lista de blocos, não um enum de formatos conhecidos.** Os padrões dos outros convênios ainda não são conhecidos; um enum exigiria deploy a cada descoberta. Com a lista, um formato novo se cadastra pela tela — o que também é o que o ADR-03 pede, já que regra de convênio é dado e não código.
- **A regra de validação vive numa trait compartilhada** por criação e edição de paciente. Estavam duplicadas; divergirem faria um cadastro válido não poder ser salvo na edição.
- **`formatCarteirinha` cai no padrão Unimed quando não recebe blocos.** Em listagens onde o convênio do paciente não vem carregado, esse era exatamente o comportamento anterior — manter evita uma regressão visual silenciosa.
- **O request aceita `carteirinha_blocos` e ainda o antigo `carteirinha_unimed`.** Um cliente desatualizado continua funcionando.
- **Lista vazia é normalizada para `null` no request.** `[]` e `null` significam a mesma coisa; gravar os dois transformaria "sem formato" em dois casos a tratar em toda leitura.

## Non-Goals

- Não há dígito verificador nem validação de conteúdo da carteirinha — só quantidade de dígitos por bloco.
- Carteirinhas já cadastradas não são reformatadas nem revalidadas.
- O `connector_driver` continua como está; nada aqui liga ou desliga automação.
