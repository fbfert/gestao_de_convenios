## Why

A tela de permissões existe (`/permissoes`) mas não está no menu: só se chega digitando a URL. Pior, ela não tem efeito visível — o menu mostra todas as telas para todos os usuários e o clique só falha com 403 na API. E os papéis são três fixos (`admin`, `funcionario`, `profissional`), criados pelo seeder e pela criação de clínica; não há como uma clínica criar um papel próprio.

Na raiz dos dois problemas está o mesmo defeito: **a API nunca devolve as permissões do usuário**. O `authStore` tem o campo `permissions`, mas nem o login nem `GET /user` o preenchem, então o único ponto do frontend que consulta permissão (`ConfiguracoesPage`) cai sempre no fallback `role === 'admin'`. Com papéis customizáveis esse fallback quebra de vez: um papel novo com `configuracoes.unimed.manage` não veria a aba da Unimed.

## What Changes

- Devolver as permissões efetivas do usuário no login e em `GET /user`, e recarregá-las na abertura do app para uma mudança de papel valer sem novo login.
- Filtrar o menu pelas permissões do usuário, escondendo o que ele não pode acessar.
- Levar `Perfis e Permissões` e `Logs de Auditoria` para o submenu de Configurações — hoje as duas telas existem fora do menu.
- Permitir criar, duplicar, renomear e excluir papéis por clínica, com `admin`, `funcionario` e `profissional` protegidos como papéis de sistema.
- Impedir que a clínica perca a capacidade de administrar permissões.
- **BREAKING**: `GET /user` deixa de devolver o model cru e passa a devolver o mesmo formato do bloco `user` do login.

## Capabilities

### New Capabilities

- `perfis-e-permissoes`: papéis por clínica, permissões efetivas no payload de autenticação e menu coerente com o que o usuário pode acessar.

### Modified Capabilities

- Nenhuma.

## Non-goals

- Múltiplos papéis por usuário: segue um papel por usuário. Quem precisa de uma combinação cria um papel com ela.
- Editar o catálogo de permissões: `PermissionCatalog` continua fixo em código. A clínica combina permissões existentes, não inventa permissões.
- Auditoria: cobertura da trilha, tela com filtros e retenção ficam em change próprio (fases 3 a 5 da estratégia). Aqui a Auditoria só ganha entrada no menu.

## Impact

- API: `AuthController`, rota `GET /user`, novo CRUD de papéis, `RoleCatalog` como fonte dos papéis de sistema.
- Frontend: `authStore`, `ShellLayout`, `navigation.ts`, tela de Perfis e Permissões, `ConfiguracoesPage`.
- Testes: `AuthApiTest` (novo formato do payload), testes do CRUD de papéis e dos guard-rails.
- Sem migration: `roles` já tem `tenant_id`.
