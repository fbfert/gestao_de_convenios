## 1. Solicitação com várias especialidades

- [x] 1.1 Ler `SolicitacaoService`, `StoreSolicitacaoRequest`, `SolicitacoesPage` e `LerPedidoMedicoPage` para confirmar o que já existia no backend.
- [x] 1.2 Criar `SolicitacaoItensFields` com uma linha por especialidade, profissional filtrado pela especialidade e quantidade.
- [x] 1.3 Usar o componente no cadastro manual e no fluxo de leitura do pedido médico.
- [x] 1.4 Avisar sobre especialidade repetida sem bloquear o envio.
- [x] 1.5 Expor `codigo_procedimento` por convênio em `GET /especialidades` e mostrar no select.

## 2. Anexos

- [x] 2.1 Definir tipos e níveis em `SolicitacaoDocumento`.
- [x] 2.2 Criar `StoreSolicitacaoDocumentoRequest` com regras de tipo, nível, extensão e tamanho.
- [x] 2.3 Criar `SolicitacaoDocumentoController` com store, download e destroy.
- [x] 2.4 Recusar segundo Pedido Médico em vez de sobrescrever.
- [x] 2.5 Travar remoção depois da Guia, por escopo de item e de solicitação.
- [x] 2.6 Criar `SolicitacaoAnexos` e ligar ao modal de detalhe, com botão de acesso na listagem.
- [x] 2.7 Corrigir `documentosPayload` para não vazar anexo de uma especialidade na guia de outra.

## 3. Carteirinha Unimed

- [x] 3.1 Extrair `lib/carteirinha` e `CarteirinhaUnimedInput`.
- [x] 3.2 Manter os blocos em estado próprio para os dígitos não migrarem na edição.
- [x] 3.3 Formatar a exibição no detalhe da guia, listagens e selects.
- [x] 3.4 Exigir carteirinha válida no cadastro rápido de paciente.

## 4. Guias

- [x] 4.1 Separar `Nº de Sessões` e `Sessões Autorizadas`.
- [x] 4.2 Trocar `overflow-hidden` por `overflow-x-auto` para as colunas finais voltarem a ser acessíveis.
- [x] 4.3 Ler quantidades na consulta de status no worker e persistir sem sobrescrever com vazio.

## 5. Testes e Documentação

- [x] 5.1 Cobrir anexos, trava pós-guia, cadastro rápido e escopo de anexo por item em testes backend.
- [x] 5.2 Cobrir extração de quantidades no worker, com e sem a informação na tela.
- [x] 5.3 Cobrir pedido com duas especialidades e anexo por especialidade em E2E.
- [x] 5.4 Corrigir `AnaliticosApiTest`, que só passava no mês em que foi escrito.
- [x] 5.5 Remover as cópias em conflito do Dropbox após confirmar que eram versões antigas.
- [x] 5.6 Criar `docs/solicitacoes-multi-especialidade-anexos.md`.
- [x] 5.7 Executar backend, worker, E2E, lint e build.

## 6. Pendências

- [ ] 6.1 Conferir na homologação real o rótulo da quantidade autorizada na tela de localizar guia.
- [ ] 6.2 Decidir, após a homologação, se `uncertain` e `failed` passam a gerar Guia.
