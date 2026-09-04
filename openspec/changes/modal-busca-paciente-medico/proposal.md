## Why

Os campos "Paciente" e "Médico" nos formulários de Solicitação e Guia usam o `<Select>` padrão do projeto (Headless UI Listbox), que carrega a lista inteira de pacientes/médicos do tenant sem busca nem paginação. Em clínicas com muitos pacientes e médicos cadastrados, esse dropdown fica impraticável — rolar centenas de nomes para achar um é lento e a listagem completa pesa no carregamento do formulário.

## What Changes

- Novos componentes `SelecionarPacienteModal` e `SelecionarMedicoModal`: um diálogo (Headless UI `Dialog`, mesmo padrão visual de `SolicitacaoGuiaModal`) com campo de busca e lista de resultados, substituindo o `<Select>` atual nesses dois campos.
- Aplicado em todos os formulários que hoje selecionam paciente/médico dentro do fluxo de Solicitações e Guias: `SolicitacoesPage` (nova solicitação e edição inline), `SolicitacaoEditarPage`, `SolicitacaoGuiaModal`. O dropdown de paciente do formulário "Nova guia" isolado, fora desse fluxo, não muda.
- Ao abrir o modal sem digitar nada, a lista inicial mostra os pacientes/médicos mais recentes usados em solicitações do tenant (recentes globais da clínica, não por usuário) — calculado a partir dos últimos `paciente_id`/`medico_id` distintos em `solicitacoes`, ordenados por `created_at` desc.
- Ao digitar 2+ caracteres (debounce ~500ms), a busca passa a consultar a API por nome, CPF ou carteirinha (paciente) e nome, CRM ou especialidade (médico) — os endpoints `GET /pacientes` e `GET /medicos` já aceitam o parâmetro `busca` cobrindo esses campos.
- Cada item da lista mostra, além do nome: paciente = CPF/carteirinha + data de nascimento; médico = especialidade + CRM — para diferenciar registros homônimos.
- `GET /pacientes`, `GET /medicos` e `GET /profissionais` passam a paginar (`page`/`per_page`), já que hoje retornam a tabela inteira em uma única resposta.
- Quando a busca não encontra ninguém, o modal mostra a opção "Cadastrar novo", reaproveitando os endpoints já existentes `POST /solicitacoes/pacientes-rapido` e `POST /solicitacoes/medicos-rapido`.
- Nova permissão `medicos.view` (somente leitura), concedida junto com `medicos.manage` aos papéis padrão `admin` e `funcionario`. `GET /medicos` passa a aceitar `medicos.view` OU `medicos.manage`, em vez de exigir só `medicos.manage`. Corrige uma fragilidade latente: hoje só existe uma permissão para ler e escrever médicos, diferente do padrão já usado em guias/antecipações/lançamentos/solicitações (que separam `.view`/`.viewOwn` de `.manage`); um papel customizado com acesso a Solicitações mas sem `medicos.manage` quebraria ao carregar o campo de médico.

## Capabilities

### New Capabilities

- `modal-busca-paciente-medico`: seleção de paciente e médico por busca em modal, com recentes, paginação e cadastro rápido, nos formulários de Solicitação e Guia.

### Modified Capabilities

- Nenhuma.

## Non-goals

- Não altera o dropdown de paciente do formulário "Nova guia" isolado (fora do fluxo de Solicitações/Guias já coberto aqui) — mantém o comportamento decidido em `guias-filtro-profissional-e-busca-paciente`.
- "Recentes" é global do tenant, não personalizado por usuário — não há registro de "últimos usados por mim" nesta fase.
- Não implementa infinite-scroll ou scroll virtualizado na lista de resultados — paginação simples (ex.: botão "carregar mais") é suficiente para o volume atual.
- Não introduz um componente de modal genérico único para os dois casos — `SelecionarPacienteModal` e `SelecionarMedicoModal` ficam como componentes específicos, cada um com seus campos de busca e de exibição.

## Impact

- API: `App\Http\Controllers\PacienteController`, `MedicoController`, `ProfissionalController`, `App\Http\Controllers\SolicitacaoController` (rota de recentes), `App\Support\PermissionCatalog`, `App\Support\RoleCatalog`, seeder de permissões/roles.
- Frontend: `web/src/components/ui/Select.tsx` (permanece para outros usos), novos `web/src/features/solicitacoes/SelecionarPacienteModal.tsx` e `SelecionarMedicoModal.tsx` (ficam na feature, não em `components/ui`, porque o cadastro rápido é específico do fluxo de Solicitações), `web/src/lib/queries/useReferenceData.ts` (novos hooks paginados/recentes, sem alterar os existentes), `web/src/features/solicitacoes/SolicitacoesPage.tsx`, `SolicitacaoEditarPage.tsx`, `SolicitacaoGuiaModal.tsx`.
