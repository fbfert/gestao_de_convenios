## 1. Preservação (feita antes de qualquer código)

- [x] 1.1 Conferir o conteúdo de `manuais` em produção.
- [x] 1.2 Exportar o texto da clínica para `resources/manual/*.html`.
- [x] 1.3 Manter as sementes originais do repositório versionadas ao lado.

## 2. Manual

- [ ] 2.1 `ManualController::show` lendo do arquivo, sem tabela.
- [ ] 2.2 Remover `update`, `UpdateManualRequest`, a rota e a permissão `manual.manage`.
- [ ] 2.3 Migration que dropa `manuais`, com `down()` que a recria.
- [ ] 2.4 Tirar a edição de `ManualPage.tsx`.
- [ ] 2.5 ADR do trade-off em `docs/decisoes-arquitetura.md`.

## 3. Novidades

- [ ] 3.1 Serviço que lê `resources/novidades/*.md` com frontmatter, cacheado.
- [ ] 3.2 Migration de `novidade_leituras`.
- [ ] 3.3 `GET /novidades` e `POST /novidades/{slug}/lida`.
- [ ] 3.4 Tela `/novidades` e card no dashboard com a contagem de não lidas.
- [ ] 3.5 Primeira novidade escrita, anunciando a central de alertas.

## 4. Testes

- [ ] 4.1 Manual vem do arquivo.
- [ ] 4.2 Não há endpoint de edição.
- [ ] 4.3 Novidades ordenadas e limitadas.
- [ ] 4.4 Arquivo inválido é ignorado sem quebrar.
- [ ] 4.5 Leitura é por usuário e não duplica.

## 5. Validação

- [ ] 5.1 `openspec validate manual-produto-e-novidades --type change --strict`.
- [ ] 5.2 `php artisan test` e `npm run lint`.
