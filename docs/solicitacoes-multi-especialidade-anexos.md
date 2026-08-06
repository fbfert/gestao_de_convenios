# Solicitações com várias especialidades, anexos e ajustes de Guias

## Escopo

Fecha sete pedidos levantados na revisão do fluxo Unimed. Três já estavam prontos e foram apenas confirmados; quatro exigiram implementação.

| # | Pedido | Situação |
|---|---|---|
| 1 | Login/senha Unimed em Configurações, com log de quem editou | Já pronto |
| 2 | Carteirinha Unimed no padrão 4+4+6+2+1 | Cadastro pronto; exibição e edição corrigidas |
| 3 | Várias especialidades por pedido, profissional por especialidade, anexos | Implementado |
| 4 | Colunas de sessões na listagem de Guias | Implementado |
| 5 | Solicitação só vira Guia depois da automação | Já pronto |
| 6 | Carteirinha visível na Guia | Já pronto; passou a ser formatada |
| 7 | Validade da senha na tabela de Guias | Já pronto |

"Guias" e "Controle da guia e do prazo de senha" são a mesma tela (`/guias`): o segundo é o título do cabeçalho.

## Solicitação com várias especialidades

O backend já aceitava `itens[]` desde `2026_07_31_100000_create_solicitacao_itens_table`, mas nenhuma tela enviava mais de um item — o formulário tinha um select único de especialidade e outro de profissional.

`SolicitacaoItensFields` passou a ser a entrada única de itens, compartilhada pelo cadastro manual (`SolicitacoesPage`) e pelo fluxo de leitura por IA (`LerPedidoMedicoPage`):

- uma linha por especialidade, cada uma com o seu profissional executante e quantidade;
- o select de profissional é filtrado pelos profissionais da especialidade da linha e fica bloqueado até ela ser escolhida;
- a última linha não pode ser removida — o pedido precisa de pelo menos uma especialidade;
- especialidade repetida gera aviso, não bloqueio: cada item vira uma guia separada na operadora, e há casos em que isso é intencional.

No fluxo de IA, a especialidade lida do pedido médico entra na primeira linha; as demais são acrescentadas à mão. A IA lê uma especialidade só.

### Código do procedimento

O código é definido por convênio (`convenio_especialidade_mapeamentos.codigo_procedimento`), não pela especialidade — o mesmo serviço tem código diferente em cada operadora. Por isso a `Especialidade` não ganhou coluna de código.

`GET /especialidades` aceita `convenio_id` e devolve `codigo_procedimento` do mapeamento ativo daquele convênio. O select mostra `Fonoaudiologia · 50000470`.

Decisão: não reusar `GET /configuracoes/unimed/mapeamentos/especialidades`, porque essa rota exige `configuracoes.unimed.manage` e o operador que cria solicitação não tem essa permissão.

## Anexos

| Tipo | Nível | Obrigatório |
|---|---|---|
| `pedido_medico` | Solicitação | Sim, para envio à Unimed |
| `laudo_medico` | Solicitação | Não |
| `plano_individualizado` | Item (especialidade) | Não |
| `relatorio_evolucao` | Item (especialidade) | Não |

Os tipos são constantes em `SolicitacaoDocumento::TIPOS`, `TIPOS_DA_SOLICITACAO` e `TIPOS_POR_ITEM`.

A tabela `solicitacao_documentos` já existia com `solicitacao_item_id` nullable, mas não havia nenhuma rota de upload: o único documento criado era o `pedido_medico`, e só pelo caminho da IA.

### Endpoints

```
POST   /solicitacoes/{solicitacao}/documentos
GET    /solicitacoes/{solicitacao}/documentos/{documento}
DELETE /solicitacoes/{solicitacao}/documentos/{documento}
```

Arquivos ficam no disco `local` (privado), em `solicitacoes/{tenant}/{solicitacao}/{uuid}.{ext}`.

### Regras

- Aceita imagem (JPG, PNG, GIF) ou PDF, até 5 MB. O teto espelha o limite do portal da Unimed
  (`worker-unimed/src/operations/gerarGuia.js`), para o erro aparecer no anexo e não no envio.
- `plano_individualizado` e `relatorio_evolucao` exigem `solicitacao_item_id`; `pedido_medico` e
  `laudo_medico` recusam esse campo. O item precisa pertencer à solicitação.
- Um segundo `pedido_medico` é recusado com mensagem explícita — o worker escolhe por
  `firstWhere('tipo', 'pedido_medico')` e dois arquivos tornariam o envio ambíguo. Nada é
  sobrescrito em silêncio: o operador remove o atual primeiro.
- Anexar `pedido_medico` também alimenta os campos legados `pedido_medico_*` da Solicitação, que
  ainda sustentam a tela de detalhe e o botão "Abrir pedido".

### Trava depois da Guia

Depois que a Guia existe, o anexo é a evidência do que foi enviado à operadora e deixa de ser removível:

- anexo de item: travado quando **aquele** item tem guia; as outras especialidades continuam editáveis;
- anexo da solicitação: travado quando **qualquer** item tem guia.

Para convênios não-Unimed a guia nasce na aprovação, então os anexos travam ao aprovar. Para Unimed, quando a automação gera a guia. A API responde 422; a UI troca "Remover" pelo selo "Guia gerada".

### Escopo por item no payload do worker

`GerarGuiaUnimedService::documentosPayload` envia à operadora os documentos da solicitação **sem item** mais os do item corrente. Sem esse recorte, `solicitacao->documentos` traria os anexos de todos os itens e o Plano de uma especialidade subiria na guia de outra.

## Carteirinha Unimed

Os 17 dígitos continuam guardados corridos; os blocos existem só para digitação e leitura. `web/src/lib/carteirinha.ts` é a fonte única de `UNIMED_BLOCK_SIZES`, split/join e `formatUnimedCarteirinha`.

- **Edição**: os blocos passaram a viver em estado próprio. Antes eram refatiados da string concatenada a cada tecla, então apagar um dígito no meio empurrava os dígitos dos blocos seguintes.
- **Exibição**: `formatUnimedCarteirinha` mostra `0012 3456 789012 34 5` no detalhe da guia, nas listagens e nos selects. Valores que não têm exatamente 17 dígitos — outros convênios, cadastros legados — passam intactos.
- **Cadastro rápido**: `POST /solicitacoes/pacientes-rapido` exigia só nome e convênio e gerava
  `PEDIDO-MEDICO-XXXXXXXX` como carteirinha. Esse paciente nunca conseguiria gerar guia. Agora a
  carteirinha é obrigatória e, para convênio `unimed_rda`, vale a mesma regra dos 17 dígitos do
  cadastro completo. Os blocos vêm de `CarteirinhaUnimedInput`, compartilhado com a tela de Pacientes.

## Guias

`Sessões · 10 / 8` virou duas colunas: **Nº de Sessões** e **Sessões Autorizadas**.

Com 12 colunas a tabela passou a precisar de ~1520px contra ~986px de container. O container usava `overflow-hidden`, o que já vinha cortando Senha, Validade, Última consulta e Ações — inclusive o botão de gerar conciliação — sem nenhuma forma de alcançá-las. Passou a `overflow-x-auto`: a tabela rola na horizontal e todas as colunas ficam acessíveis.

### Sessões autorizadas na consulta de status

A operadora pode revisar a quantidade autorizada depois da guia gerada. Havia duas lacunas:

- `ConsultarStatusUnimedService::aplicarResultado` descartava `sessoes_*` mesmo se viessem;
- o worker não lia quantidade nenhuma na consulta de status.

Agora o worker extrai `Qtd:` e `Qtd Aut:` do texto da listagem, usando os mesmos rótulos que `gerarGuia` já lê do HTML real, e a API persiste **apenas quando o portal informou** o número — sem o campo, o valor atual é preservado, não zerado.

Pendência para a Etapa 5: os rótulos são confirmados na tela de *resultado da guia*. Não há amostra da tela de *localizar guia* do portal real, então o rótulo dela precisa ser conferido com o portal aberto. Se for diferente, é trocar uma regex em `lerQuantidades`.

## O que não mudou

- A aba de Configurações continua chamando "Unimed RDA" e concentra credencial e mapeamentos.
- Solicitação Unimed continua virando Guia só pela automação, com qualquer situação reportada pela
  operadora, inclusive restrição administrativa (`needs_verification`). `uncertain` e `failed`
  seguem sem gerar guia: nesses casos a operadora não reportou situação alguma. Revisitar depois da
  homologação real.
- O caminho manual de solicitação continua podendo ser criado sem anexo; o Pedido Médico entra
  depois, pelo modal de anexos.

## Rollback

Reverter derruba as rotas de documentos e a UI de anexos. A tabela `solicitacao_documentos` é anterior a esta mudança e não exige migração — documentos já enviados continuam no disco e no banco, apenas sem tela. O formulário volta a mandar um item por solicitação; solicitações com vários itens continuam válidas no banco e nas listagens.
