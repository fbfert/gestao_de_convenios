## ADDED Requirements

### Requirement: Inactive patients are hidden from the patient list by default

The system SHALL exclude inactive patients from the patient list unless the caller explicitly asks for them, so that a patient the clinic has deactivated is never offered as a choice in a form that creates records.

The system SHALL accept an explicit request for the full list, including inactive patients, for the patient management screen.

This default SHALL match the other reference listings — professionals, doctors, specialties and health plans — which already hide inactive entries unless asked otherwise.

#### Scenario: List without asking for status
- **WHEN** an authenticated user requests the patient list without specifying a status
- **THEN** the system SHALL return only active patients

#### Scenario: Form select does not offer an inactive patient
- **WHEN** a patient is deactivated and a user opens a form that lists patients to create a new record
- **THEN** the system SHALL NOT offer that patient

#### Scenario: Management screen asks for everyone
- **WHEN** the caller explicitly requests all patients
- **THEN** the system SHALL return active and inactive patients together

#### Scenario: Asking only for inactive
- **WHEN** the caller explicitly requests inactive patients
- **THEN** the system SHALL return only inactive patients

#### Scenario: Search still honours the default
- **WHEN** the caller searches by name or card number without specifying a status
- **THEN** the system SHALL match only active patients

#### Scenario: A patient reactivated comes back
- **WHEN** an inactive patient is set active again
- **THEN** the system SHALL offer that patient in the lists that hide inactive ones
