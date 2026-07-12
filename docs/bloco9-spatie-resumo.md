# Bloco 9 - Permissoes, usuarios e escopo Own

Resumo do bloco que publicou o `spatie/laravel-permission`, separou permissões por tenant e aplicou o escopo "Own" nas listagens do backend.

## O que ficou pronto

- Configuração do pacote em [`api/config/permission.php`](../api/config/permission.php)
  - `teams = true`
  - `team_foreign_key = tenant_id`
- Integração do tenant com o `PermissionRegistrar` em [`api/app/Http/Middleware/ResolveTenant.php`](../api/app/Http/Middleware/ResolveTenant.php)
- Usuário passou a usar `HasRoles` em [`api/app/Models/User.php`](../api/app/Models/User.php)
  - remoção do campo legado `role`
  - adição de `profissional_id`
- Migração estrutural de usuários em [`api/database/migrations/2026_07_11_191226_update_users_for_spatie_roles.php`](../api/database/migrations/2026_07_11_191226_update_users_for_spatie_roles.php)
  - adiciona `profissional_id`
  - remove `role`
- Tabelas do pacote publicadas em [`api/database/migrations/2026_07_11_191225_create_permission_tables.php`](../api/database/migrations/2026_07_11_191225_create_permission_tables.php)
- Catálogo fixo de permissões do ADR-14 em [`api/database/seeders/PermissionSeeder.php`](../api/database/seeders/PermissionSeeder.php)
- Papéis default do tenant seedado em [`api/database/seeders/RoleSeeder.php`](../api/database/seeders/RoleSeeder.php)
- Atualização do fluxo de usuários em [`api/database/seeders/UserSeeder.php`](../api/database/seeders/UserSeeder.php)
  - admin
  - funcionario
  - profissional vinculado a um `Profissional`
- Ajuste na ordem dos seeders em [`api/database/seeders/DatabaseSeeder.php`](../api/database/seeders/DatabaseSeeder.php)
  - `ProfissionalSeeder` antes de `UserSeeder`
- Login da API adaptado ao novo modelo de role em [`api/app/Http/Controllers/AuthController.php`](../api/app/Http/Controllers/AuthController.php)
- Escopo "Own" aplicado nos serviços:
  - [`api/app/Services/GuiaService.php`](../api/app/Services/GuiaService.php)
  - [`api/app/Services/AntecipacaoService.php`](../api/app/Services/AntecipacaoService.php)
  - [`api/app/Services/LancamentoService.php`](../api/app/Services/LancamentoService.php)
  - [`api/app/Services/ConciliacaoService.php`](../api/app/Services/ConciliacaoService.php)
- Trait compartilhada para o filtro em [`api/app/Services/Concerns/AppliesOwnScope.php`](../api/app/Services/Concerns/AppliesOwnScope.php)
- Testes de feature atualizados para cobrir permissão, tenant e isolamento:
  - [`api/tests/Feature/AuthApiTest.php`](../api/tests/Feature/AuthApiTest.php)
  - [`api/tests/Feature/GuiasApiTest.php`](../api/tests/Feature/GuiasApiTest.php)
  - [`api/tests/Feature/AntecipacoesApiTest.php`](../api/tests/Feature/AntecipacoesApiTest.php)
  - [`api/tests/Feature/LancamentosApiTest.php`](../api/tests/Feature/LancamentosApiTest.php)
  - [`api/tests/Feature/ConciliacoesApiTest.php`](../api/tests/Feature/ConciliacoesApiTest.php)

## Decisões registradas

- Spatie Permission passou a respeitar `tenant_id` como chave de team.
- O usuário deixou de carregar role legado em coluna própria e passou a usar o catálogo do pacote.
- O acesso de listagem para papel `profissional` usa o escopo "Own" quando o usuário não tem permissão global.
- O backend continua bloqueando acesso sem permissão com `403`.
- O fluxo de login passou a responder com a role vinda do pacote, não mais de coluna própria.

## Validação concluída

- [`php artisan test`](../api) executado com sucesso
  - `52 passed`
  - `240 assertions`

## Validação em aberto no momento da interrupção

- A suíte E2E do bloco estava em estabilização e foi interrompida pelo pedido de encerramento.
- O caminho feliz já tinha avançado até:
  - criar solicitação
  - aprovar solicitação
  - finalizar guia
  - registrar lançamento
- A etapa de conciliação ainda estava sob investigação no último ciclo antes da interrupção.

## Arquivos principais alterados

- [`api/app/Http/Controllers/AuthController.php`](../api/app/Http/Controllers/AuthController.php)
- [`api/app/Http/Middleware/ResolveTenant.php`](../api/app/Http/Middleware/ResolveTenant.php)
- [`api/app/Models/User.php`](../api/app/Models/User.php)
- [`api/app/Services/Concerns/AppliesOwnScope.php`](../api/app/Services/Concerns/AppliesOwnScope.php)
- [`api/app/Services/GuiaService.php`](../api/app/Services/GuiaService.php)
- [`api/app/Services/AntecipacaoService.php`](../api/app/Services/AntecipacaoService.php)
- [`api/app/Services/LancamentoService.php`](../api/app/Services/LancamentoService.php)
- [`api/app/Services/ConciliacaoService.php`](../api/app/Services/ConciliacaoService.php)
- [`api/database/seeders/DatabaseSeeder.php`](../api/database/seeders/DatabaseSeeder.php)
- [`api/database/seeders/UserSeeder.php`](../api/database/seeders/UserSeeder.php)
- [`api/database/seeders/PermissionSeeder.php`](../api/database/seeders/PermissionSeeder.php)
- [`api/database/seeders/RoleSeeder.php`](../api/database/seeders/RoleSeeder.php)
- [`api/tests/Feature/AuthApiTest.php`](../api/tests/Feature/AuthApiTest.php)
- [`api/tests/Feature/AntecipacoesApiTest.php`](../api/tests/Feature/AntecipacoesApiTest.php)
- [`api/tests/Feature/ConciliacoesApiTest.php`](../api/tests/Feature/ConciliacoesApiTest.php)
- [`api/tests/Feature/GuiasApiTest.php`](../api/tests/Feature/GuiasApiTest.php)
- [`api/tests/Feature/LancamentosApiTest.php`](../api/tests/Feature/LancamentosApiTest.php)
- [`docs/decisoes-arquitetura.md`](../docs/decisoes-arquitetura.md)

