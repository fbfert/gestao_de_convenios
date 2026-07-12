## Implementação

- `PacienteController` passou a suportar `show`, `store` e `update`, mantendo `index` como endpoint de referência.
- Foi adicionado binding tenant-scoped para `paciente` em `AppServiceProvider`, garantindo `404` em acessos cross-tenant.
- Foram criados `StorePacienteRequest` e `UpdatePacienteRequest` com validação do `convenio_id` restrita ao tenant autenticado.
- `PacienteResource` passou a expor `cpf`, `telefone`, `clinica_agil_id` e `ativo`, além do convênio carregado.
- A API recebeu as rotas `GET /api/pacientes/{paciente}`, `POST /api/pacientes` e `PATCH /api/pacientes/{paciente}`.
- No frontend, foi criada a tela `Pacientes`, com lista, busca, formulário, ativação/desativação e menu no shell.

## Validação

- Backend: `php artisan test --filter=PacientesApiTest`
- Frontend: `npm run lint`
- Frontend: `npm run build`

## Resultado

- `PacientesApiTest`: 4 passed, 19 assertions
- `npm run lint`: passou
- `npm run build`: passou
