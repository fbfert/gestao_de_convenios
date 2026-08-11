## ADDED Requirements

### Requirement: Administrador inicial provisionado pelo schema
O sistema SHALL provisionar, via migration, uma conta administrativa nominal vinculada ao tenant primário com o papel `admin`, de modo que uma instalação que rode apenas as migrations tenha um primeiro login possível.

#### Scenario: Instalação em servidor novo
- **WHEN** as migrations forem executadas em uma base que já possua um tenant e não possua a conta `fbfert@gmail.com`
- **THEN** o sistema SHALL criar essa conta ativa, no tenant primário, com o papel `admin` e a senha inicial definida na migration

#### Scenario: Base ainda sem tenant
- **WHEN** as migrations forem executadas em uma base sem nenhum tenant
- **THEN** o sistema SHALL concluir a migration sem criar a conta e sem falhar, deixando a criação para a carga inicial via `db:seed`

#### Scenario: Papel admin ausente no tenant
- **WHEN** o tenant primário não possuir o papel `admin`
- **THEN** o sistema SHALL criar o papel `admin` desse tenant com o catálogo completo de permissões antes de vincular a conta

#### Scenario: Papel admin já ajustado
- **WHEN** o tenant primário já possuir um papel `admin` com permissões atribuídas
- **THEN** o sistema SHALL preservar essas permissões sem sobrescrevê-las

### Requirement: Preservação de senha já em uso
O sistema SHALL preservar a senha vigente da conta administrativa quando ela já existir, limitando-se a garantir nome, tenant, situação ativa e papel `admin`.

#### Scenario: Migration reexecutada após troca de senha
- **WHEN** a conta `fbfert@gmail.com` já existir com uma senha diferente da inicial
- **THEN** o sistema SHALL manter a senha existente e SHALL NOT redefini-la para a senha inicial

### Requirement: Conta presente na carga inicial
O sistema SHALL incluir a mesma conta administrativa no `UserSeeder`, para que ela sobreviva a `migrate:fresh --seed` nos ambientes local e de testes.

#### Scenario: Recriação do banco local
- **WHEN** o banco for recriado com `migrate:fresh --seed`
- **THEN** o sistema SHALL recriar a conta administrativa ativa com o papel `admin`
