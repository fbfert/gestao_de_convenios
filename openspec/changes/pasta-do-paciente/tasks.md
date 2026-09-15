## 1. Tipo de documento

- [x] 1.1 Acrescentar `registro_sessoes` a `PacienteArquivo::TIPOS`, fora de
      `TIPOS_DA_SOLICITACAO` e de `TIPOS_POR_ITEM`, com `TIPOS_DA_SESSAO` documentando
      por quê.
- [x] 1.2 Acrescentar o tipo, o rótulo "Registro de Sessões" e `DOCUMENTOS_DA_SESSAO`
      a `web/src/lib/documentoTipos.ts`, incluindo-o em `TODOS_DOCUMENTOS`.

## 2. Gravação da folha de registro

- [x] 2.1 `LancamentoController::guardarRegistroDeSessoes()` grava o PDF em
      `pacientes/{id}/registro-sessoes`, com nome UUID, MIME lido do arquivo gravado e
      `metadata` apontando `guia_id` e `numero_guia`.
- [x] 2.2 Chamar o método depois de `confirmarTranscricao`, nunca antes.
- [x] 2.3 Testes em `LancamentosApiTest`: grava com PDF; não cria arquivo sem PDF.

## 3. Endpoint da pasta

- [x] 3.1 `PacientePastaController` invocável, devolvendo `paciente`, `solicitacoes`,
      `guias`, `sessoes`, `antecipacoes` e `arquivos`.
- [x] 3.2 Sessões por `whereHas('guia', …)` e antecipações por
      `whereHas('solicitacaoOrigem', …)` — nenhuma das duas tabelas tem `paciente_id`.
- [x] 3.3 Rota `GET /pacientes/{paciente}/pasta` sob `permission:dashboard.pacientes`.
- [x] 3.4 `PacientePastaApiTest`: as cinco listas, guias com sessões, registro de
      sessões, paciente de outro tenant e usuário sem permissão.

## 4. Tela

- [x] 4.1 `usePacientePasta.ts` tipado, uma requisição.
- [x] 4.2 `PacientePastaPage.tsx` com cabeçalho de dados cadastrais e as cinco seções.
- [x] 4.3 `Secao` sempre recolhida ao abrir, com `aria-expanded` e `aria-controls`.
- [x] 4.4 Exportar `GrupoDocumento` do drawer para reaproveitar upload e exclusão por tipo.
- [x] 4.5 Registrar `/pacientes/:id` em `AppRoutes.tsx`, depois de `/editar`.
- [x] 4.6 `PacientesPage` navega em vez de abrir o drawer.

## 5. Validação

- [x] 5.1 `php artisan test` — 612 passaram.
- [x] 5.2 `npm run build` e `npm run lint` (contrato do design system).
- [x] 5.3 Cada teste novo verificado falhando sem a sua correção.
