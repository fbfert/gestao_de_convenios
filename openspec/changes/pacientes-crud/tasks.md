## 1. Backend pacientes

- [x] 1.1 Add `PacienteController` actions for show, store, update and deactivate, keeping the current index behavior for reference-data use.
- [x] 1.2 Add tenant-scoped route binding for `paciente` in `AppServiceProvider` so cross-tenant detail/update requests return 404.
- [x] 1.3 Add Form Requests and resource updates for patient create/update/deactivate payloads.
- [x] 1.4 Add or update feature tests for list, search, create, update, deactivate and cross-tenant isolation.

## 2. Frontend pacientes

- [x] 2.1 Add a `PacientesPage` feature module with list, create/edit form, deactivate action and loading/error states.
- [x] 2.2 Add a pacientes menu entry and route in the shell/navigation.
- [x] 2.3 Add React Query hooks for patients CRUD and reuse the existing reference-data list shape.

## 3. Validation

- [x] 3.1 Run backend tests for the pacientes flow.
- [x] 3.2 Run frontend build and lint.
- [x] 3.3 Update the OpenSpec change notes with the implemented scope and validation results.
