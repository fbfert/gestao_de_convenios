## ADDED Requirements

### Requirement: Patient list remains tenant-scoped and searchable
The system MUST list patients belonging only to the authenticated tenant. The list endpoint MUST support search by patient name and card number, and MUST preserve the reference-data shape used by existing selects.

#### Scenario: List only current tenant patients
- **WHEN** an authenticated user requests the patients list
- **THEN** the system returns only patients from the user tenant

#### Scenario: Search by name or card number
- **WHEN** an authenticated user requests the patients list with `busca`
- **THEN** the system returns patients whose name or `carteirinha` matches the search term

### Requirement: Patient detail must respect tenant isolation
The system MUST expose patient detail by id and MUST return 404 when the requested patient does not belong to the authenticated tenant.

#### Scenario: Tenant-scoped detail lookup
- **WHEN** an authenticated user requests `GET /api/pacientes/{id}` for a patient in another tenant
- **THEN** the system returns 404

### Requirement: Patient creation must validate tenant-owned reference data
The system MUST allow creation of a patient for the authenticated tenant. The request MUST validate required fields and MUST reject references that do not belong to the same tenant when applicable.

#### Scenario: Successful patient creation
- **WHEN** an authenticated user submits a valid patient payload
- **THEN** the system creates the patient under the current tenant and returns the created record

#### Scenario: Invalid payload is rejected
- **WHEN** an authenticated user submits a patient payload missing required fields
- **THEN** the system returns 422

### Requirement: Patient updates must preserve tenant isolation
The system MUST allow updating an existing patient only within the authenticated tenant.

#### Scenario: Successful patient update
- **WHEN** an authenticated user updates a patient from the same tenant
- **THEN** the system stores the changes and returns the updated record

#### Scenario: Cross-tenant update is blocked
- **WHEN** an authenticated user attempts to update a patient from another tenant
- **THEN** the system returns 404

### Requirement: Patient deactivation must be supported without physical deletion
The system MUST support deactivating a patient by toggling its active state. The system MUST not require physical deletion for tenant administration.

#### Scenario: Deactivate patient
- **WHEN** an authenticated user disables a patient
- **THEN** the system stores the patient as inactive and returns the updated record
