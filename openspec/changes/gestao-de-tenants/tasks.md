## 1. Quem pode administrar clínicas

- [x] 1.1 Migration `2026_08_12_180000`: coluna `users.super_admin` e concessão ao administrador inicial.
- [x] 1.2 Cast e `ehSuperAdmin()` no model `User`, com a flag fora do `$fillable`.
- [x] 1.3 Middleware `EnsureSuperAdmin`, registrado com o alias `super-admin`.
- [x] 1.4 Expor `super_admin` no payload de login e no `authStore`.

## 2. Catálogo de papéis compartilhado

- [x] 2.1 Criar `App\Support\RoleCatalog` com o mapa papel→permissões.
- [x] 2.2 Fazer o `RoleSeeder` consumir o catálogo em vez do mapa embutido.

## 3. API de tenants

- [x] 3.1 `TenantController::index` com contagem de usuários.
- [x] 3.2 `TenantController::store` transacional: tenant, papéis com o team id do tenant novo, e o administrador inicial.
- [x] 3.3 `TenantController::update` com `slug` imutável e recusa de desativar a própria clínica.
- [x] 3.4 Form requests com slug em formato de identificador, único, e e-mail do admin único globalmente.
- [x] 3.5 Registrar as três rotas sob o middleware `super-admin`.

## 4. Tela

- [x] 4.1 `useTenants` com listagem, criação e edição, e sugestão de slug a partir do nome.
- [x] 4.2 `TenantsPage` com listagem, formulário de criação e edição em linha.
- [x] 4.3 Entrada `Clínicas` no menu, visível apenas para super admin, e rota `/clinicas`.

## 5. Validação

- [x] 5.1 `TenantsApiTest` com 7 casos: bloqueio de não super admin nos três verbos, listagem, criação completa, recusa de slug e e-mail repetidos, ausência de tenant órfão em falha, edição, e a trava da própria clínica.
- [x] 5.2 Suíte completa: 174 passam; as 5 falhas de `AnaliticosApiTest`/`AntecipacoesApiTest` são anteriores.
- [x] 5.3 `tsc -b` e `oxlint` (0 erros, 0 avisos).
- [x] 5.4 Verificação no ar: `GET /tenants` responde para super admin e devolve 403 nos três verbos para o papel funcionário; desativar a própria clínica é recusado.
- [ ] 5.5 Conferência visual da tela nos dois temas.
- [ ] 5.6 Criar uma clínica de verdade pela tela — não executado para não inserir dado de teste no banco de produção; a criação foi validada só no sqlite da suíte.
