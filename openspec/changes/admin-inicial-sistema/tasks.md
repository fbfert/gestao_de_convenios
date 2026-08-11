## 1. Provisionamento pelo schema

- [x] 1.1 Criar a migration `2026_08_07_100000_create_admin_inicial_fbfert`, idempotente e sem sobrescrever senha existente.
- [x] 1.2 Garantir papel `admin` do tenant e catálogo de permissões apenas quando ausentes.

## 2. Carga inicial

- [x] 2.1 Incluir a mesma conta no `UserSeeder` com o papel `admin`.
- [x] 2.2 Ajustar `UsuariosApiTest::test_lista_usuarios_da_clinica_exemplo`, que fixava 3 contas semeadas.

## 3. Validação

- [x] 3.1 `migrate` numa base sem tenant: migration conclui sem criar conta e sem falhar.
- [x] 3.2 `migrate` numa base com tenant e sem a conta: cria a conta com papel `admin` e as 33 permissões.
- [x] 3.3 `migrate:fresh --seed`: conta recriada, ativa, papel `admin`, senha inicial válida.
- [x] 3.4 Idempotência: com senha já trocada, a migration preserva a senha e só restaura nome, `ativo` e papel.
- [x] 3.5 `POST /api/login` com a credencial retorna 200, papel `admin`; `GET /api/usuarios` com o token retorna 200.
- [x] 3.6 `php artisan test` (172 passando) e `openspec validate admin-inicial-sistema`.
