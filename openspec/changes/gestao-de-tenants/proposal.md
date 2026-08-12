## Why

O sistema é multi-tenant desde o ADR-01, mas **não havia nenhuma forma de configurar tenants pela aplicação**: nem rota, nem tela, nem comando. O único tenant existente veio do `TenantSeeder`. Criar uma clínica nova exigia três operações manuais no banco — inserir o tenant, criar os três papéis com o team id correto do Spatie, e criar o primeiro usuário —, e errar qualquer uma delas produzia falhas silenciosas: um tenant sem papéis nasce inacessível, e um `Role::findOrCreate` sem team id cria o papel global com `tenant_id` nulo que sombreia todos os tenants, exatamente o defeito que a migration `2026_08_05_100000` teve que consertar.

## What Changes

- Criar a tela **Clínicas** (`/clinicas`), com listagem, criação e edição de tenants.
- Criar `GET/POST/PUT /api/tenants`, restritos a super admin.
- A criação é transacional e faz os três passos de uma vez: tenant, os papéis do `RoleCatalog` com as permissões padrão, e o primeiro usuário administrador.
- Introduzir `users.super_admin`, concedida na migration ao administrador inicial.
- Extrair o mapa papel→permissões do `RoleSeeder` para `App\Support\RoleCatalog`, agora compartilhado com a criação de tenant.

## Capabilities

### New Capabilities

- `gestao-de-tenants`: cadastro das clínicas atendidas pelo sistema e provisionamento do primeiro acesso de cada uma.

### Modified Capabilities

- Nenhuma. O isolamento por `TenantScope` e a resolução via `ResolveTenant` continuam exatamente como o ADR-01 e o ADR-11 definem.

## Impact

- **API**: `TenantController`, `StoreTenantRequest`, `UpdateTenantRequest`, middleware `EnsureSuperAdmin` (alias `super-admin`), `RoleCatalog`, `super_admin` no payload de login.
- **Banco**: migration `2026_08_12_180000` adiciona `users.super_admin` (boolean, default false) e concede ao administrador inicial.
- **Frontend**: `web/src/features/tenants/`, rota `/clinicas` e a única entrada condicional do menu.
- **Testes**: `TenantsApiTest` com 7 casos.

## Decisões

- **`super_admin` é coluna, não permissão do catálogo.** Esta é a decisão central. O papel `admin` de qualquer tenant tem `permissoes.manage` e edita as atribuições pela tela de Permissões. Se "gerenciar clínicas" fosse uma permissão do `PermissionCatalog`, o administrador de uma clínica poderia conceder a si mesmo e passar a criar e alterar as outras — escalada de privilégio por desenho. A capacidade tem que viver fora do catálogo e fora do escopo de tenant, como o próprio `User` (ADR-11).
- **A flag não é editável por nenhuma tela**, e está fora do `$fillable`. Conceder e revogar é operação de banco, deliberadamente: é a única credencial do sistema que atravessa tenants.
- **Criar tenant exige o administrador inicial no mesmo request.** Um tenant sem usuário é inalcançável — não há como logar nele, e a tela de Usuários só cria pessoas no tenant de quem está logado. Deixar os dois passos separados permitiria criar clínicas mortas.
- **Não existe exclusão.** Apagar um tenant levaria junto pacientes, guias e lançamentos, ou os deixaria órfãos apontando para um tenant inexistente. Desativar já resolve o caso real: `AuthController::login` recusa quem pertence a tenant inativo.
- **O `slug` é imutável depois de criado.** Seeders e migrations consultam por ele (`where('slug', 'clinica-exemplo')`); trocá-lo quebraria essas buscas em silêncio.
- **Ninguém desativa a própria clínica.** O login recusa usuário de tenant inativo, então a operação trancaria quem a executou para fora do sistema.
- **O `RoleCatalog` é compartilhado com o seeder.** Duplicar o mapa faria uma clínica criada pela tela nascer com permissões diferentes das que o seeder entrega, e a divergência só apareceria quando alguém reclamasse de um menu faltando.

## Non-Goals

- Não há troca de clínica dentro da sessão. O tenant continua vindo de `users.tenant_id`; quem atende duas clínicas precisa de duas contas com e-mails diferentes, como o ADR-11 já pressupõe.
- Não há tela para conceder ou revogar `super_admin`.
- Não há migração de dados entre tenants, nem cópia de configuração de uma clínica para outra.
- A criação não copia convênios, especialidades ou templates de e-mail: a clínica nova nasce só com papéis e o administrador.
