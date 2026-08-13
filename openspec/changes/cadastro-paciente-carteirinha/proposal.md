## Why

O cadastro de paciente pede os dados na ordem errada e guarda menos do que a operação precisa.

O convênio é a última pergunta relevante, mas é ele que define o formato da carteirinha — hoje o operador digita o número antes de dizer de quem ele é. Pior: o formulário pré-seleciona o primeiro convênio da lista, então basta não olhar para cadastrar o paciente na operadora errada, em silêncio.

O CPF aceita qualquer texto, sem máscara e sem conferência. O telefone é uma coluna única, e uma criança em terapia costuma ter três contatos — mãe, pai e recado. O campo ID Clínica Ágil ocupa espaço na tela para um dado que ninguém digita à mão.

E todo o cadastro é feito olhando para a carteirinha do paciente e transcrevendo à mão, com a leitura por IA já pronta e em uso para pedidos médicos.

## What Changes

- Convênio passa a ser a primeira pergunta, sem pré-seleção: escolher vira ato consciente.
- `Nome` vira `Nome Completo`.
- CPF opcional, com máscara `000.000.000-00`, aceitando apenas dígitos e conferindo os dígitos verificadores. Gravado sem formatação.
- Vários telefones por paciente, cada um com rótulo, nome de quem atende e marcação de principal.
- Campo ID Clínica Ágil sai da tela. **A coluna e os dados permanecem** — é referência externa prevista no ADR-05.
- Botão `Ler Carteirinha`: foto pela câmera no celular ou arquivo no computador, lidos por IA, preenchendo carteirinha, nome, convênio, CPF, validade e data de nascimento.
- Validade da carteirinha e data de nascimento passam a ser guardadas. Carteirinha vencida gera aviso no paciente e ao abrir solicitação para ele, sem bloquear.
- A imagem da carteirinha é guardada por prazo configurável, padrão 30 dias, e apagada por rotina diária.

## Capabilities

### New Capabilities

- `leitura-de-carteirinha`: extração assistida por IA dos dados da carteirinha, com guarda temporária da imagem.

### Modified Capabilities

- `patients-crud`: ordem dos campos, CPF validado, múltiplos telefones, validade e nascimento.

## Non-goals

- Bloquear operação por carteirinha vencida: a data cadastrada pode estar velha, e travar a esteira por causa disso atrapalharia mais do que ajuda.
- Restringir quem aciona a leitura: as rotas de paciente não exigem permissão dedicada hoje, e a leitura de pedido médico segue o mesmo padrão. Mudar isso é decisão separada.
- Anexos gerais do paciente: a tabela nova guarda a imagem da carteirinha com prazo de validade, não um repositório de documentos.

## Impact

- Banco: tabelas `paciente_telefones` e `paciente_documentos`; colunas `validade_carteirinha` e `data_nascimento` em `pacientes`; `carteirinha_retencao_dias` em `configuracoes_globais`.
- API: `POST /pacientes/ler-carteirinha`, novo serviço de leitura, chave de prompt `ler_carteirinha`, job diário de expurgo das imagens.
- Frontend: formulário de paciente reescrito; máscara de CPF; lista de telefones; captura de imagem.
- Fluxo rápido de paciente dentro de Solicitações (`storePacienteRapido`) precisa continuar funcionando com o modelo novo de telefone.
