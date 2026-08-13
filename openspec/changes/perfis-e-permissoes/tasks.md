## Estratégia acordada (2026-08-13)

Cinco fases. Este change cobre as fases 1 e 2; auditoria (3 a 5) fica em change
próprio.

| Fase | Entrega | Onde |
|---|---|---|
| 1 | Permissões no payload, menu filtrado, telas no menu | aqui |
| 2 | CRUD de papéis com papéis de sistema protegidos | aqui |
| 3 | Trilha de auditoria (trait `Auditable`, censura de campos sensíveis) | change próprio |
| 4 | Tela de auditoria: filtros, paginação, antes/depois, CSV | change próprio |
| 5 | Retenção de 12 meses configurável, com expurgo no scheduler | change próprio |

Decisões: papéis customizáveis com os três padrão protegidos; menu esconde o que
o papel não pode; um papel por usuário; auditoria cobrindo configurações,
cadastros, operação e acessos; retenção padrão de 12 meses configurável.

## 1. Permissões no payload

- [x] 1.1 Incluir `permissions` na resposta do login, lidas do papel dentro do tenant do usuário.
- [x] 1.2 `GET /user` passa a devolver o mesmo formato do bloco `user` do login, em vez do model cru.
- [x] 1.3 Frontend recarrega papel e permissões na abertura do app, para mudança de papel valer sem novo login.
- [x] 1.4 `authStore` guarda `permissions` e o hook `usePode()` (em `lib/permissoes.ts`) responde pelas consultas. O campo segue opcional no tipo por causa de sessão gravada antes desta versão: `undefined` significa "ainda não sei" e não filtra nada, senão o menu apareceria vazio até o `GET /user` responder.

## 2. Menu por permissão

- [x] 2.1 Cada item de `navigation.ts` declara a permissão que o habilita.
- [x] 2.2 `ShellLayout` filtra itens e esconde grupo que ficou sem filhos visíveis.
- [x] 2.3 Trocar o fallback `role === 'admin'` de `ConfiguracoesPage` pelo `can()` real.
- [x] 2.4 Entradas novas em Configurações: `Perfis e Permissões` e `Logs de Auditoria`.

## 3. CRUD de papéis

- [x] 3.1 `POST /roles` com nome e papel de origem opcional, criando no tenant do usuário.
- [x] 3.2 `PATCH /roles/{role}` para renomear, recusando papel de sistema.
- [x] 3.3 `DELETE /roles/{role}`, recusando papel de sistema e papel com usuários vinculados.
- [x] 3.4 `RoleCatalog::nomes()` como fonte única dos papéis de sistema, sem duplicar a lista.

## 4. Guard-rails de administração

- [x] 4.1 Recusar alteração que deixe o tenant sem nenhum papel com `permissoes.manage`.
- [x] 4.2 Recusar que o usuário remova `permissoes.manage` do papel dele mesmo.
- [x] 4.3 Aplicar as duas regras também na exclusão de papel.

## 5. Tela de perfis e permissões

- [x] 5.1 Listagem dos papéis com contagem de usuários e de permissões.
- [x] 5.2 Criação e edição em tela própria, conforme a spec `crud-lista-formulario-separados`.
- [x] 5.3 Papel de sistema aparece identificado, sem ações de renomear e excluir.
- [x] 5.4 Mostrar rótulo legível da permissão, não só o nome técnico.

## 6. Validação

- [x] 6.1 Testes de API: payload de autenticação, CRUD de papéis, papéis de sistema, papel em uso e os dois guard-rails.
- [x] 6.2 `openspec validate perfis-e-permissoes --type change --no-interactive`.
- [x] 6.3 `tsc -b`, `oxlint`, `vite build` e `php artisan test` (201 testes, 978 asserções).
- [ ] 6.4 Rodar a suíte e2e do Playwright — o servidor não tem Node e PHP fora dos containers.
