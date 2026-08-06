## Why

A revisão do fluxo Unimed encontrou pedidos ainda abertos. O backend já modelava vários itens por Solicitação e uma tabela de documentos, mas nenhuma tela usava: o formulário mandava sempre um item só, e não existia rota de upload para Laudo, Plano Individualizado e Relatório de Evolução. A listagem de Guias também juntava sessões solicitadas e autorizadas em uma coluna só, e a carteirinha Unimed aparecia sem separadores.

## What Changes

- Substituir os campos únicos de especialidade/profissional por uma lista de itens no cadastro manual e no fluxo de leitura do pedido médico.
- Expor o código do procedimento do convênio na listagem de especialidades usada pelo pedido.
- Criar rotas de anexo, download e remoção de documentos da Solicitação e por item.
- Travar a remoção de anexo depois que a Guia correspondente existir.
- Separar `Nº de Sessões` e `Sessões Autorizadas` na listagem de Guias.
- Padronizar leitura e digitação da carteirinha Unimed em 4+4+6+2+1 e exigir carteirinha válida no cadastro rápido de paciente.
- Persistir sessões autorizadas revisadas pela operadora na consulta de status.

## Non-Goals

- Renomear a aba "Unimed RDA" em Configurações.
- Gerar Guia para execução `uncertain` ou `failed`; revisar só depois da homologação real.
- Tornar o Pedido Médico obrigatório no cadastro manual de Solicitação.
- Bloquear especialidade repetida no mesmo pedido; por ora é aviso.

## Capabilities

### Modified Capabilities
- `solicitacoes-multi-itens`: entrada de vários itens pela UI, documentos por Solicitação e por item, e regras de imutabilidade após a Guia.

## Impact

- Backend Laravel: `SolicitacaoDocumentoController`, `StoreSolicitacaoDocumentoRequest`, `SolicitacaoController::storePacienteRapido`, `EspecialidadeController`, `SolicitacaoResource`, `EspecialidadeResource`, `GerarGuiaUnimedService`, `ConsultarStatusUnimedService`.
- Frontend React: `SolicitacaoItensFields`, `SolicitacaoAnexos`, `CarteirinhaUnimedInput`, `lib/carteirinha`, telas de Solicitações, Ler Pedido Médico, Pacientes e Guias.
- Worker Node: leitura de quantidades na consulta de status.
- Sem migrations: `solicitacao_itens` e `solicitacao_documentos` já existiam.
- Documentação em `docs/solicitacoes-multi-especialidade-anexos.md`.
