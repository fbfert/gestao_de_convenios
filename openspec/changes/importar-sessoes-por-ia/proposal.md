## Why

A importação do registro de sessões vivia embutida na listagem de Sessões, com uma caixa de texto de setenta linhas permanentemente aberta acima da lista, dentro de um grid de duas colunas com um único filho — metade da largura vazia ao lado.

A revisão do fluxo mostrou mais do que um problema de espaço:

- O campo se chamava "Transcrição OCR", mas **não havia OCR nenhum**: quem lia era um parser de expressões regulares, e o texto precisava ser produzido fora do sistema. A chave de prompt `ler_sessoes_escaneadas` existia reservada, sem uso, enquanto carteirinha e pedido médico já liam imagem por IA.
- O seletor de antecipação mostrava `Paciente 42` — o identificador, não o nome —, porque a API de antecipações só devolvia IDs.
- O profissional executante listava toda a clínica, sem filtrar por quem atende a especialidade da antecipação. Lançar sessão no nome de quem não faz aquela terapia gera glosa na conciliação.
- Os contadores da tela mostravam o estado da importação no lugar de métrica da listagem.

## What Changes

- A importação vira tela própria, aberta por um botão ao lado de Templates.
- A leitura passa a aceitar foto ou PDF lidos por IA, além do texto colado, que continua disponível recolhido.
- A API de antecipações passa a devolver nome do paciente, convênio e especialidade.
- O profissional executante é filtrado pela especialidade da antecipação e pré-selecionado quando só há um.
- Aviso destacado enquanto a IA lê, para leitura demorada não parecer travada e virar clique duplicado.

## Capabilities

### New Capabilities

- `importacao-de-sessoes`: leitura assistida do registro de sessões e revisão antes de gravar.

### Modified Capabilities

- Nenhuma.

## Non-goals

- Confirmar as sessões automaticamente: a revisão de datas e horários continua obrigatória, e nada é gravado sem confirmação.
- Substituir o parser de texto: quem já tem a transcrição pronta continua colando.

## Impact

- API: `RegistroSessoesAiService`, `POST /antecipacoes/{antecipacao}/lancamentos/ler-registro`, `AntecipacaoResource` e `AntecipacaoService`.
- Frontend: `ImportarSessoesPage` nova, listagem de Sessões enxuta.
- Prompt de sistema `ler_sessoes_escaneadas` sai da reserva e passa a ser usado.
