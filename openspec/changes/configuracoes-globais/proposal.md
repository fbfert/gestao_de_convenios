## Why

Vários parâmetros de comportamento do sistema estavam fixos no código, sem forma de ajustar por clínica: a antecedência do aviso de senha vencendo (`SENHA_VENCENDO_EM_DIAS = 7`), a quantidade sugerida ao acrescentar uma especialidade na solicitação (`'10'`) e o tamanho das listagens. Mudar qualquer um exigia alterar código e fazer deploy.

Faltava também o mais pedido: **tempo de sessão**. O sistema autentica por token do Sanctum, e `sanctum.expiration` estava `null` — um login valia para sempre. Um token vazado do `localStorage` continuaria funcionando indefinidamente.

## What Changes

- Nova tela **Configurações → Globais**, primeira do submenu, com o card correspondente na tela de entrada de Configurações.
- Nova tabela `configuracoes_globais`, uma linha por tenant.
- **Tempo de sessão** aplicado de verdade, por middleware: passado o prazo, o token é apagado e a próxima requisição devolve 401, que o frontend já trata levando ao login.
- Três parâmetros que estavam no código passam a ser configuráveis: aviso de senha vencendo, sessões sugeridas por especialidade e itens por página.

## Capabilities

### New Capabilities

- `configuracoes-globais`: parâmetros de comportamento do sistema por clínica, incluindo o tempo de validade de um login.

## Impact

- **API**: migration, model `ConfiguracaoGlobal`, `ConfiguracaoGlobalController`, `UpdateConfiguracaoGlobalRequest` e o middleware `EncerrarSessaoExpirada` no grupo autenticado.
- **Frontend**: `ConfiguracoesGlobaisPage`, `useConfiguracoesGlobais`, entrada no submenu e rota.
- **Banco**: tabela nova. Nenhum dado existente é tocado.

## Decisões

- **Uma coluna por parâmetro, não chave-valor.** Cada parâmetro tem tipo e faixa próprios; um `valor` genérico em texto empurraria toda a validação para o código de leitura, onde um valor inválido só apareceria em produção.
- **O prazo conta da emissão do token, não do último uso.** O Sanctum grava `last_used_at` dentro do próprio guard, antes de qualquer middleware da rota: ao chegar no middleware o campo já vale "agora", então um tempo ocioso medido por ele nunca venceria. Contar da emissão é o mesmo critério do `expiration` do próprio Sanctum, e o efeito é claro — passado o prazo, é preciso entrar de novo.
- **Middleware próprio em vez de `config('sanctum.expiration')`.** Aquele valor é único para a instalação inteira; aqui o prazo é por tenant.
- **O token expirado é apagado, não apenas recusado.** Deixá-lo no banco daria a um vazamento de `localStorage` uma credencial que continua existindo.
- **`0` desliga a expiração.** É a saída para quem não quer o comportamento, sem precisar de outra chave.
- **A leitura cria a linha com os padrões.** Quem consome nunca precisa tratar o caso de um tenant que jamais abriu a tela.
- **Regra de convênio não entra aqui.** Validade de senha, quantidade autorizada e valores continuam em `convenio_regras` e `tabela_valores`, por operadora, como manda o ADR-03.

## Non-Goals

- Não há expiração por inatividade — só o prazo absoluto desde a entrada.
- Não há aviso na tela antes da sessão expirar; o usuário descobre na próxima ação.
- Os três parâmetros de operação estão gravados e editáveis, mas **as telas que os originaram ainda leem os valores fixos**. Ligar cada um é trabalho separado.
