# Resumo de entregas — 13 e 14/08/2026

Onze entregas em produção (`gescon.gestaonossa.com.br`), do commit `ea9d613` ao
`0aa9c8c`. Cada uma foi publicada e verificada em produção antes da seguinte.

## 1. Convênios: editar em tela própria

`94dd0dd`

A criação já abria em `/convenios/novo`, mas a edição continuava embutida na
listagem — o que contrariava a spec `crud-lista-formulario-separados`, cujo
cenário fala de criação **e** edição. O formulário existia duplicado no arquivo,
uma cópia para cada fluxo.

Rota `/convenios/:id/editar`, formulário único, hidratação ao abrir pela URL, e
erro de salvamento visível — o `.then()` anterior engolia a falha em silêncio.

## 2. Dica do campo Conector

`94dd0dd`

Componente `Tooltip` (botão de lupa) explicando Manual, API e Scraping.

O aviso não é detalhe de roadmap: o `ConnectorResolver` só implementa `manual`,
então convênio marcado como `api` ou `scraping` **derruba a verificação diária de
guias** — o job aborta e os convênios seguintes ficam sem verificação naquela
execução. A automação da Unimed é ligada por `connector_driver`, em
Configurações, não por este campo.

## 3. Perfis, permissões e menu por permissão

`01f9b41` — spec `perfis-e-permissoes`

A tela `/permissoes` existia fora do menu e não tinha efeito visível: o menu
mostrava tudo para todos e o clique só falhava com 403.

Na raiz, o mesmo defeito: **a API nunca devolvia as permissões do usuário**. O
`authStore` tinha o campo, sempre `undefined`, e o único ponto do frontend que
consultava permissão caía no fallback `role === 'admin'`.

- `permissions` no login e em `GET /user`, recarregadas na abertura do app
- menu, cartões de grupo e de configurações escondem o que o papel não pode
- CRUD de papéis por clínica, com `admin`, `funcionario` e `profissional`
  protegidos: permissões editáveis, nome e existência não
- guard-rails contra lockout: não é possível remover `permissoes.manage` do
  último papel que a tem, nem do papel de quem está alterando

O guard-rail derrubou um teste existente que fazia exatamente isso.

## 4. Trilha de auditoria

`99f87fe` e `e664429` — spec `trilha-de-auditoria`

A tabela `audit_logs` existia desde o começo, mas **três pontos do sistema
inteiro** escreviam nela. Alterar convênio, criar usuário, trocar permissão: nada
deixava rastro.

- trait `Auditable` com observer em 26 modelos: `created`, `updated` e `deleted`
  viram registro sozinhos, com antes e depois
- campo sensível registra só que mudou, nunca o valor
- **`senha`, `chave` e `key` soltos ficaram fora dos padrões de censura**: neste
  domínio "senha" é o código de autorização do convênio, e escondê-lo tiraria da
  trilha justamente o que ela precisa mostrar
- acessos (login, logout, recusa, 403) com IP e navegador
- importação de analítico gera um evento por lote, não um por linha
- consulta com filtros, busca por nome, tipo de ação, rótulos legíveis e CSV
- retenção de 12 meses configurável, com expurgo que exporta antes de apagar

**Bug corrigido no caminho:** `audit_logs` não tinha `ip` nem `user_agent` no
`$fillable`, então o mass assignment descartava os dois em silêncio.

## 5. Cadastro de paciente e leitura da carteirinha

`2efc11f`, `aebdd80`, `faa57b7`, `1b23e27` — spec `cadastro-paciente-carteirinha`

O formulário pedia os dados na ordem errada e pré-selecionava o primeiro
convênio da lista — bastava não olhar para cadastrar o paciente na operadora
errada, em silêncio.

- convênio como primeira pergunta, sem pré-seleção
- CPF opcional, com máscara e dígitos verificadores conferidos nos dois lados
- telefones em tabela própria, com rótulo, nome de quem atende e principal único
- validade da carteirinha e data de nascimento, com aviso de vencida sem bloquear
- **Ler Carteirinha**: câmera do celular, webcam no computador ou arquivo, lidos
  por IA. Campo não reconhecido não apaga o que já estava digitado, e convênio
  sem casamento certeiro fica em branco com a nota de proximidade de cada
  candidato
- imagem guardada por 30 dias configuráveis, nascendo sem dono para que leitura
  abandonada não deixe documento pessoal órfão

## 6. Código da especialidade por convênio

`fff4b5a` — spec `codigo-por-convenio-na-especialidade`

Cada operadora usa um código próprio para o mesmo procedimento. A estrutura já
existia — `convenio_especialidade_mapeamentos`, com a chave
`(tenant, convênio, especialidade)` — mas só era editável dentro da automação da
Unimed.

O cadastro da especialidade passou a mostrar um campo por convênio, e convênio
novo aparece sozinho em todas as especialidades. Onze códigos carregados em
produção, conferindo nome a nome.

## 7. Densidade das telas

`5259a1e` e `074e83c` — spec `densidade-e-confirmacao-de-exclusao`

Contadores viraram linha compacta em dez telas; 163 linhas de texto descritivo
saíram de dezenove. Boa parte era nota de desenvolvimento vazada para a
interface — "vem da API real do tenant", "passa por translateStatus()".

A garantia de privacidade da auditoria não é decorativa e virou dica na lupa.

## 8. Exclusão confirmada e atalho de profissional

`79fe815`

- exclusão de anexo passa a exigir o diálogo e a palavra `EXCLUIR` digitada; o
  `window.confirm` anterior virava clique reflexo, e anexo apagado não volta
- especialidade sem executante na solicitação mostra atalho para cadastrar
  profissional, abrindo em outra aba com a especialidade já marcada
- listagem de pacientes ganhou ordenação e filtros de convênio, status e
  situação da carteirinha

**Corrigido no caminho:** a coluna Contato lia a coluna `telefone` antiga, que
nasce vazia desde os telefones múltiplos — todo paciente cadastrado depois
daquela mudança aparecia sem telefone.

## 9. Importação de sessões em tela própria

`990947d` — spec `importar-sessoes-por-ia`

O painel vivia aberto acima da listagem, com uma caixa de texto de setenta
linhas. A revisão do fluxo mostrou que **não havia OCR nenhum**: o campo se
chamava "Transcrição OCR", mas quem lia era um parser de expressões regulares, e
o texto precisava ser produzido fora do sistema.

Virou tela própria com leitura por IA de foto ou PDF, usando a chave
`ler_sessoes_escaneadas` que existia reservada e sem uso. O seletor de
antecipação passou a mostrar nome do paciente em vez do id, e o executante é
filtrado pela especialidade.

## 10. Editar em tela própria no sistema inteiro

`75e26a9` — spec `separar-listagem-formularios`, seção 7

Auditoria de todas as telas com `Editar`. O padrão se repetia: criação com rota
própria, edição embutida por estado local. Corrigidas Médicos, Pacientes,
Templates de E-mail e Prompts Operacionais.

Clínicas ficou de fora: edita dentro do próprio cartão da lista, é outro padrão.

## 11. Ordenação em todas as listagens

`0aa9c8c` — spec `listagens-ordenaveis`

Dez listagens passaram a ordenar pelos cabeçalhos, sempre no servidor. Colunas
de relação ordenam pelo nome, com `join`. A lista de colunas aceitas é fechada
por listagem, porque o nome vem da query string e vai direto para o `ORDER BY` —
há teste enviando `(select 1)` e `nome; drop table users`.

## Estado da verificação

- **256 testes de API**, 1178 asserções, todos passando
- `tsc -b`, `oxlint` e `vite build` limpos
- `openspec validate` válido em todos os changes tocados
- `openspec validate --all`: 41 de 42 válidos. O único que falha é
  `dashboard-home-refresh`, quebrado desde julho (commit `31fb380`): a pasta tem
  só o `.openspec.yaml`, sem proposal nem specs. Não é desta rodada e não mexi —
  não dá para saber se os arquivos se perderam ou nunca foram escritos
- **suíte e2e do Playwright nunca executada**: o servidor não tem Node nem PHP
  fora dos containers. Os casos escritos para `/convenios/:id/editar`,
  `/permissoes/novo` e a navegação de convênios estão commitados e não rodados

## Pendências registradas

| Onde | O quê |
|---|---|
| `separar-listagem-formularios` 5.4 e 6.6 | Hidratação de edição lê a listagem carregada; link direto para registro fora dela abre formulário vazio ou "não encontrado" |
| `separar-listagem-formularios` 7.4 | Clínicas segue editando dentro do cartão |
| `codigo-por-convenio-na-especialidade` 3.2 | Códigos ainda em branco: Fonoaudiologia sem ABA, Celos, Particular, e três especialidades na SC Saúde |
| `listagens-ordenaveis` 4.1 | Convênio em Conciliações e Especialidade em Profissionais não ordenam |
| `densidade-e-confirmacao-de-exclusao` 3.3 | Excluir perfil ainda usa `window.confirm` |
| `cadastro-paciente-carteirinha` | Telefone antigo de pacientes anteriores não migrou para a lista nova |
| `importar-sessoes-por-ia` 3.4 | Leitura real de registro de sessões com a chave de produção não foi conferida |
| Trilha de auditoria | Começou vazia em 13/08; nada anterior foi registrado |
