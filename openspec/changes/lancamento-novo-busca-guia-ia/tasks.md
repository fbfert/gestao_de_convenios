## 1. API

- [x] 1.1 `GuiaService::listar`: novo filtro `busca` (ID exato quando numérico, número da guia parcial, nome do paciente parcial, nome do profissional parcial — qualquer um batendo já mostra a guia).
- [x] 1.2 `GuiaController::index`: liberar `busca` na whitelist de filtros.
- [x] 1.3 `RegistroSessoesAiService`: parar de descartar linha sem data/hora; preencher (pad/truncar) sempre para exatamente 10 posições, preservando a ordem/posição física da folha.
- [x] 1.4 `RegistroSessoesAiService`/`AiPromptTemplate`: atualizar o contrato de leitura e o prompt padrão semeado (`ler_sessoes_escaneadas`) para instruir a IA sobre as 10 linhas fixas, ignorar assinatura do acompanhante e respeitar linhas divisórias no resumo.
- [x] 1.5 `ImportLancamentosTranscricaoRequest`: `transcricao` vira opcional; `sessoes` ganha `max:10` e `sessoes.*.data_sessao` vira opcional (linhas em branco da grade); novo campo `numero_cartao` opcional.
- [x] 1.6 `LancamentoService::confirmarTranscricao`: aceita `transcricao` nula (só reprocessa o parser de texto quando há transcrição de verdade); filtra linhas sem `data_sessao` antes de gravar.
- [x] 1.7 `LancamentoController::importarTranscricao`: usa `numero_cartao` explícito do payload para a checagem da regional 0220, em vez de derivar da transcrição colada (corrige leitura por imagem, que nunca tinha texto para reprocessar).

## 2. Frontend

- [x] 2.1 Novo `SelecionarGuiaModal` (busca por ID/número/paciente/profissional, mesmo padrão de `SelecionarPacienteModal`/`SelecionarMedicoModal`, sem cadastro rápido).
- [x] 2.2 Novo hook `useGuiasBusca` em `useLancamentos.ts`, no molde de `usePacientesBusca`/`useMedicosBusca`, mas habilitado mesmo com busca vazia (mostra guias disponíveis por padrão).
- [x] 2.3 `LancamentosPage`: formulário de criação reescrito — guia por modal, executante pré-preenchido/editável filtrado por especialidade, especialidade exibida, grade fixa de 10 linhas sempre visível, anexo de foto/PDF com leitura por IA, "colar texto" como alternativa recolhida, preenchimento manual livre.
- [x] 2.4 Remover o link "Importar transcrição" da listagem de Sessões.
- [x] 2.5 `AppRoutes`: `/lancamentos/importar` vira redirect para `/lancamentos/novo`; remover `ImportarSessoesPage.tsx` e seu import.
- [x] 2.6 `types.ts`: `LancamentoConfirmImportForm` ganha `numero_cartao`; remover `LancamentoForm` (tipo do formulário manual antigo, agora sem uso) e `useCriarLancamento` (hook órfão).

## 3. Validação

- [x] 3.1 Teste de API cobrindo o filtro `busca` de Guias por ID, número, paciente e profissional (`GuiasApiTest`).
- [x] 3.2 Teste de API atualizado: leitura por IA agora devolve sempre 10 sessões, preservando a posição da linha ruidosa em branco em vez de descartá-la (`LancamentosApiTest`).
- [x] 3.3 Novos testes de API: confirmação com `transcricao` nula e linhas em branco misturadas (só as preenchidas viram lançamento); `numero_cartao` explícito disparando a exigência de PDF da regional 0220 mesmo sem transcrição; `sessoes` recusando mais de 10 linhas.
- [x] 3.4 `web/tests/e2e/crud-navigation.spec.ts`: heading de `/lancamentos/novo` atualizado de "Novo lançamento manual" para "Novo lançamento".
- [x] 3.5 `web/tests/e2e/mvp-flow.spec.ts`: trecho de importação de sessões atualizado para `/lancamentos/novo`, testids `lancamento-*` e seleção de guia pelo modal — **nota**: esse arquivo já tinha, antes desta mudança, asserções quebradas e não relacionadas (`/antecipacoes`, `antecipacao-row-`, `antecipacao-cota-text-`, conceito de Antecipação já removido do backend) que não foram tocadas por estarem fora do escopo desta mudança; ver observação no resumo da tarefa.
- [ ] 3.6 `php artisan test` — **não executável neste ambiente**: a única versão de PHP disponível é 8.0.30, e o projeto exige >= 8.4.1 (`composer.json`); não executado.
- [ ] 3.7 `tsc -b`, `oxlint`, `vite build` — **não executáveis neste ambiente**: não há `node`/`npm` instalados.
- [ ] 3.8 `openspec validate` — **CLI do OpenSpec não disponível neste ambiente**; spec revisada manualmente contra o formato de `modal-busca-paciente-medico` e `guias-filtro-profissional-e-busca-paciente`.
- [ ] 3.9 Verificação manual no navegador (escolher guia pelo modal, anexar imagem de teste, conferir a grade de 10 linhas, completar uma linha manualmente, confirmar) — não realizada neste ambiente, sem acesso a `npm run dev`.
