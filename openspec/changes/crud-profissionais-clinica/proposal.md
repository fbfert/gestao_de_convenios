## Why

O sistema já usa `profissionais` como entidade central em guias, solicitações, lançamentos e conciliação, mas ainda não oferece um CRUD próprio para a clínica manter esses cadastros. Isso obriga a manter dados diretamente em seeders ou ajustes de backend, sem uma tela operacional para criar, editar e desativar profissionais.

## What Changes

- Criar o CRUD de profissionais da clínica com listagem, inclusão, edição e ativação/desativação.
- Expor os dados necessários para operação clínica: nome, especialidade, conselho/registro, percentual de repasse e status.
- Manter o endpoint de referência `/profissionais` para os formulários já existentes, sem quebrar os consumidores atuais.
- Adicionar permissões de gerenciamento para restringir o cadastro aos usuários autorizados.
- Disponibilizar uma tela de administração de profissionais no frontend, com busca e ações rápidas.

## Capabilities

### New Capabilities
- `profissionais-clinica`: cadastro e manutenção dos profissionais executantes da clínica, incluindo regras de permissão e integração com os selects operacionais.

### Modified Capabilities
- Nenhuma.

## Impact

- API Laravel: novos endpoints de criação e atualização de profissionais, com validação e controle por permissão.
- Frontend React: nova tela de cadastro/consulta de profissionais e atualização da navegação.
- Banco de dados: reuso da tabela `profissionais` existente, sem nova entidade.
- Permissões e seeders: inclusão de `profissionais.manage` nos papéis da aplicação.
- Fluxos existentes: formulários de solicitações, guias, lançamentos, usuários e conciliação continuam consumindo `/profissionais` como referência.
