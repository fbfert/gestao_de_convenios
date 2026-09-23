## MODIFIED Requirements

### Requirement: Alerta de sessões em conflito no dashboard

O sistema SHALL apresentar, no grupo de alertas de guias do dashboard, a contagem de guias cujas sessões registradas violam o intervalo mínimo ou o limite diário.

O alerta SHALL levar à lista dessas guias, e SHALL desaparecer quando não houver nenhuma.

A lista aberta pelo alerta SHALL conter somente as guias em conflito, e SHALL indicar que está filtrada, permitindo remover o filtro.

#### Scenario: Existem guias em conflito
- **WHEN** houver guias com sessões em conflito
- **THEN** o sistema SHALL exibir o alerta com a contagem e SHALL permitir abrir a lista delas

#### Scenario: Nenhuma guia em conflito
- **WHEN** não houver guia com sessões em conflito
- **THEN** o sistema SHALL NOT exibir o alerta

#### Scenario: Lista aberta pelo alerta
- **WHEN** o usuário abrir a lista a partir do alerta
- **THEN** o sistema SHALL listar somente as guias em conflito e SHALL exibir o filtro ativo, removível
