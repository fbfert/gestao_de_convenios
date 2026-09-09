## 1. API

- [ ] 1.1 Criar a migration de `saude_componentes`, com chave única por tenant.
- [ ] 1.2 Criar o model `SaudeComponente` com as traits `BelongsToTenant` e `Auditable`.
- [ ] 1.3 Criar o `SaudeService` com `registrarHeartbeat` e a derivação de estado a partir do carimbo.
- [ ] 1.4 Chamar `registrarHeartbeat` nos pontos que já existem: execução bem-sucedida do worker de automação, rodada do agendador e envio de e-mail.
- [ ] 1.5 Adicionar `GET /saude` no grupo autenticado, com leitura barata o suficiente para o polling de 30s do dashboard.
- [ ] 1.6 Preencher o array `componentes` do `GET /api/health` com chave e estado, sem identificar tenant.
- [ ] 1.7 Criar o seeder dos componentes padrão de tenant novo.
- [ ] 1.8 Cobrir em teste: heartbeat recente resulta em `healthy`; heartbeat de quatro vezes o intervalo resulta em `down`; componente sem heartbeat resulta em `down`; componente de outro tenant não aparece; `GET /api/health` não expõe tenant.

## 2. Web

- [ ] 2.1 Criar hooks e tipos para consumir `GET /saude`, no mesmo `refetchInterval` de 30s do dashboard.
- [ ] 2.2 Implementar o card de saúde com uma linha por componente.
- [ ] 2.3 Comunicar o estado por cor e também por texto ou ícone, com token do design system — sem hex e sem classe arbitrária.
- [ ] 2.4 Tratar os casos de borda: todos saudáveis vira resumo discreto; tenant sem componente omite o card; componente fora informa desde quando não responde, sem ação de reinício.

## 3. Validação

- [ ] 3.1 Executar `openspec validate saude-componentes --type change`.
- [ ] 3.2 Executar `php artisan test` e `npm run lint` (que inclui `ds:check`).
