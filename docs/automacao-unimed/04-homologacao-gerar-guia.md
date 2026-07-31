# Homologacao: gerar Guia Unimed

## Escopo

Este roteiro valida o fluxo manual "Enviar para Unimed" por item de Solicitacao.
O envio automatico ao aprovar Solicitacao permanece desabilitado para Convenios com `connector_driver=unimed_rda`.

## Pre-condicoes

- Convenio do tenant configurado como Unimed RDA em Configuracoes.
- Credencial Unimed ativa e com senha configurada.
- Solicitacao aprovada.
- Pedido Medico geral anexado a Solicitacao.
- Item com profissional, especialidade e quantidade.
- Worker local e queue worker em execucao.

## Roteiro com worker mockado

1. Abrir a tela de Solicitacoes.
2. Filtrar ou localizar uma Solicitacao aprovada de Convenio Unimed RDA.
3. Acionar "Enviar para Unimed" no item.
4. Confirmar que a API cria `automacao_execucoes` com status `queued`.
5. Rodar a fila `automacoes`.
6. Confirmar que o worker retorna `succeeded` com numero de Guia mockado.
7. Confirmar que a Guia local foi criada com `solicitacao_item_id` e `automacao_execucao_id`.
8. Confirmar que a acao fica bloqueada para o item com Guia criada.

## Resultado incerto

Simular resposta do worker com `status=uncertain`.
O sistema deve marcar a execucao como `uncertain`, nao criar Guia local e bloquear novo envio cego do mesmo item.

## Portal real autorizado

Antes do portal real, validar em ambiente assistido:

- O worker nao usa URL temporaria com `dynaHash` hardcoded.
- Navegacao interna ocorre por elementos, links ou redirects da sessao corrente.
- Screenshots e dumps tecnicos ficam em storage privado e sem credenciais.
- Timeout apos submit vira `uncertain` ate confirmacao idempotente.
