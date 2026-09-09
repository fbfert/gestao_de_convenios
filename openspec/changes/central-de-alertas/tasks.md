## 1. Modelo

- [ ] 1.1 Migration de `alertas`, com a coluna de deduplicação e o índice único que impede dois abertos da mesma chave.
- [ ] 1.2 Migration de `alerta_regras`.
- [ ] 1.3 Models `Alerta` e `AlertaRegra` com `BelongsToTenant` e `Auditable`.

## 2. Avaliador

- [ ] 2.1 Interface `AvaliadorDeAlerta` e o resolver que mapeia chave para classe.
- [ ] 2.2 Regra `senha.vencendo`.
- [ ] 2.3 Regra `guia.negada`.
- [ ] 2.4 Regra `automacao.falhas_em_serie`.
- [ ] 2.5 Regra `componente.fora`.
- [ ] 2.6 `AvaliarAlertasJob`, abrindo, atualizando e resolvendo, registrado no agendador a cada 15 minutos com `withoutOverlapping()`.
- [ ] 2.7 Seeder das regras padrão.

## 3. API

- [ ] 3.1 Permissões `alertas.view` e `alertas.manage` no `PermissionCatalog`.
- [ ] 3.2 `GET /alertas` com filtros, `POST /alertas/{alerta}/reconhecer` e `POST /alertas/{alerta}/silenciar`.
- [ ] 3.3 CRUD de `alerta_regras`.
- [ ] 3.4 Card de alertas na resposta do dashboard.

## 4. Web

- [ ] 4.1 `AlertasPage` com filtros.
- [ ] 4.2 `AlertasConfiguracoesPage`.
- [ ] 4.3 Rotas e entrada no menu.
- [ ] 4.4 Card no dashboard, só amarelo e vermelho, sem sumir quando vazio.
- [ ] 4.5 Remover o `GuiaAlertaNegacoes` — só depois de as ações existirem no alerta novo.

## 5. Testes

- [ ] 5.1 Rodar o avaliador duas vezes não duplica.
- [ ] 5.2 Condição que some resolve o alerta.
- [ ] 5.3 Alerta silenciado não reaparece antes do prazo.
- [ ] 5.4 Regra desativada não gera nada.
- [ ] 5.5 Limiar alterado muda o resultado.
- [ ] 5.6 Permissões barram quem não tem.

## 6. Validação

- [ ] 6.1 `openspec validate central-de-alertas --type change --strict`.
- [ ] 6.2 `php artisan test` e `npm run lint`.
