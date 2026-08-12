## 1. Estrutura do menu

- [x] 1.1 Criar `web/src/routes/navigation.ts` como fonte única dos grupos, submenus e descrições.
- [x] 1.2 Reescrever `ShellLayout` com grupos suspensos: rótulo navega, seta abre; fecha por Escape, clique fora e troca de rota.
- [x] 1.3 Remover o título do cabeçalho e renomear `Dashboard` para `Gestão de Convênios`.
- [x] 1.4 Apontar `/` e a rota curinga para a tela inicial.

## 2. Telas de grupo

- [x] 2.1 Criar `GrupoPage` com cartões explicativos e bloco de métricas vindo de `GET /dashboard`.
- [x] 2.2 Criar `CadastrosPage` (sem numeração) e `OperacaoConveniosPage` (numerada pela ordem de uso).
- [x] 2.3 Registrar `/cadastros` e `/operacao-convenios`.

## 3. Configurações por rota

- [x] 3.1 Converter as abas em rotas: `/configuracoes/emails`, `/configuracoes/ia`, `/configuracoes/unimed`.
- [x] 3.2 Criar `ConfiguracoesGeralPage` em `/configuracoes`, com aparência no topo e cartões abaixo.
- [x] 3.3 Remover a barra de abas, agora redundante com o submenu.

## 4. Métricas que faltavam

- [x] 4.1 Adicionar `dashboard.especialidades` e `dashboard.analiticos` ao `PermissionCatalog` e ao `RoleSeeder`.
- [x] 4.2 Adicionar os blocos correspondentes ao `DashboardController`.
- [x] 4.3 Migration de sync por tenant, derivando de permissão vizinha e sem `Role::findOrCreate` global (ver 2026_08_05_100000).

## 5. Contraste

- [x] 5.1 Fixar cores opacas em `select option/optgroup` por tema em `web/src/index.css`.
- [x] 5.2 Trocar `bg-white` por branco literal nos iframes do Manual e do preview de template.
- [x] 5.3 Dar fundo opaco ao painel dos submenus.

## 6. Ajustes do dashboard

- [x] 6.1 Filtrar o bloco `usuarios` da grade, mantendo-o na API para a tela de Cadastros.
- [x] 6.2 Remover o chapéu e trocar o texto de abertura, com `Manual` como link.

## 7. Correções de sobreposição e hover

- [x] 7.1 `relative z-50` no `<header>` e `z-0` no `<main>`: o `backdrop-blur` dos dois cria contexto de empilhamento e o `<main>`, por vir depois no DOM, cobria o submenu.
- [x] 7.2 Trocar a margem do painel por `padding` do invólucro e adicionar 180 ms de atraso no fechamento — a faixa vazia entre botão e painel disparava `mouseleave` e fechava o menu antes do clique.

## 8. Validação

- [x] 8.1 `tsc -b` e `oxlint` no `web/` (0 erros, 0 avisos).
- [x] 8.2 `php artisan test` — 167 passam; as 5 falhas de `AnaliticosApiTest`/`AntecipacoesApiTest` são anteriores a esta mudança, confirmado com `git stash`.
- [x] 8.3 Deploy e verificação de `GET /dashboard` no ar: 13 blocos, 12 exibidos.
- [ ] 8.4 Conferência visual dos dois temas nas telas novas.
