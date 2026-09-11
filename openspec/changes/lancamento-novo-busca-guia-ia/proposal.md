## Why

Em `/lancamentos/novo`, a Guia era escolhida num `<select>` que só carregava a primeira página de guias disponíveis, sem busca por ID, número, paciente ou profissional — em clínicas com muitas guias, achar a certa exigia rolar o combo. O profissional executante e a especialidade da guia também não eram exibidos como informação própria da tela.

Ao mesmo tempo, `/lancamentos/importar` fazia quase a mesma coisa que "Novo" — registrar sessão contra uma guia — só que lendo a folha de registro por IA (foto/PDF) ou por texto colado, com uma tabela de revisão antes de confirmar. Duas telas concorrentes para o mesmo objetivo, com formulários diferentes.

A leitura por IA (`RegistroSessoesAiService`) também descartava qualquer linha sem data/hora como "ruído", perdendo a posição física da linha na folha de registro (que tem exatamente 10 linhas numeradas). Isso impede completar manualmente uma linha específica depois — a posição da sessão na folha se perde assim que uma linha anterior é descartada.

Por fim, a checagem de PDF obrigatório para a regional 0220 (`regiaoExigePdf`) dependia de reprocessar o texto colado (`transcricao`) para extrair o número do cartão — o que nunca funcionava quando a leitura vinha de foto/PDF, já que nesse caminho não existe texto colado algum. Bug latente, descoberto e corrigido nesta mudança.

## What Changes

- `/lancamentos/novo` passa a ser a única tela de criação de lançamento, absorvendo tudo que `/lancamentos/importar` fazia. `/lancamentos/importar` vira redirect para `/lancamentos/novo`.
- A Guia passa a ser escolhida por um modal de busca (`SelecionarGuiaModal`), no mesmo padrão de `SelecionarPacienteModal`/`SelecionarMedicoModal`: busca por ID, número da guia, nome do paciente ou nome do profissional executante (novo filtro `busca` em `GET /guias`).
- Ao escolher a guia, o profissional executante é filtrado pela especialidade dela e pré-selecionado quando há só um (continua editável), e a especialidade passa a ser exibida como informação própria da tela.
- A tela ganha uma grade fixa de 10 linhas (o máximo físico de uma folha de registro), sempre visível, preenchível manualmente linha a linha.
- É possível anexar uma foto ou PDF da folha de registro de sessões; a IA lê e preenche a grade — preservando a posição física de cada linha: uma linha em branco ou ilegível na folha vira uma posição em branco na grade, na mesma posição, em vez de ser descartada e deslocar as seguintes.
- A leitura por IA passa a ler só o nome do acompanhante, ignorando a assinatura manuscrita dele, e a respeitar as linhas divisórias da folha ao juntar um resumo de atividades que ocupe mais de uma linha de texto — sem misturar texto de sessões diferentes.
- Corrigido: a exigência de PDF para a regional 0220 agora usa o número do cartão vindo explícito do cabeçalho lido (por IA ou pelo parser de texto), em vez de depender de sempre existir uma transcrição colada.

## Capabilities

### New Capabilities

- `lancamento-novo-busca-guia-ia`: busca de guia por ID/número/paciente/profissional, pré-preenchimento do executante, exibição da especialidade e grade fixa de 10 sessões com anexo e leitura por IA, tudo em `/lancamentos/novo`.

### Modified Capabilities

- `importacao-de-sessoes`: a leitura por imagem/PDF deixa de descartar linha sem data/hora — passa a preservar a posição física da linha na folha (10 posições fixas), e ganha instruções para ignorar assinatura do acompanhante e respeitar linhas divisórias no resumo.

## Non-goals

- Não mexe em `/lancamentos/importar-planilha` (importação em lote por planilha) — fluxo e capacidade totalmente separados.
- Não muda o filtro da listagem de Guias (`GuiasPage`) nem a spec `guias-filtro-profissional-e-busca-paciente` — o novo filtro `busca` é aditivo, para o modal de seleção de guia da tela de Sessões.
- Não adiciona cadastro rápido de guia dentro do modal — guia só existe já criada.
- Não altera `POST /guias/{guia}/lancamentos` (criação de sessão única) nem o fluxo de edição de lançamento (`LancamentoEditarPage`) — ambos continuam existindo e testados como estão; só deixam de ser exercitados pelo novo formulário de criação.

## Impact

- API: `App\Services\GuiaService`, `App\Http\Controllers\GuiaController`, `App\Services\RegistroSessoesAiService`, `App\Models\AiPromptTemplate`, `App\Services\LancamentoService`, `App\Http\Controllers\LancamentoController`, `App\Http\Requests\ImportLancamentosTranscricaoRequest`.
- Frontend: `web/src/features/lancamentos/LancamentosPage.tsx` (formulário de criação reescrito), novo `web/src/features/lancamentos/SelecionarGuiaModal.tsx`, novo hook `useGuiasBusca` em `useLancamentos.ts`, `web/src/features/lancamentos/types.ts`, `web/src/routes/AppRoutes.tsx` (redirect), remoção de `web/src/features/lancamentos/ImportarSessoesPage.tsx`.
- Testes: `api/tests/Feature/LancamentosApiTest.php`, `api/tests/Feature/GuiasApiTest.php`, `web/tests/e2e/crud-navigation.spec.ts`, `web/tests/e2e/mvp-flow.spec.ts`.
