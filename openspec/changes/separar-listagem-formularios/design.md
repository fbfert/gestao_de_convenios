## Context

O sistema já possui vários CRUDs onde a listagem e o formulário compartilham a mesma tela. Em alguns casos, o formulário aparece logo acima ou abaixo da tabela; em outros, o estado da página muda para edição sem sair da rota principal. Isso cria uma experiência inconsistente e amplia a complexidade da manutenção.

A mudança afeta a navegação web, a composição de telas e os fluxos de criação/edição em módulos como Pacientes, Solicitações, Guias, Lançamentos, Convênios, Profissionais, Médicos, Especialidades e Usuários.

## Goals / Non-Goals

**Goals:**
- Separar visual e funcionalmente a tela de listagem da tela de cadastro/edição.
- Garantir que `Novo` e `Inserir` levem a uma rota própria de formulário.
- Preservar a listagem como ponto de entrada principal de cada módulo.
- Manter a regra de negócio e as permissões existentes.

**Non-Goals:**
- Não alterar modelos de dados, tabelas ou regras de domínio.
- Não redesenhar os formulários além do necessário para a nova navegação.
- Não trocar a stack de rotas nem introduzir uma dependência nova de navegação.
- Não revisar o fluxo de detalhe/leitura que já existe e não depende de formulário inline.

## Decisions

- Usar rotas dedicadas para formulário em vez de formulário inline na listagem.
  - Rationale: a rota separada torna o fluxo explícito, melhora o botão Voltar e evita que a listagem fique “presa” ao estado do formulário.
  - Alternatives considered: modal, drawer ou query param. Foram descartados porque ainda mantêm a listagem acoplada à ação de criação/edição e tendem a piorar telas mais complexas.

- Reaproveitar componentes de formulário quando possível, mas em páginas próprias.
  - Rationale: reduz duplicação e mantém validação, submissão e mensagens consistentes.
  - Alternatives considered: duplicar o formulário em cada rota nova. Isso simplificaria a primeira entrega, mas aumentaria o custo de manutenção.

- Manter a página de listagem como o lugar de consulta e ação primária.
  - Rationale: o usuário encontra os registros, aplica filtros e escolhe a ação sem ruído visual.
  - Alternatives considered: abrir formulário na mesma tela com scroll/ancoragem. Foi descartado porque ainda mistura contexto de consulta e edição.

- Padronizar o padrão de navegação por módulo.
  - Rationale: reduz curva de aprendizado entre páginas diferentes.
  - Alternatives considered: migrar apenas alguns CRUDs. Isso deixaria comportamento inconsistente e não resolveria o problema relatado pelo cliente.

## Risks / Trade-offs

- [Risk] Parte do código atual pode depender de estado local da tela de listagem para preencher o formulário → Mitigation: extrair o estado para componentes de formulário dedicados e recuperar dados por rota.
- [Risk] A mudança pode quebrar links existentes de edição/criação → Mitigation: adicionar rotas explícitas e ajustar os botões principais antes de remover o fluxo antigo.
- [Risk] O volume de páginas afetadas é alto → Mitigation: implementar por módulos, validando cada página após a migração.
- [Risk] Algumas telas podem ter exceções funcionais, como páginas de detalhe com ações próprias → Mitigation: tratar somente CRUDs com listagem + botão de novo/inserir/editar, sem forçar os fluxos que já são naturalmente separados.

## Migration Plan

1. Identificar os CRUDs que ainda usam formulário inline na listagem.
2. Criar rotas dedicadas para criação e edição.
3. Reaproveitar os componentes de formulário, removendo a dependência de estado compartilhado com a listagem.
4. Atualizar os botões `Novo` e `Inserir` para apontarem para as novas rotas.
5. Validar página por página e ajustar testes de navegação/fluxo.
6. Se necessário, manter redirecionamentos temporários para não quebrar links antigos durante a migração.

## Open Questions

- Quais páginas entram na primeira onda da migração e quais podem ficar para a segunda?
- O fluxo de criação deve sempre abrir uma tela vazia ou alguns módulos precisam iniciar com dados pré-carregados a partir da listagem?
