## 1. API

- [ ] 1.1 Serviço que monta o card em duas consultas agregadas, sem `count()` por número.
- [ ] 1.2 Seção nova na resposta de `GET /dashboard`, só para quem tem permissão de guias.
- [ ] 1.3 Filtros `pendente` e `senha_vencendo` no `GuiaController` e no `GuiaService`.
- [ ] 1.4 Ler `senha_alerta_dias` do tenant, sem prazo embutido no código.

## 2. Web

- [ ] 2.1 Card de Guias com três linhas clicáveis, levando à listagem filtrada.
- [ ] 2.2 Tirar o bloco de Guias da grade genérica do "Resumo por área".
- [ ] 2.3 Tokens do design system; `npm run lint` tem que passar.

## 3. Testes

- [ ] 3.1 Guia antiga negada hoje conta em "hoje".
- [ ] 3.2 Guia com alerta ocultado sai da contagem de negadas.
- [ ] 3.3 Guia histórica não entra em nenhuma linha.
- [ ] 3.4 Janela de senha vencendo segue a configuração do tenant.
- [ ] 3.5 Filtros novos devolvem o mesmo conjunto que o card promete.

## 4. Validação

- [ ] 4.1 `openspec validate dashboard-cards-por-linha --type change --strict`.
- [ ] 4.2 `php artisan test` e `npm run lint`.
