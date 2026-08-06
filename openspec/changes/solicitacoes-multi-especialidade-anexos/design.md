# Design

## Código do procedimento vem do convênio, não da especialidade

`Especialidade` não ganhou coluna de código. O mesmo serviço tem código diferente em cada operadora, e isso já está modelado em `convenio_especialidade_mapeamentos`.

`GET /especialidades` passou a aceitar `convenio_id` e a devolver `codigo_procedimento` do mapeamento ativo. Alternativa descartada: reusar `GET /configuracoes/unimed/mapeamentos/especialidades`, que exige `configuracoes.unimed.manage` — permissão que o operador de solicitações não tem.

## Um componente de itens para as duas telas

`SolicitacaoItensFields` é usado pelo cadastro manual e pelo fluxo de IA. O fluxo de IA lê uma especialidade só; ela entra na primeira linha e as demais são acrescentadas à mão.

Os profissionais são carregados de uma vez e filtrados por especialidade no cliente, porque um hook por linha não é possível com número variável de linhas.

## Anexos: nível da Solicitação e nível do item

`solicitacao_documentos.solicitacao_item_id` nullable já expressava os dois níveis. A validação amarra tipo e nível: `plano_individualizado` e `relatorio_evolucao` exigem item; `pedido_medico` e `laudo_medico` recusam.

Pedido Médico é único por Solicitação porque o worker o escolhe com `firstWhere`. Um segundo upload é recusado em vez de sobrescrever — o operador remove o atual primeiro, e nenhum arquivo some sem ação explícita.

O teto de 5 MB e a lista de extensões espelham o que o portal aceita, para o erro aparecer no momento do anexo e não no envio.

## Escopo dos anexos no payload do worker

`solicitacao->documentos` traz os documentos de todos os itens. O payload de um item precisa dos documentos da Solicitação **sem item** mais os do próprio item; sem esse recorte, o Plano de uma especialidade subiria na guia de outra.

## Imutabilidade depois da Guia

Depois da Guia, o anexo é evidência do que sustentou a autorização. A trava é por escopo: anexo de item trava quando aquele item tem guia; anexo da Solicitação trava quando qualquer item tem guia. Assim uma especialidade já enviada não congela o preparo das outras.

## Quantidades na consulta de status

O worker extrai `Qtd:` e `Qtd Aut:` do texto, os mesmos rótulos que `gerarGuia` já lê do HTML real fornecido pela clínica. Quando a tela não traz, retorna vazio e a API preserva o valor atual em vez de zerar.

Risco aceito: os rótulos são confirmados na tela de resultado da guia, não na de localizar guia. Sem amostra do portal real, a extração é tolerante — se o rótulo for outro, o comportamento é o de hoje e a correção é uma regex. Conferir na homologação real.

## Blocos da carteirinha em estado próprio

Derivar os cinco blocos da string concatenada fazia os dígitos migrarem entre blocos quando um bloco anterior ficava incompleto. Os blocos passaram a ser estado do formulário; a string concatenada é derivada deles.

## Rolagem horizontal na listagem de Guias

Com 12 colunas a tabela precisa de mais largura que o container. O `overflow-hidden` anterior já escondia as últimas colunas de forma inalcançável. `overflow-x-auto` mantém todas acessíveis. Alternativas para depois, se a rolagem incomodar: esconder colunas menos usadas ou mover Ações para um menu.
