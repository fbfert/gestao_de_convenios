## Why

As especialidades já existem como dado de referência central do sistema, mas hoje elas só são consumidas indiretamente pelos fluxos operacionais. Falta um CRUD próprio para a clínica manter esse cadastro sem depender de seed, banco ou edição manual.

## What Changes

- Criar uma área autenticada para listar, criar, editar e inativar especialidades.
- Expor endpoints de manutenção com isolamento por tenant e validação de unicidade do nome dentro da clínica.
- Reaproveitar `ativo` como exclusão lógica, evitando remoção física de registros usados por profissionais, guias, solicitações e tabelas de valor.
- Manter o consumo das especialidades ativas pelos formulários operacionais existentes.
- Adicionar o controle de acesso do CRUD ao catálogo de permissões do sistema.

## Capabilities

### New Capabilities
- `crud-especialidades-clinica`: manutenção completa do cadastro de especialidades, incluindo listagem, criação, edição, inativação e reativação lógica por tenant.

### Modified Capabilities
- Nenhuma.

## Impact

- API Laravel: novos endpoints de CRUD para especialidades, regras de validação e verificação de tenant.
- Frontend React: nova tela de especialidades com formulário, listagem, filtros de ativo/inativo e ações de manutenção.
- Permissões: inclusão de permissão de gerenciamento de especialidades no catálogo fixo e na tela de papéis.
- Banco de dados: reaproveitamento do modelo atual com exclusão lógica via `ativo`, sem migração estrutural nova.
- Fluxos existentes: formulários de guias, solicitações, profissionais e valores continuam lendo especialidades ativas normalmente.
