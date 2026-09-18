## 1. API

- [x] 1.1 `PacienteController::index` passa a filtrar `ativo = true` quando `status` não vier, e a reconhecer `status=todos` para não filtrar nada. `ativos` e `inativos` seguem iguais.
- [x] 1.2 Comentar o porquê no código: o default permissivo era o que deixava inativo aparecer nos formulários, e a armadilha é silenciosa — quem chama sem `status` não percebe.

## 2. Web

- [x] 2.1 O checkbox "Mostrar inativos" da tela de Pacientes manda `todos` no lugar de string vazia.

## 3. Testes

- [x] 3.1 Listagem sem `status` não traz inativo.
- [x] 3.2 `status=todos` traz os dois.
- [x] 3.3 `status=inativos` traz só inativo.
- [x] 3.4 Busca por nome sem `status` não acha inativo — é o caminho que os formulários usam.
- [x] 3.5 Suíte da API verde.
- [x] 3.6 Suíte e2e rodada duas vezes: **47 passando e 1 falhando nas duas, em specs DIFERENTES** (`mvp-flow` numa, `adicionar-sessoes` na outra), e cada uma passa sozinha. Não é regressão desta change: nenhum spec de e2e inativa registro — todos criam com `ativo: true` —, então a mudança de default não cria dependência de ordem ali. É flakiness da suíte, que já aparecia antes desta change e está sendo investigada à parte.

## 4. Conferência dos vizinhos

- [x] 4.1 Confirmar que profissionais, médicos, especialidades e convênios já escondem inativo por padrão — para a correção não deixar pacientes certo e o resto errado.

## 5. O que a implementação achou além do plano

- [x] 5.1 **Dois lugares teriam quebrado em silêncio**, e só apareceram ao rastrear os consumidores antes de mexer:
  - O checkbox "Mostrar inativos" mandava string vazia. Com o default novo, passaria a trazer **só ativos** — o oposto do que o checkbox promete.
  - `PacientesPage:113` mandava `status: ''` na rota de edição, exatamente para carregar por link direto um paciente inativo. Teria deixado de abrir o formulário — o caso que aquele trecho existe para resolver. Os dois passaram a mandar `todos`.
- [x] 5.2 `PacienteCarteirinhaApiTest::listagem ordena e filtra` falhou, e **estava certo em falhar**: esperava o paciente inativo "Abel Ordenacao" numa listagem sem `status`. O alvo daquele caso é a ORDENAÇÃO (coluna inválida não vira `ORDER BY` cru), então passou a mandar `status=todos` — mantém o mesmo conjunto de dados, em vez de trocar o paciente esperado e enfraquecer a asserção.

## 6. Cobertura de navegador

- [ ] 6.1 Sem e2e para o checkbox "Mostrar inativos" nem para a edição de paciente inativo por link direto — os dois pontos corrigidos em 5.1. A suíte cobre a tela de Pacientes só de passagem. Os testes de API fixam o contrato do endpoint, que é onde a regra mora; o que fica descoberto é a tela mandar o parâmetro certo.
