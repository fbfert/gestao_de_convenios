## 1. Permissões

- [x] 1.1 `PermissionCatalog`: adicionar `medicos.view` (rótulo "Consultar médicos").
- [x] 1.2 `RoleCatalog`: adicionar `medicos.view` aos papéis `admin` e `funcionario` (junto de `medicos.manage`, já presente nos dois).
- [x] 1.3 Migração `2026_09_04_120000_sync_medicos_view_permission_to_existing_roles.php`: concede `medicos.view` a todo papel de tenant existente que já tenha `medicos.manage`, seguindo o padrão de `2026_08_12_120000_sync_dashboard_especialidades_analiticos_permissions.php`.
- [x] 1.4 `routes/api.php`: `GET /medicos` e novo `GET /medicos/recentes` aceitam `permission:medicos.view|medicos.manage`.

## 2. API — busca, paginação e recentes

- [x] 2.1 `PacienteController@index`: paginação opcional via `App\Support\PaginaListagem` (só pagina quando `page` vem na query string; sem ela mantém `->get()` de sempre), preservando filtros e ordenação existentes.
- [x] 2.2 `MedicoController@index`: mesmo helper `PaginaListagem`, preservando `busca` e ordenação existentes.
- [x] 2.3 `ProfissionalController@index`: mesmo helper `PaginaListagem`, preservando `busca`, `especialidade_id`, `incluir_inativos` e ordenação existentes.
- [x] 2.4 Novo endpoint `GET /pacientes/recentes`: `PacienteController@recentes`, agrupa `solicitacoes` por `paciente_id`, pega os 10 com `MAX(created_at)` mais recente (tenant já é escopado pelo trait `BelongsToTenant` do model `Solicitacao`).
- [x] 2.5 Novo endpoint `GET /medicos/recentes`: `MedicoController@recentes`, mesma lógica com `medico_id`.
- [x] 2.6 Resources já expunham os campos necessários (`PacienteResource`: cpf, carteirinha, data_nascimento; `MedicoResource`: crm, crm_uf, especialidade_medica) — nenhuma mudança necessária.

## 3. Frontend — hooks e API client

- [x] 3.1 `useReferenceData.ts`: novos hooks `usePacientesBusca`/`useMedicosBusca` (paginados, com `busca`/`page`/`enabled`) — os hooks existentes `usePacientes`/`useMedicos` ficaram intocados de propósito, para não arriscar quebrar quem ainda os usa sem paginação (ex.: `LerPedidoMedicoPage`).
- [x] 3.2 Novos hooks `usePacientesRecentes()` e `useMedicosRecentes()` consumindo os endpoints de recentes.
- [x] 3.3 (descoped) Consolidar `useReferenceData.ts`/`features/medicos/useMedicos.ts` não é necessário para esta mudança — os dois hooks antigos continuam servindo usos distintos (form de Solicitação vs. CRUD de Médicos) e mexer neles é risco de regressão fora do escopo pedido.

## 4. Frontend — componentes de modal

- [x] 4.1 Criado `SelecionarPacienteModal` em `web/src/features/solicitacoes/SelecionarPacienteModal.tsx` (não em `components/ui`: o cadastro rápido é específico do fluxo de Solicitações, então o componente mora na feature, junto dos hooks `useCriarPacienteRapido`/`useCriarMedicoRapido`). `Dialog` do Headless UI, busca com debounce 2 caracteres/500ms, recentes ao abrir vazio (filtrados por convênio quando informado), item com CPF/carteirinha, "carregar mais" quando pagina, cadastro rápido (nome + carteirinha, com `CarteirinhaBlocosInput` quando o convênio define blocos) quando a busca não acha nada.
- [x] 4.2 Criado `SelecionarMedicoModal` em `web/src/features/solicitacoes/SelecionarMedicoModal.tsx` — mesma estrutura, item com especialidade + CRM, cadastro rápido (nome, CRM, UF, especialidade opcionais).
- [x] 4.3 Botão de gatilho inline em cada integração (mesmo visual do `Select` anterior — `h-11 rounded-2xl border border-white/10 bg-white/5`), mostrando nome selecionado ou placeholder, abrindo o modal correspondente.

## 5. Frontend — integração nos formulários

- [x] 5.1 `SolicitacoesPage.tsx`: campos de Paciente e Médico trocados pelos modais. Removida a busca da lista inteira (`usePacientes`/`useMedicos`) e os `useEffect`s que pré-selecionavam o primeiro paciente/médico em ordem alfabética — com busca explícita, o campo agora começa vazio até o usuário escolher. Paciente some da seleção se o convênio mudar para um que não é o dele. Paciente pré-preenchido por link (alerta de guia negada) passou a usar `GET /pacientes/{id}` (novo hook `usePaciente`) para exibir o nome.
- [x] 5.2 `SolicitacaoEditarPage.tsx`: mesma troca no campo de médico, hidratando `medicoSelecionado` a partir de `solicitacao.medico` ao carregar.
- [x] 5.3 `SolicitacaoGuiaModal.tsx`: mesma troca no campo de médico dentro do modo de edição inline.

## 6. Validação

- [x] 6.1 Testes de API cobrindo paginação (`PacientesApiTest::test_pagina_pacientes_quando_page_informado`) e os dois endpoints de recentes (`PacientesApiTest`, `MedicosApiTest`, incluindo filtro por convênio nos recentes de paciente).
- [x] 6.2 `MedicosApiTest::test_usuario_com_apenas_medicos_view_pode_listar_mas_nao_gerenciar` — lista com `medicos.view` isolado, continua bloqueado em `POST`; `test_usuario_sem_permissao_nao_pode_gerenciar_medicos` passou a cobrir também o `GET` bloqueado sem nenhuma das duas permissões.
- [x] 6.3 `php artisan test` — suíte completa (344 testes) passando.
- [x] 6.4 `tsc -b`, `oxlint` e o guard do design system (`verificar-design-system.mjs`) passando; `npm run build` (Vite) também passa.
- [ ] 6.5 Teste manual no navegador — pendente (rodar app localmente e verificar visualmente busca/recentes/cadastro rápido).
- [x] 6.6 `openspec validate` — CLI do OpenSpec não disponível neste ambiente; spec revisada manualmente contra o formato usado em `guias-filtro-profissional-e-busca-paciente`.
