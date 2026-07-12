## Context

`pacientes` already exists as tenant-scoped reference data for selects and filters across Solicitações, Guias and Antecipações. What is missing is a first-class CRUD for the same entity, so the team can create and maintain patients without direct database edits.

The codebase already has multi-tenant bindings, React Query, and the same admin-style CRUD pattern used for Médicos and Usuários. This change should reuse that pattern instead of introducing another one.

## Goals / Non-Goals

**Goals:**
- Provide a full patient administration flow in the API and frontend.
- Keep the tenant boundary enforced on list/detail routes through explicit route binding.
- Support create, update and soft deactivation with validation that stays aligned to the current schema.
- Preserve the current patient reference endpoint shape for existing selects.

**Non-Goals:**
- No migration to a new patient domain model or taxonomies.
- No import/mass migration tooling.
- No change to the current schema fields beyond what is needed to support CRUD.
- No changes to convênio or convênio regra behavior.

## Decisions

1. Use the existing `Paciente` model and controller namespace instead of introducing a parallel resource or service layer.
   - Rationale: this is simple tenant-owned CRUD; a separate service would add indirection without a business rule payoff.
   - Alternative considered: dedicated `PacienteService`. Rejected because the behavior is mostly persistence and validation, not domain orchestration.

2. Expose `GET /api/pacientes/{id}` with explicit tenant-scoped binding.
   - Rationale: the project already established ADR-13 for cross-tenant HTTP isolation, and patient details will likely be used in admin flows.
   - Alternative considered: relying on the global scope alone. Rejected because the binding race already bit other resources.

3. Keep list endpoint behavior compatible with existing reference-data use.
   - Rationale: Solicitações and Guias depend on `GET /api/pacientes` today; adding CRUD must not break those selects.
   - Alternative considered: separate reference endpoint and CRUD endpoint. Rejected because it would split the source of truth and increase maintenance.

4. Implement deactivation instead of physical deletion.
   - Rationale: patients already have downstream references in the flow; soft removal would be destructive and risky.
   - Alternative considered: hard delete. Rejected because it can break historical solicitations/guias.

## Risks / Trade-offs

- [Risk] Existing selects may break if the patient payload shape changes.
  - [Mitigation] Keep the current `index` resource shape stable and add the CRUD fields alongside it.
- [Risk] A detail route without binding would leak cross-tenant records.
  - [Mitigation] Bind `paciente` explicitly by `tenant_id` + `id` and cover it in feature tests.
- [Risk] Update flows may become ambiguous if there is no clear active/inactive state.
  - [Mitigation] Make `ativo` explicit in the resource and form.

## Migration Plan

1. Add backend routes, resource, requests and binding for patients.
2. Add feature tests for list/detail/create/update/deactivate and cross-tenant isolation.
3. Add frontend page and menu entry.
4. Validate build and tests.

Rollback:
- Remove the new routes, page and tests if needed.
- No destructive schema change is required for this change.
