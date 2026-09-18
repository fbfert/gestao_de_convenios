## Why

Paciente inativo aparece nos formulários de **Nova guia** e de **Ler pedido médico**. Dá para abrir uma guia ou uma solicitação no nome de alguém que a clínica já desligou, e nada avisa.

A causa é uma inconsistência de default entre listagens de referência. Todas as outras escondem inativo por padrão e exigem opt-in para mostrá-lo:

| Listagem | Default hoje |
|---|---|
| Profissionais, Médicos, Especialidades | só ativos (`incluir_inativos` é opt-in) |
| Convênios | só ativos |
| **Pacientes** | **todos** (`status=ativos` é opt-in) |

`PacienteController::index` só filtra quando recebe `status`. Quem chama sem o parâmetro recebe inativo junto — e é o caso de `usePacientes`, o hook que alimenta os dois formulários. O `usePacientesBusca`, logo abaixo dele no mesmo arquivo, manda `status: 'ativos'` e por isso o modal de busca de Solicitações está correto. A inconsistência está entre dois hooks vizinhos, o que explica ter passado despercebido.

Confirmado na consulta real: inativando um paciente, a listagem sem `status` continua devolvendo-o; com `status=ativos`, não.

## What Changes

- **`GET /pacientes` passa a devolver só ativos por padrão**, alinhando-se às outras listagens de referência. Inativo passa a exigir pedido explícito.
- **`status=todos`** passa a existir para quem precisa de tudo — é a tela de gestão de Pacientes, e só ela. `ativos` e `inativos` seguem como estão.
- **O checkbox "Mostrar inativos"** da tela de Pacientes passa a mandar `todos` no lugar de string vazia.

A correção é no default do endpoint, e não nos dois hooks, porque o hook errado é sintoma: o próximo consumidor a chamar `/pacientes` sem `status` cairia na mesma armadilha em silêncio.

## Impact

**API**
- `app/Http/Controllers/PacienteController.php` — default invertido, `todos` reconhecido
- `tests/Feature/PacientesApiTest.php`

**Web**
- `features/pacientes/PacientesPage.tsx` — o checkbox manda `todos`

Sem migration, sem mudança de schema.

**Consumidores conferidos** — só três chamam `/pacientes`, todos neste repositório:

| Chamador | Manda | Depois da mudança |
|---|---|---|
| `usePacientesCrud` (tela de Pacientes) | `ativos`, ou `todos` com o checkbox | igual ao de hoje |
| `usePacientesBusca` (modal de Solicitações) | `ativos` | igual ao de hoje |
| `usePacientes` (Nova guia, Ler pedido médico) | nada | **passa a esconder inativo** — o conserto |

## Não faz parte desta change

- Bloquear guia ou solicitação já existente cujo paciente foi inativado depois. Os dois formulários afetados são de **criação**; a edição não usa este hook. Esconder o paciente de um registro já criado é outro problema, e a solução lá seria manter o valor atual visível, não sumir com ele.
- Mexer no default de profissionais, médicos, especialidades ou convênios — conferidos, e os quatro já escondem inativo.
- Avisar na tela quando o paciente escolhido for inativado depois da escolha.
