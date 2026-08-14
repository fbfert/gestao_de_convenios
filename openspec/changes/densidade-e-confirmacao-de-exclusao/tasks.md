## 1. Contadores

- [x] 1.1 Componente `Indicadores`, com rótulo e valor lado a lado.
- [x] 1.2 Aplicado em Pacientes, Profissionais, Médicos, Especialidades, Usuários, Solicitações, Guias, Sessões, Conciliações e Templates de E-mail.
- [x] 1.3 Em Guias, o filtro de validade vencendo continua botão ao lado da linha: ele age, não só informa.

## 2. Textos

- [x] 2.1 Remover 9 cabeçalhos "Lista"/"Filtros e lista" com a descrição junto.
- [x] 2.2 Remover 18 descrições sob título de formulário e 19 sob título de tela.
- [x] 2.3 Preservar mensagens de carregamento e de estado vazio — a remoção automática levou três junto, e a compilação acusou.
- [x] 2.4 A garantia de privacidade da auditoria virou dica na lupa, em vez de parágrafo.

## 3. Exclusão

- [x] 3.1 Componente `ConfirmarExclusao`, com palavra digitada e cancelamento por Esc.
- [x] 3.2 Aplicado à exclusão de anexo da solicitação.
- [ ] 3.3 Avaliar o mesmo padrão em outras exclusões sem volta — excluir perfil ainda usa `window.confirm`.

## 4. Validação

- [x] 4.1 `openspec validate`, `tsc -b`, `oxlint` e `vite build`.
- [ ] 4.2 Conferir no navegador o alinhamento da linha de contadores em duas ou três telas.
