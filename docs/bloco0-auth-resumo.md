# Bloco 0 — Resumo de Auth

Este bloco fechou a lacuna de autenticação real antes do frontend.

## Entregas

- [`api/app/Http/Controllers/AuthController.php`](../api/app/Http/Controllers/AuthController.php)
- [`api/app/Http/Requests/LoginRequest.php`](../api/app/Http/Requests/LoginRequest.php)
- [`api/database/factories/TenantFactory.php`](../api/database/factories/TenantFactory.php)
- [`api/routes/api.php`](../api/routes/api.php)
- [`api/tests/Feature/AuthApiTest.php`](../api/tests/Feature/AuthApiTest.php)

## Regras validadas

- `POST /api/login` busca `User` por e-mail globalmente, sem filtro de tenant
- login falha com `401` para senha inválida, usuário inativo ou tenant inativo
- login bem-sucedido devolve token Sanctum e payload com `user` + `tenant`
- `POST /api/logout` revoga apenas o token atual
- throttle de `5` tentativas por minuto bloqueia força bruta com `429`

## Resultado

- `6` testes
- `32` assertions

## Observação

O comportamento segue o ADR-11: `User` fica fora do `TenantScope`, porque o
login precisa resolver o e-mail antes do `TenantContext` existir.
