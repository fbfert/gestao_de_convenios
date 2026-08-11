## Why

`deploy/entrypoint.sh` roda apenas `php artisan migrate --force` no servidor — nunca `db:seed`. Um servidor novo sobe, portanto, sem nenhuma conta utilizável: não há primeiro login possível. As contas de exemplo (`admin@clinica-exemplo.test` e afins) só existem no `UserSeeder`, que é carga de demonstração e não roda em produção.

É preciso uma conta administrativa nominal, provisionada pelo próprio schema, para o primeiro acesso após a instalação.

## What Changes

- Nova migration `2026_08_07_100000_create_admin_inicial_fbfert` que provisiona o administrador inicial (Felipe B. Fert / `fbfert@gmail.com`) no tenant primário, com o papel `admin`.
- A migration garante o papel `admin` do tenant e o catálogo de permissões quando eles ainda não existirem, sem alterar um `admin` que já tenha permissões ajustadas na tela de Permissões.
- A mesma conta passa a existir no `UserSeeder`, para sobreviver a `migrate:fresh --seed` nos ambientes local e de testes.

## Capabilities

### New Capabilities

- `admin-inicial`: conta administrativa provisionada pelo schema para o primeiro acesso após a instalação.

### Modified Capabilities

- Nenhuma. Autenticação, papéis e permissões seguem inalterados.

## Impact

- `api/database/migrations/`, `api/database/seeders/UserSeeder.php`.
- Sem impacto no frontend nem no worker.
- **Segurança:** a senha inicial fica versionada no repositório e no histórico do Git. Ela é credencial de primeiro acesso e deve ser trocada no primeiro login; quem tem acesso ao repositório a conhece.

## Non-Goals

- Não existe papel de super administrador global: `admin` é por tenant, e esta conta é vinculada ao tenant primário.
- A migration não cria tenant. Numa base ainda sem tenant, a carga inicial continua vindo de `db:seed`.
- Não há fluxo de troca obrigatória de senha no primeiro login.
