## Why

O contrato de saída da leitura por IA estava fixo no código pedindo `especialidade_nome`, **no singular**. O modelo obedecia: num pedido de acompanhamento multidisciplinar devolvia uma especialidade no campo e descrevia as outras em `observacoes` — visíveis para o operador, mas fora dos campos do formulário.

Foi o que aconteceu num pedido real com fonoaudiologia, nutrição, psicologia e psicopedagogia: só Fisioterapia chegou ao pedido, e o resto virou texto de observação. Como o sufixo é concatenado ao prompt pelo próprio código, nem editando o prompt na tela de Prompts Operacionais o operador mudaria isso.

A solicitação já suportava várias especialidades (uma linha de item por especialidade) desde antes; quem não suportava era a leitura.

## What Changes

- A chave de saída vira `especialidades`, uma lista. `especialidade_nome` continua aceito.
- As sugestões passam a vir **agrupadas por termo lido**, cada uma com os cadastros parecidos e um indicador de que nenhum serve.
- A tela cria uma linha de item por especialidade lida que casou com segurança, e mostra um bloco por termo do documento.
- Termo sem cadastro correspondente ganha o botão de cadastrar, que cria a especialidade e a acrescenta ao pedido.
- O bloco do **médico solicitante** passa a vir antes das especialidades no formulário.

## Capabilities

### Modified Capabilities

- `solicitacoes-ler-pedido-medico`: a leitura passa a extrair várias especialidades e a distinguir as que não têm cadastro.

## Impact

- **API**: `PedidoMedicoAiService` — contrato de saída, agrupamento das sugestões e o corte de confiança.
- **Frontend**: `LerPedidoMedicoPage` e os tipos de `PedidoMedicoAiResult`.
- **Banco**: nenhuma alteração.

## Decisões

- **O contrato de saída fica no código, não no prompt editável.** O parser e o agrupamento dependem exatamente daquelas chaves; se o operador reescrevesse o prompt e trocasse os nomes, a leitura passaria a devolver campos vazios sem erro nenhum.
- **Corte de confiança de 90% para considerar que um cadastro é a mesma especialidade.** `similar_text` dá 75% entre "Psicopedagogia" e "Psicologia", que são terapias diferentes. Abaixo do corte a sugestão continua visível como palpite, ao lado do convite para cadastrar, mas **nunca é aplicada sozinha**: aplicar trocaria a terapia do paciente em silêncio, o que é erro clínico e não de digitação.
- **Sugestões agrupadas por termo, não em lista achatada.** Com quatro especialidades no pedido, uma lista plana não diria qual cadastro casou com qual termo, nem quais termos não casaram com nada.
- **A especialidade recém-criada é acrescentada, não substitui a primeira linha.** O comportamento anterior apagava uma especialidade já preenchida num pedido multidisciplinar.

## Non-Goals

- Não há criação automática de especialidade: o cadastro sempre passa por confirmação do operador.
- Não há leitura de quantidade de sessões nem de frequência por especialidade; essas informações continuam em `observacoes` e são preenchidas à mão.
- O corte de confiança não é configurável pela tela.
