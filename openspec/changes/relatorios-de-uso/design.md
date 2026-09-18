## Context

O dado para relatório já está no banco, espalhado por quatro origens que não conversam: `guia_status_historico` (transições com data), `analitico_unimed_lotes`/`_linhas` e `movimentos_financeiros` (dinheiro), `automacao_execucoes` (robô), `audit_logs` (gente). O que falta é agregação por período e comparação.

Duas regras do projeto mandam no desenho e não são negociáveis aqui: todo model usa `BelongsToTenant` com escopo global, e regra de convênio é dado configurável, nunca constante em código.

## Goals / Non-Goals

**Goals:**
- Um contrato só para as quatro abas, para a UI ter um componente por tipo de visualização e não um por aba.
- Comparação com o período anterior em todo KPI, porque número solto não diz se melhorou.
- Permissão por aba, para o financeiro não vazar para quem só opera.
- Agregação em SQL, rodando igual em SQLite (teste) e MariaDB (produção).

**Non-Goals:**
- Pré-agregar em snapshot. Ver Non-Goals do proposal.
- Gráfico configurável pelo usuário. As séries são fixas e nomeadas pela spec.

## Decisions

- **Um contrato, quatro abas.** A resposta é sempre `{ periodo, comparacao, filtros_aplicados, kpis[], series[], tabelas[], gerado_em, cache }`. Racional: a alternativa — um formato por aba — multiplicaria por quatro os componentes de tela e faria cada aba nova exigir front novo. Com `key` estável em cada KPI, série e tabela, a UI renderiza por tipo (`linha|area|barras|funil|pizza`) e a aba vira configuração.

- **Data de transição, nunca `created_at`.** Taxa de aprovação, negação, tempo em status e "guias aprovadas no período" saem de `guia_status_historico` pela data em que a transição aconteceu. Racional: uma guia criada em agosto e negada em setembro é negação de **setembro**; contar pela criação joga o número no mês errado e faz a série mentir justamente onde ela deveria informar. `DashboardGuiasCardService` já faz assim, e o cálculo é reaproveitado.

- **`withoutGlobalScope` só no serviço de relatório, e só para super admin.** Usuário comum nunca escapa do escopo global — nem passando `tenant_id`, que devolve 403. Super admin com `tenant_id=<id>` força aquele tenant; com `tenant_id=todos`, os services derrubam o escopo. Racional: é a única forma de ver o agregado das clínicas, e concentrar isso num lugar torna o teste de isolamento possível de escrever e óbvio de revisar. Espalhar `withoutGlobalScope` pelo código seria um vazamento esperando acontecer.

- **Dinheiro em centavos inteiros na API.** A formatação é da UI. Racional: `decimal:2` do Eloquent volta string, somar string em PHP e depois em JS é como se perde centavo; inteiro não tem esse problema e o front já formata BRL em outras telas.

- **"Valor apresentado" é derivado, não é coluna.** Vem de `analitico_unimed_lotes.total_pago + total_glosado`. Racional: `ConciliacaoFinanceira` não separa apresentado de pago — quem procurar o campo não acha, e inventar um valor plausível a partir da tabela de valores misturaria o que a clínica cobrou com o que a operadora reconheceu.

- **Truncar data por helper, não por SQL cru.** Uma helper resolve dia/semana/mês para o driver em uso. Racional: os testes rodam em SQLite (`strftime`) e a produção em MariaDB (`DATE_FORMAT`/`YEARWEEK`); escrever o `selectRaw` direto faz a suíte passar e a produção quebrar — ou o contrário, que é pior porque ninguém vê.

- **Cache de 5 minutos, com a chave carregando os filtros.** `relatorios:{tenant|todos}:{aba}:{sha1 dos filtros}`, e a resposta diz `cache: true` quando veio de lá. Racional: relatório é consulta cara e repetida (trocar de aba refaz tudo); 5 min é curto o bastante para ninguém tomar decisão com número velho e longo o bastante para o uso normal não bater no banco a cada clique. Expor `cache` evita a dúvida de "por que o número não mudou".

- **Granularidade automática, com override.** Até 31 dias: dia; até 120: semana; acima: mês. Teto de 366 dias. Racional: um ano em granularidade diária são 365 pontos num gráfico de 600px — ilegível e caro. O teto existe para o cache não guardar resposta gigante e para a query ter limite conhecido.

- **Saúde ganha tabela de evento, e não coluna nova.** `saude_componente_eventos` grava cada MUDANÇA de estado (ok→erro, erro→ok), não cada heartbeat. Racional: heartbeat é de minuto em minuto, e gravar todos encheria a tabela com milhões de linhas para responder uma pergunta sobre transições. Gravar só a mudança dá a mesma resposta com três ordens de grandeza menos linha. Consequência assumida: o relatório só enxerga do deploy em diante, e a aba diz isso — mostrar zero seria afirmar "sempre no ar", que é diferente de "não sei".

- **Recharts pintado por token, sem hex.** `web/src/lib/graficos.ts` lê as cores de CSS vars via `getComputedStyle`. Racional: é o que faz o `ds:check` passar e o que mantém o tema de alto contraste funcionando — uma paleta literal quebraria o segundo tema em silêncio, porque gráfico não tem teste de contraste.

- **Filtros na URL.** Estado em `searchParams`, como as listagens já fazem com `useListaNaUrl`. Racional: relatório é feito para ser mandado para alguém ("olha esse número"), e sem URL compartilhável isso vira captura de tela.

## Risks

- **Isolamento de tenant é o risco alto desta change.** É a primeira vez que o sistema derruba o escopo global de propósito. Mitigação: `withoutGlobalScope` confinado aos services de relatório, e teste obrigatório de que usuário comum não vê número de outro tenant nem passando `tenant_id`.
- **A tela nasce vazia.** Uma clínica, menos de um mês de dados. Mitigação: `DemoDataSeeder` com ~90 dias determinísticos — sem isso não há como avaliar o desenho nem escrever e2e com número conhecido.
- **Dois bancos.** Ver a decisão da helper de truncamento; é o defeito mais fácil de deixar passar aqui.
