## ADDED Requirements

### Requirement: Solicitação com múltiplos itens
O sistema SHALL permitir que uma Solicitação possua um ou mais itens, cada item contendo especialidade, profissional executor e quantidade solicitada, sempre vinculado ao tenant da Solicitação.

#### Scenario: Criar Solicitação com múltiplos itens
- **WHEN** o operador criar uma Solicitação com dois itens válidos do tenant autenticado
- **THEN** o sistema SHALL persistir a Solicitação e ambos os itens vinculados ao mesmo tenant

#### Scenario: Item com quantidade padrão
- **WHEN** o operador não informar quantidade para um item novo
- **THEN** o sistema SHALL aplicar quantidade padrão 10 ao item

#### Scenario: Bloquear referência fora do tenant
- **WHEN** um item referenciar especialidade ou profissional de outro tenant
- **THEN** o sistema SHALL rejeitar a operação sem criar ou alterar o item

### Requirement: Compatibilidade com Solicitações legadas
O sistema SHALL manter compatibilidade com Solicitações existentes criando um item legado equivalente quando necessário e preservando os campos legados durante a transição.

#### Scenario: Backfill de item legado
- **WHEN** a migration de fundação encontrar uma Solicitação sem itens
- **THEN** o sistema SHALL criar um item com especialidade, profissional e quantidade derivados dos dados legados

#### Scenario: Exibir Solicitação legada
- **WHEN** a API retornar uma Solicitação legada já migrada
- **THEN** o sistema SHALL incluir seus itens sem remover os campos legados esperados pelas telas existentes

### Requirement: Documentos de Solicitação e item
O sistema SHALL armazenar documentos privados vinculados à Solicitação ou a um item, com tipo, nome original, mime, caminho privado e tenant.

#### Scenario: Pedido Médico geral obrigatório no fluxo Unimed
- **WHEN** a Solicitação for preparada para envio Unimed
- **THEN** o sistema SHALL exigir documento do tipo Pedido Médico vinculado à Solicitação

#### Scenario: Documento opcional por item
- **WHEN** o operador anexar Laudo, Plano ou Relatório em um item
- **THEN** o sistema SHALL persistir o documento vinculado ao item e protegido pelo tenant

#### Scenario: Acesso a documento fora do tenant
- **WHEN** um usuário tentar acessar documento de outro tenant
- **THEN** o sistema SHALL retornar erro de acesso ou não encontrado
