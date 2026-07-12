# Bloco 11 - Permissoes e Usuarios

Resumo do fechamento do CRUD de permissões por papel e do CRUD de usuários no tenant, seguindo o Spatie Permission com teams.

## O que entrou no backend

- Catálogo fixo de permissões centralizado em [`api/app/Support/PermissionCatalog.php`](../api/app/Support/PermissionCatalog.php)
- CRUD de permissões:
  - [`api/app/Http/Controllers/PermissionController.php`](../api/app/Http/Controllers/PermissionController.php)
  - [`api/app/Http/Controllers/RoleController.php`](../api/app/Http/Controllers/RoleController.php)
  - [`api/app/Http/Controllers/RolePermissionController.php`](../api/app/Http/Controllers/RolePermissionController.php)
  - [`api/app/Http/Requests/UpdateRolePermissionsRequest.php`](../api/app/Http/Requests/UpdateRolePermissionsRequest.php)
  - [`api/app/Http/Resources/PermissionResource.php`](../api/app/Http/Resources/PermissionResource.php)
  - [`api/app/Http/Resources/RoleResource.php`](../api/app/Http/Resources/RoleResource.php)
- CRUD de usuários:
  - [`api/app/Http/Controllers/UserController.php`](../api/app/Http/Controllers/UserController.php)
  - [`api/app/Http/Requests/StoreUsuarioRequest.php`](../api/app/Http/Requests/StoreUsuarioRequest.php)
  - [`api/app/Http/Requests/UpdateUsuarioRequest.php`](../api/app/Http/Requests/UpdateUsuarioRequest.php)
  - [`api/app/Http/Resources/UserResource.php`](../api/app/Http/Resources/UserResource.php)
- Bindings tenant-scoped em [`api/app/Providers/AppServiceProvider.php`](../api/app/Providers/AppServiceProvider.php)
  - `role`
  - `usuario`
- Rotas protegidas em [`api/routes/api.php`](../api/routes/api.php)
- Seeders reaproveitados:
  - [`api/database/seeders/PermissionSeeder.php`](../api/database/seeders/PermissionSeeder.php)
  - [`api/database/seeders/RoleSeeder.php`](../api/database/seeders/RoleSeeder.php)
  - [`api/database/seeders/UserSeeder.php`](../api/database/seeders/UserSeeder.php)
  - [`api/database/seeders/DatabaseSeeder.php`](../api/database/seeders/DatabaseSeeder.php)

## O que entrou no frontend

- Tela simples de permissões:
  - [`web/src/features/permissoes/PermissoesPage.tsx`](../web/src/features/permissoes/PermissoesPage.tsx)
  - [`web/src/features/permissoes/usePermissoes.ts`](../web/src/features/permissoes/usePermissoes.ts)
  - [`web/src/features/permissoes/types.ts`](../web/src/features/permissoes/types.ts)
  - [`web/src/features/permissoes/index.ts`](../web/src/features/permissoes/index.ts)
- CRUD de usuários:
  - [`web/src/features/usuarios/UsuariosPage.tsx`](../web/src/features/usuarios/UsuariosPage.tsx)
  - [`web/src/features/usuarios/useUsuarios.ts`](../web/src/features/usuarios/useUsuarios.ts)
  - [`web/src/features/usuarios/types.ts`](../web/src/features/usuarios/types.ts)
  - [`web/src/features/usuarios/index.ts`](../web/src/features/usuarios/index.ts)
- Navegação atualizada:
  - [`web/src/routes/ShellLayout.tsx`](../web/src/routes/ShellLayout.tsx)
  - [`web/src/routes/AppRoutes.tsx`](../web/src/routes/AppRoutes.tsx)

## Decisões registradas

- O catálogo de permissões permanece fixo no código e o CRUD só edita a atribuição dos papéis.
- O usuário continua pertencendo a um tenant e o CRUD não cria profissional inline quando o papel é profissional.
- O binding de `role` e `usuario` é filtrado pelo tenant autenticado, mantendo o 404 cross-tenant.
- O cache do Spatie é limpo no fim do `DatabaseSeeder` para evitar estado velho entre seeds e testes locais.

## Validações feitas

- [`php artisan test --filter=PermissionsApiTest`](../api) passou com `5 passed / 16 assertions`
- [`php artisan test --filter=UsuariosApiTest`](../api) passou com `6 passed / 17 assertions`
- [`npm run build`](../web) passou
- [`npm run lint`](../web) passou
- `http://127.0.0.1:5173/usuarios` responde com a nova tela navegável no frontend local

## Estado atual

- A tela de permissões está navegável no frontend local.
- O CRUD de usuários está disponível na API e também na UI administrativa.
