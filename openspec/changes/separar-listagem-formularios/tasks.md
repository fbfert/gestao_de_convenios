## 1. Base de navegação

- [x] 1.1 Mapear os CRUDs que ainda misturam listagem e formulário inline e definir a convenção de rotas dedicadas para criação.
- [x] 1.2 Criar/ajustar a estrutura de rotas da web para suportar telas separadas de listagem e criação.
- [x] 1.3 Garantir que a navegação de voltar retorne à listagem correta em cada módulo.

## 2. Migração dos CRUDs principais

- [x] 2.1 Separar a tela de Pacientes em listagem própria e formulário de criação próprio.
- [x] 2.2 Separar a tela de Solicitações em listagem própria e formulário de criação próprio.
- [x] 2.3 Separar a tela de Guias em listagem própria e formulário de criação próprio.
- [x] 2.4 Separar a tela de Lançamentos e seus fluxos de cadastro em telas dedicadas.

## 3. Migração dos cadastros de referência

- [x] 3.1 Separar as telas de Profissionais, Médicos e Especialidades em listagem própria e formulário de criação próprio.
- [x] 3.2 Separar a tela de Usuários, incluindo o fluxo de criação em rota dedicada.
- [x] 3.3 Separar a tela de Convênios para que a listagem não compartilhe a página com o formulário.

## 4. Revisão final

- [x] 4.1 Atualizar botões, links e redirecionamentos para apontarem às novas rotas de formulário.
- [x] 4.2 Ajustar ou criar testes de navegação para cada módulo migrado.
- [x] 4.3 Executar validação do OpenSpec e os testes/builds relevantes após a migração.

## 5. Pendência encontrada depois (2026-08-12)

A tarefa 3.2 cobriu apenas a **criação** de Usuários. A edição continuava
embutida na listagem, controlada por estado local, então a tela seguia com o
comportamento antigo em metade do fluxo.

- [x] 5.1 Rota `/usuarios/:id/editar`, no mesmo padrão já usado em Profissionais.
- [x] 5.2 Trocar o estado `isFormOpen` pela rota como fonte da decisão de renderizar.
- [x] 5.3 Hidratar o formulário quando a rota é aberta direto pela URL ou recarregada, casos em que o clique em Editar nunca acontece.
- [ ] 5.4 `GET /usuarios/{id}`: hoje a hidratação procura o usuário na página carregada da listagem, então um link direto para alguém fora da página atual abre o formulário vazio.

## 6. Mesma pendência em Convênios (2026-08-13)

A tarefa 3.3 também cobriu apenas a **criação**. A edição continuava embutida na
listagem, controlada pelo estado local `form`, contrariando o cenário "Abrir
formulário em rota própria" da spec `crud-lista-formulario-separados`, que fala
de criação **e** edição.

- [x] 6.1 Rota `/convenios/:id/editar`, no mesmo padrão já usado em Profissionais e Usuários.
- [x] 6.2 Trocar o estado `form: Convenio | null` pela rota como fonte da decisão de renderizar, com um único formulário — antes o mesmo formulário existia duplicado, um na tela de criação e outro na edição em linha.
- [x] 6.3 Hidratar o formulário quando a rota é aberta direto pela URL ou recarregada.
- [x] 6.4 Tirar a listagem da tela enquanto o formulário está aberto, tanto na criação quanto na edição.
- [x] 6.5 Teste de navegação cobrindo o fluxo de edição em tela própria.
- [ ] 6.6 `GET /convenios/{id}`: a hidratação lê a listagem, que devolve só convênios ativos. Abrir `/convenios/{id}/editar` de um convênio inativo mostra "não encontrado" em vez do formulário. Mesma raiz da 5.4.
