# Notas de Setup — Bloco 1 (Fundação Multi-tenant)

## Pré-requisito: `api/` precisa ser um Laravel 11 executável

Se você colocou só `app/Models`, `app/Scopes`, `app/Concerns`, `app/Http/Middleware`
e `database/migrations` direto na pasta `api/`, ela ainda não tem `artisan`,
`composer.json` nem `bootstrap/app.php` — nada roda. Use
`bootstrap-laravel-scaffold.sh` (raiz do projeto) pra preencher o resto do
esqueleto Laravel sem sobrescrever o que você já colocou, e suba o banco com
`docker compose up -d` (usa o `docker-compose.yml` da raiz).

## Registrar o `ResolveTenant` no Laravel 11

Laravel 11 não usa mais `app/Http/Kernel.php` — o registro é em
`bootstrap/app.php`:

```php
// bootstrap/app.php
->withMiddleware(function (Middleware $middleware) {
    $middleware->api(append: [
        \App\Http\Middleware\ResolveTenant::class,
    ]);
})
```

Isso garante que `ResolveTenant` roda **depois** do Sanctum já ter autenticado
o usuário (`$request->user()` disponível), preenchendo o `TenantContext` antes
de qualquer Model com `BelongsToTenant` ser consultado na mesma requisição.

## Checklist deste bloco

- [x] `App\Support\TenantContext`
- [x] `App\Scopes\TenantScope`
- [x] `App\Concerns\BelongsToTenant`
- [x] `App\Http\Middleware\ResolveTenant`
- [x] Todos os Models de `docs/schema.md` (Etapa 1), com `BelongsToTenant`
      exceto `Tenant` e `User`
- [ ] Rodar `bootstrap-laravel-scaffold.sh` (esqueleto Laravel real)
- [ ] Subir `docker compose up -d` e configurar `api/.env`
- [ ] Registrar o middleware em `bootstrap/app.php` (manual, acima)
- [ ] Rodar `php artisan migrate` (só confirma que as migrations rodam sem erro
      — ainda não prova isolamento de tenant, porque não há dado ainda)

## Teste real do isolamento de tenant — só faz sentido depois do Bloco 3

Testar `App\Models\Paciente::all()` sem dado nenhum no banco não prova nada
(vazio por falta de dado é igual a vazio por scope funcionando). O teste que
realmente comprova o `TenantScope` é: depois dos seeders do Bloco 3 criarem
pacientes pro tenant fictício, rodar `Paciente::all()` no tinker **sem**
`TenantContext::set()` ativo e confirmar que retorna vazio mesmo havendo
registros no banco. Guardar esse teste pro Bloco 3, não antes.