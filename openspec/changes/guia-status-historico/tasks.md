## 1. Modelo

- [ ] 1.1 Migration de `guia_status_historico`, com índice em `(tenant_id, guia_id, ocorrido_em)` e em `(tenant_id, para, ocorrido_em)` — o segundo é o que o card do dashboard usa.
- [ ] 1.2 Migration das colunas `negada_em` e `aprovada_em` em `guias`.
- [ ] 1.3 Model `GuiaStatusHistorico` com `BelongsToTenant`.

## 2. Ponto único de escrita

- [ ] 2.1 `GuiaService::registrarTransicao`, gravando histórico, status e carimbo na mesma transação.
- [ ] 2.2 Observer de trava no model `Guia`, recusando `status` sujo fora do método.
- [ ] 2.3 Migrar os oito call sites: `GuiaService` (criar, finalizar, negar), `SolicitacaoService`, `GerarGuiaUnimedService`, `ConfirmarGuiaIncertaUnimedService`, `ConsultarStatusUnimedService` e `GuiaImportService`.
- [ ] 2.4 Ajustar seeders e fábricas que criam guia com status.

## 3. Reconstrução

- [ ] 3.1 Command `guias:backfill-status-historico --dry-run`, best-effort, com relato do que ficou sem histórico.

## 4. Testes

- [ ] 4.1 Transição grava histórico e carimbo.
- [ ] 4.2 Transição por robô grava sem usuário e com a origem certa.
- [ ] 4.3 Segunda negação acrescenta linha e atualiza o carimbo.
- [ ] 4.4 A trava reprova escrita de status por fora.
- [ ] 4.5 Salvar outros campos continua livre.

## 5. Validação

- [ ] 5.1 `openspec validate guia-status-historico --type change --strict`.
- [ ] 5.2 `php artisan test`.
