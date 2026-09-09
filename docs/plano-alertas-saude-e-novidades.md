# Plano de construção — Saúde, Alertas, Notificações e Novidades

Gestão de Convênios (`fbfert/gestao_de_convenios`) — set/2026

---

## 1. Princípios que valem para todas as fases

1. **Spec antes de código.** O `AGENTS.md` exige: nenhuma implementação sem spec criada ou atualizada, e `openspec validate` antes de considerar concluído. Cada fase abaixo é **um change separado** — não junte.
2. **Regra é dado, nunca código.** Limiares de alerta, intervalos de heartbeat e prazos vão em tabela por tenant, jamais hardcoded em Service/Controller.
3. **Enxuto primeiro.** Cada fase entrega algo usável sozinha. Se a fase 4 nunca sair, as fases 0-3 continuam valendo.
4. **Nada de tela nova quando já existe uma.** `email_templates` e `senha_alerta_dias` já existem e estão órfãos — são consumidos, não recriados.
5. **Status em inglês no banco/API**, tradução só na UI (regra do `openspec/config.yaml`).
6. **`npm run ds:check` reprova hex e valor mágico.** Toda cor nova passa por token do design system antes de existir no componente.

### Estado do sistema que motiva a ordem

- 1 tenant em produção (NeuroKids), menos de 1 mês de histórico → gráficos de evolução não têm dado, ficam por último.
- A automação Unimed já roda em produção e **hoje a falha é descoberta por reclamação do cliente** → observabilidade é a fase 0.
- `email_templates` (CRUD completo) e `senha_alerta_dias` (config) existem e **nenhum código os consome**.
- `GuiaAlertaNegacoes` já é um banner de alerta no dashboard → será absorvido, não duplicado.

---

## 2. Mapa das fases

| Fase | Change do openspec | Entrega | Depende de |
|---|---|---|---|
| 0 | *(fora do openspec — infra)* | `/health` público, restart automático do worker, monitor externo | — |
| 1 | `saude-componentes` | Heartbeat genérico + card de saúde no dashboard | 0 |
| 2 | `guia-status-historico` | Histórico de transições + `negada_em`/`aprovada_em` | — |
| 3 | `dashboard-cards-por-linha` | Card de Guias com 3 linhas clicáveis | 2 |
| 4 | `central-de-alertas` | Tabela, avaliador, 4 regras, tela `/alertas`, card | 1, 2 |
| 5 | `notificacoes-de-alertas` | Destinatários (tenant + global), digest diário, imediato | 4 |
| 6 | `manual-produto-e-novidades` | Manual read-only + Novidades | — |
| 7 | *adiada* | Métricas diárias e gráficos | 2 + 3-4 meses de dado |

A fase 6 é independente de tudo — encaixe num intervalo, não bloqueie nada por causa dela.

---

# FASE 0 — Observabilidade externa

**Por quê primeiro:** meia tarde de trabalho, maior redução de risco de todo o plano. Sem isso, worker caído às 2h da manhã só aparece quando a clínica reclama.

**Não vai pro openspec** — é infraestrutura de deploy, não requisito de produto. Registre em `docs/decisoes-arquitetura.md` como ADR.

### P0.1 — Endpoint de saúde

```
Contexto: repo gestao_de_convenios. API em api/ (Laravel 11), front em web/.
Leia AGENTS.md, docs/decisoes-arquitetura.md e api/routes/api.php antes de mexer.

Tarefa: criar um endpoint GET /health PÚBLICO (fora do grupo auth:sanctum), em
api/routes/api.php, servido por um HealthController novo.

Ele retorna JSON com:
- db: "ok" | "erro"  (um SELECT 1)
- fila: "ok" | "erro" (a conexão de queue responde)
- scheduler_ultima_rodada: ISO8601 ou null
- componentes: [] (array vazio por enquanto — a fase 1 preenche)

Regras rígidas:
- Nenhum dado sensível: sem nome de tenant, sem e-mail, sem contagem de pacientes.
  Este endpoint é público e vai ser lido por um monitor externo.
- HTTP 200 quando tudo ok; HTTP 503 quando qualquer item estiver "erro" ou quando
  scheduler_ultima_rodada for mais antigo que 10 minutos. O monitor externo decide
  pelo status HTTP, não pelo corpo.
- Sem auth, sem middleware de tenant, sem consulta pesada. O endpoint é batido a
  cada 2 minutos: nada de count() em tabela grande.

Para o scheduler_ultima_rodada: registre um carimbo a cada rodada do scheduler.
Use cache()->forever('scheduler.ultima_rodada', now()) num Schedule::call() de um
minuto em api/routes/console.php, e leia esse valor no controller. Se o driver de
cache do projeto não persistir entre processos, use uma linha em configuracoes ou
um arquivo em storage — decida lendo config/cache.php e justifique num comentário.

Escreva teste de feature: 200 com tudo saudável, 503 com scheduler velho.
Rode os testes e me diga o que passou.
```

**Pronto quando:** `curl https://.../api/health` responde 200, e 503 se você parar o cron.

### P0.2 — Reinício automático do worker

```
Contexto: repo gestao_de_convenios. O worker da automação Unimed está em worker-unimed/.
Leia worker-unimed/ e o docker-compose.yml (ou o compose de deploy em deploy/).

Tarefa: garantir que o worker se recupere sozinho de queda, sem intervenção humana.

1. Adicione restart: unless-stopped ao serviço do worker no compose de produção.
2. Adicione um HEALTHCHECK (no compose ou no Dockerfile) que verifique se o processo
   está de fato respondendo — não apenas se o container está de pé. Se o worker não
   expõe nada HTTP, use um comando que valide o processo real.
3. Documente em docs/decisoes-arquitetura.md, como ADR novo, a decisão:
   "o reinício do worker é responsabilidade do supervisor de container, não de um
   botão na interface" — e o motivo: um botão de restart na tela não funciona
   justamente quando o worker está morto, e expor execução remota de comando a
   usuário de clínica é risco desnecessário.

Não crie endpoint de restart. Não crie botão. Não mexa em código do app.
```

### P0.3 — Monitor externo (manual, sem prompt)

Suba **Uptime Kuma** em Docker, **numa máquina que não seja a VPS do gescon** — essa é a regra que faz a coisa funcionar; monitor na mesma máquina morre junto com o que ele monitora.

- Monitor HTTP em `https://.../api/health`, intervalo 2 min, aceita só 200.
- Notificação para o seu WhatsApp (você já tem esteira) e para `suporte@xiax.com.br`.
- Segundo monitor opcional: "push" (dead man's switch) — o scheduler faz `curl` numa URL do Kuma ao fim de cada rodada; se parar de chegar, alarme. Cobre o caso "a API responde mas o cron morreu".

Custo: ~30 minutos. É a única camada que sobrevive à queda da VPS inteira.

---

# FASE 1 — Saúde de componentes

Change do openspec: **`saude-componentes`**

**Decisão de arquitetura:** saúde ≠ alerta. Alerta é acumulado ("10 negadas pendentes"); saúde é estado agora ("worker respondeu há 2 min"). São dois cards e dois modelos.

**Decisão de extensibilidade:** a automação não vai ser só Unimed. Nada de `worker_unimed_status`. Componente é linha de tabela; conector novo não toca o dashboard.

### P1.1 — Proposta no openspec

```
Contexto: repo gestao_de_convenios. Leia AGENTS.md, openspec/config.yaml, e um change
já existente (ex.: openspec/changes/dashboard-home-refresh/) para copiar o formato.

Tarefa: criar o change "saude-componentes" em openspec/changes/saude-componentes/
com proposal.md, design.md, tasks.md e specs/.

O que ele especifica:

Uma tabela saude_componentes registra cada peça do sistema que pode estar viva ou
morta: worker de automação (um por convênio automatizado, não só Unimed), scheduler,
fila, e envio de e-mail (SMTP). Campos:
  chave (único por tenant), nome, tipo (worker|scheduler|fila|smtp|conector),
  convenio_id (nullable), ultimo_heartbeat_em, ultimo_status, ultima_mensagem,
  intervalo_esperado_segundos, ativo

Cada componente EMPURRA um heartbeat quando funciona. O sistema deriva o estado
comparando now() - ultimo_heartbeat_em com intervalo_esperado_segundos:
  - dentro do intervalo            -> saudável
  - até 3x o intervalo             -> atenção
  - acima de 3x, ou nunca bateu    -> fora

Requisitos que a spec precisa cobrir:
- Registrar componente novo é inserir linha, sem alterar código do dashboard.
- O estado é derivado do heartbeat, nunca escrito à mão.
- GET /saude retorna os componentes do tenant e o estado derivado de cada um.
- O dashboard mostra um card de saúde com uma linha por componente.
- Quando um componente está fora, a interface informa que o suporte foi notificado
  automaticamente e NÃO oferece botão de reiniciar. Justifique isso no design.md
  com o argumento do ADR da fase 0.

Não implemente nada. Só o change. Rode openspec validate ao final e me mostre a saída.
```

### P1.2 — Implementação backend

```
Contexto: repo gestao_de_convenios. Leia AGENTS.md e a spec aprovada em
openspec/changes/saude-componentes/. A spec é a fonte de verdade — se o código
existente conflitar com ela, registre o conflito antes de alterar.

Tarefa: implementar o backend da fase.

1. Migration da tabela saude_componentes conforme a spec.
2. Model SaudeComponente usando os traits BelongsToTenant e Auditable, como os
   outros models de api/app/Models/.
3. Um service SaudeService com:
   - registrarHeartbeat(string $chave, string $status = 'ok', ?string $mensagem = null)
   - estadoDe(SaudeComponente $c): 'saudavel' | 'atencao' | 'fora'
4. Chamar registrarHeartbeat nos pontos que já existem:
   - no fim de cada execução bem-sucedida do worker Unimed (veja
     api/app/Jobs/ExecutarAutomacaoUnimedJob.php e o worker-unimed/)
   - a cada rodada do scheduler (api/routes/console.php)
   - após cada envio de e-mail bem-sucedido
5. GET /saude no grupo autenticado de api/routes/api.php, retornando os componentes
   do tenant com o estado derivado. Query barata: é lida pelo dashboard, que faz
   polling de 30s.
6. Alimentar o array "componentes" do GET /health da fase 0 com os mesmos dados,
   mas SEM identificar tenant — só chave e estado.
7. Seeder que cria os componentes padrão (scheduler, fila, smtp) para tenant novo.

Testes de feature: heartbeat recente = saudável; heartbeat de 4x o intervalo = fora;
componente sem heartbeat nenhum = fora.

Ao final, liste as specs lidas e os comandos de validação executados.
```

### P1.3 — Card de saúde no dashboard

```
Contexto: repo gestao_de_convenios, front em web/. Leia design-system-xiax-agenda.md
(especialmente a §11, o contrato de design) e web/src/features/dashboard/DashboardPage.tsx.

Tarefa: criar o card de saúde do sistema no dashboard, consumindo GET /saude.

Formato: um card, uma linha por componente:
  [•] Automação Unimed    respondeu há 2 min
  [•] Agendador           respondeu há 40 s
  [•] Envio de e-mail     sem resposta há 3 h

Regras de interface:
- Bolinha de estado com token do design system. NUNCA hex ou classe arbitrária:
  npm run ds:check reprova o build. Se faltar token para o estado, crie o token
  no design system primeiro e registre no documento.
- Cor não pode ser o único sinal — o tema de alto contraste existe por requisito
  de acessibilidade real (ADR-23). Use também texto e/ou ícone.
- Quando um componente está fora: mostrar "O suporte foi notificado automaticamente"
  com o horário. Nada de botão "Reiniciar Worker".
- Se todos estiverem saudáveis, o card fica discreto (uma linha resumo), não some.
  Some só se o tenant não tiver componente nenhum.
- TanStack Query como no resto do app, com o mesmo refetchInterval de 30s do dashboard.

Rode npm run lint (que inclui ds:check) e me mostre a saída.
```

---

# FASE 2 — Histórico de status das guias

Change do openspec: **`guia-status-historico`**

**Decisão:** histórico é a verdade; `negada_em`/`aprovada_em` são cache desnormalizado do último evento. Não é redundância — o card lê a coluna (barato, o dashboard faz polling), o gráfico lê o histórico.

**O risco que mata a feature:** hoje quem muda status é o `GuiaService`, o worker Unimed **e** o import. Se cada um fizer `->update(['status' => ...])` solto, o histórico nasce furado e você descobre em três meses. Ponto único de escrita, com teste que trava.

### P2.1 — Proposta no openspec

```
Contexto: repo gestao_de_convenios. Leia AGENTS.md, docs/schema.md, e os pontos que
hoje alteram Guia::status (procure por 'status' em api/app/Services/GuiaService.php,
api/app/Jobs/, api/app/Services/Automation/ e nos imports).

Tarefa: criar o change "guia-status-historico" em openspec/changes/.

O que ele especifica:

1. Tabela guia_status_historico: tenant_id, guia_id, de (nullable), para,
   ocorrido_em, user_id (nullable — null quando é robô), origem
   (manual|automacao|importacao|migracao), motivo (nullable).
2. Colunas negada_em e aprovada_em em guias — cache do último evento de cada tipo,
   escrito pelo mesmo ponto que grava o histórico.
3. TODA transição de status passa por um único método registrarTransicao() no
   GuiaService. Nenhum outro lugar do código pode escrever guias.status.
4. Um teste automatizado reprova qualquer escrita de status fora desse método.
   Decida no design.md como fazer isso de forma confiável (evento saving do model
   com verificação de origem, ou varredura estática do código) e justifique a escolha.

Contexto que muda o escopo: hoje existe apenas 1 tenant em produção com menos de
1 mês de histórico. Portanto:
- O backfill a partir de audit_logs é BEST-EFFORT, com origem='migracao'. Não gaste
  mais de uma hora nisso e não bloqueie a entrega se sair incompleto.
- Não especifique nada de gráfico ou métrica agregada aqui. Fica para outro change,
  quando houver dado.

Escopo restrito a GUIAS. O mesmo padrão vale depois para Solicitação, Antecipação e
Conciliação, mas não especifique as outras agora — registre como não-objetivo.
NÃO use tabela polimórfica única (entidade/entidade_id): mata FK e índice.

Rode openspec validate e me mostre a saída.
```

### P2.2 — Implementação

```
Contexto: repo gestao_de_convenios. Leia AGENTS.md e a spec aprovada em
openspec/changes/guia-status-historico/.

Tarefa: implementar.

1. Migrations: tabela guia_status_historico (com índice em (tenant_id, guia_id,
   ocorrido_em) e em (tenant_id, para, ocorrido_em) — o segundo é o que o card do
   dashboard vai usar) e colunas negada_em / aprovada_em em guias.
2. Model GuiaStatusHistorico com BelongsToTenant.
3. GuiaService::registrarTransicao(Guia $guia, string $para, array $contexto = []):
   grava o histórico, atualiza guias.status e o carimbo correspondente
   (negada_em/aprovada_em), tudo numa transação.
4. Migrar TODOS os pontos que hoje escrevem guias.status para usar esse método —
   inclusive os que vêm do worker de automação e dos importadores. Liste no resumo
   final quais arquivos foram alterados.
5. A trava definida na spec, com o teste que a comprova (o teste deve FALHAR se
   alguém adicionar um update de status solto).
6. Command artisan guias:backfill-status-historico --dry-run, lendo audit_logs,
   com origem='migracao'. Best-effort: registre no output quantas guias ficaram
   sem histórico e siga.

Testes: transição grava histórico e carimbo; transição pelo worker grava user_id
null e origem='automacao'; segunda negação não sobrescreve a primeira no histórico
(mas atualiza negada_em).

Ao final, liste as specs lidas e os comandos executados.
```

**Pronto quando:** `php artisan guias:backfill-status-historico --dry-run` roda e o teste da trava falha se você inserir um `->update(['status' => ...])` solto de propósito.

---

# FASE 3 — Card de Guias com três linhas

Change do openspec: **`dashboard-cards-por-linha`**

Substitui o bloco solto de Guias do "Resumo por área" por um card denso. O mesmo padrão serve depois para Conciliação e Solicitações — mas prove em Guias antes.

### P3.1 — Proposta + implementação (change pequeno, pode ir junto)

```
Contexto: repo gestao_de_convenios. Leia AGENTS.md, a spec de
openspec/changes/dashboard-home/, api/app/Http/Controllers/DashboardController.php,
web/src/features/dashboard/DashboardPage.tsx e
web/src/features/guias/GuiaAlertaNegacoes.tsx.
Depende do change guia-status-historico já implementado.

Tarefa: criar o change "dashboard-cards-por-linha" e implementá-lo.

O card de Guias vira um card com três linhas, cada uma clicável e levando à listagem
JÁ FILTRADA (hoje o bloco leva a /guias cru, e o operador precisa filtrar de novo):

  Guias
    Negadas         10 pendentes   5 hoje · 7 na semana   -> /guias?status=denied&pendente=1
    Em análise      14             6 hoje · 12 na semana  -> /guias?status=under_review
    Senha vencendo   3             vence 2 em 48h         -> /guias?senha_vencendo=1

Semântica dos números — isto é requisito, não detalhe:
- O número grande é O QUE AINDA EXIGE AÇÃO. Em "Negadas", são as pendentes: guias
  com alerta_negacao_ocultado_em NULL. As ocultadas não entram.
- "hoje" e "na semana" são contados pela DATA DA TRANSIÇÃO DE STATUS
  (guias.negada_em / guia_status_historico), NUNCA por created_at da guia. Uma guia
  criada semana passada e negada hoje conta em "hoje".
- "Senha vencendo" usa configuracoes_globais.senha_alerta_dias — a configuração já
  existe e hoje não é lida por nenhum código. Este é o primeiro consumidor dela.

Performance — requisito rígido:
- O dashboard faz polling de 30s e já dispara 13 count(). NÃO adicione um count()
  por número. Use uma query agregada com GROUP BY status + faixa de data, ou duas
  queries no total para o card inteiro. Meça e comente o resultado.
- Aproveite para parar de enviar o bloco "usuarios" da API: o front já o descarta
  em DashboardPage.tsx. Confirme antes que nenhuma outra tela o consome.

Interface:
- Tokens do design system, npm run ds:check tem que passar.
- O banner GuiaAlertaNegacoes CONTINUA como está nesta fase. Ele só é absorvido na
  fase da central de alertas — não remova agora, senão o operador perde as ações de
  "ocultar" e "nova solicitação" sem ter o substituto pronto.

Adicione filtros correspondentes no GuiaController (status=denied&pendente=1 e
senha_vencendo=1) se ainda não existirem.

Rode openspec validate, php artisan test e npm run lint.
```

---

# FASE 4 — Central de alertas

Change do openspec: **`central-de-alertas`**

**Decisões travadas:**
- **Materializado**, não derivado: tabela + job avaliador. Alerta que não notifica é relatório, e você já tem scheduler rodando.
- **Dedupe e fechamento automático são obrigatórios.** Sem os dois, a central enche de lixo em um mês e vira ignorada.
- **Verde não vai pro card do dashboard.** Se verde significa "tudo ok", o card enche de linhas irrelevantes e as vermelhas somem. No card: só amarelo e vermelho; ausência de alerta é o estado verde ("Nenhum alerta pendente"). Verde/informativo existe no banco e aparece só na central completa, atrás de filtro.
- **Lugar:** menu próprio `/alertas` (senha vencendo e glosa são operação, não automação). A **configuração das regras** vai em Automações → Configurações, onde `senha_alerta_dias` já mora.

### P4.1 — Proposta no openspec

```
Contexto: repo gestao_de_convenios. Leia AGENTS.md, openspec/config.yaml,
api/app/Models/ConfiguracaoGlobal.php, api/routes/console.php,
web/src/routes/navigation.ts e web/src/features/guias/GuiaAlertaNegacoes.tsx.

Tarefa: criar o change "central-de-alertas" em openspec/changes/.

MODELO
Tabela alertas:
  tenant_id, chave, nivel (verde|amarelo|vermelho), titulo, descricao,
  entidade (nullable), entidade_id (nullable), dados (json),
  aberto_em, resolvido_em (nullable), reconhecido_por, reconhecido_em,
  silenciado_ate (nullable)
Índice único parcial: (tenant_id, chave, entidade, entidade_id) enquanto
resolvido_em IS NULL. É o que impede o avaliador de criar duplicata a cada rodada.

Tabela alerta_regras (limiares são DADO, nunca código — regra de ouro do projeto):
  tenant_id, chave, ativo, nivel_base, limiar_amarelo, limiar_vermelho,
  critica (bool — se dispara notificação imediata na fase seguinte)

COMPORTAMENTO
- Um job avaliador roda no scheduler e, para cada regra ativa, abre alertas novos,
  atualiza os existentes e RESOLVE automaticamente os que não satisfazem mais a
  condição. O fechamento automático é requisito, não otimização: sem ele a central
  vira lixo e o time para de olhar.
- Alerta pode ser reconhecido (alguém viu) e silenciado até uma data.

REGRAS DA PRIMEIRA ENTREGA — apenas estas quatro:
1. senha.vencendo   — validade_senha dentro de senha_alerta_dias. Amarelo; vermelho
                      a <= 2 dias ou vencida com sessões não lançadas.
2. guia.negada      — guias negadas com alerta_negacao_ocultado_em NULL. Vermelho.
                      ABSORVE o componente GuiaAlertaNegacoes, que é removido nesta
                      fase. As ações que ele oferece hoje ("ocultar" e "nova
                      solicitação") precisam existir no alerta — se sumirem, a
                      entrega é uma regressão.
3. automacao.falhas_em_serie — N falhas consecutivas da mesma operação em H horas.
                      Vermelho. Use automacao_execucoes.
4. componente.fora  — componente de saude_componentes sem heartbeat além de 3x o
                      intervalo. Vermelho. Depende do change saude-componentes.

Especifique como NÃO-OBJETIVO desta entrega (mas registre no proposal.md como
backlog, para não se perder): guia.parada, guia.a_definir, guia.saldo_baixo,
credencial.rda_invalida, automacao.uncertain_pendente, automacao.desligada,
sync.pendencias_acumuladas, sync.falhando, analitico.nao_conciliado,
conciliacao.glosa_alta, lancamento.fora_da_senha, antecipacao.aberta_vencida,
paciente.duplicados, convenio.sem_valor, email.smtp_falhando.

INTERFACE
- Tela /alertas: lista com filtro por nível, chave e situação (aberto/resolvido/
  silenciado). Entrada própria no menu (web/src/routes/navigation.ts), não dentro
  de Automações.
- Tela /alertas/configuracoes: liga/desliga de regra, limiares, nível, marcar como
  crítica. Acessível também por Automações → Configurações.
- Card no dashboard: os 5 alertas abertos mais recentes + "Ver todos". SOMENTE
  amarelo e vermelho. Quando não houver nenhum, o card mostra "Nenhum alerta
  pendente" em vez de sumir.
- Permissões novas no PermissionCatalog (api/app/Support/PermissionCatalog.php),
  com rótulo legível: alertas.view e alertas.manage.

Rode openspec validate e me mostre a saída.
```

### P4.2 — Implementação: modelo e avaliador

```
Contexto: repo gestao_de_convenios. Leia AGENTS.md e a spec aprovada em
openspec/changes/central-de-alertas/.

Tarefa: implementar SÓ o backend do motor. Sem tela ainda.

1. Migrations de alertas e alerta_regras, com o índice único parcial descrito na
   spec. Atenção: se o alvo de produção for MariaDB/MySQL, índice único parcial não
   existe — resolva com coluna gerada ou com uma coluna 'ativo_dedupe' que só é
   preenchida enquanto o alerta está aberto. Escolha, implemente e comente o porquê.
2. Models Alerta e AlertaRegra com BelongsToTenant e Auditable.
3. Interface AvaliadorDeAlerta com avaliar(int $tenantId): array, e uma classe por
   regra em api/app/Services/Alertas/Regras/. Registro das regras num resolver, para
   que regra nova seja uma classe + uma linha de seed, sem tocar no job.
4. AvaliarAlertasJob: roda todas as regras ativas do tenant, abre/atualiza/RESOLVE.
   Registre no scheduler (api/routes/console.php) a cada 15 minutos, com
   withoutOverlapping(), seguindo o padrão dos jobs que já estão lá.
5. As quatro regras da spec.
6. Seeder das alerta_regras padrão para tenant novo.
7. Endpoints: GET /alertas (com filtros), POST /alertas/{alerta}/reconhecer,
   POST /alertas/{alerta}/silenciar, e o CRUD de alerta_regras — todos com as
   permissões novas.

Testes (o mais importante do change):
- rodar o avaliador duas vezes seguidas NÃO cria alerta duplicado;
- quando a condição deixa de valer, o alerta é resolvido automaticamente;
- alerta silenciado não reabre antes de silenciado_ate;
- regra desativada não gera nada.

Ao final, liste as specs lidas e os comandos executados.
```

### P4.3 — Implementação: telas

```
Contexto: repo gestao_de_convenios, front em web/. Leia design-system-xiax-agenda.md,
web/src/routes/navigation.ts, web/src/routes/AppRoutes.tsx,
web/src/features/dashboard/DashboardPage.tsx e
web/src/features/guias/GuiaAlertaNegacoes.tsx.
Depende de P4.2 implementado.

Tarefa: implementar as telas da central de alertas.

1. web/src/features/alertas/: AlertasPage (lista + filtros), AlertasConfiguracoesPage
   (regras), hooks com TanStack Query no padrão do resto do app.
2. Rotas em AppRoutes.tsx e entrada no menu em navigation.ts, com descricao no mesmo
   tom dos outros itens. A configuração aparece TAMBÉM em Automações → Configurações.
3. Card no dashboard: 5 alertas abertos mais recentes + "Ver todos". Só amarelo e
   vermelho. Sem alertas, mostra "Nenhum alerta pendente" — não some.
4. REMOVER GuiaAlertaNegacoes do dashboard e da tela de Guias, garantindo antes que
   as ações "ocultar alerta" e "nova solicitação a partir da guia" existem no novo
   alerta de guia.negada. Se alguma não existir ainda, PARE e me avise em vez de
   remover — perder essas ações é regressão para quem opera todo dia.
5. Nível é comunicado por cor E por texto/ícone (tema de alto contraste, ADR-23).
   Tokens do design system, sem hex.

Atualize os testes Playwright que referenciam o banner antigo.
Rode npm run lint e npm run test:e2e, e me mostre a saída.
```

---

# FASE 5 — Notificações

Change do openspec: **`notificacoes-de-alertas`**

**Decisões travadas:**
- **Não crie CRUD de modelos.** `email_templates` já existe com CRUD completo (`/configuracoes/emails/templates`), SMTP por tenant e botão de teste — e **nada no app consome**. A central de alertas é o primeiro consumidor. Uma tela a menos, uma dívida a menos.
- **Destinatário global em tabela separada**, não `tenant_id` nullable: `BelongsToTenant` tem global scope, e um registro com tenant null some das queries. O dia que alguém esquecer o `withoutGlobalScopes()` num refactor, o suporte para de receber — e ausência de e-mail parece "sem problemas".
- **Digest diário + imediato só para vermelho crítico**, com janela de silêncio e agrupamento. Sem isso, uma automação em loop manda 200 e-mails de madrugada. Não é hipótese, é o comportamento padrão.

### P5.1 — Proposta no openspec

```
Contexto: repo gestao_de_convenios. Leia AGENTS.md, api/app/Models/EmailTemplate.php,
api/app/Models/EmailSmtpSetting.php, api/app/Http/Controllers/EmailTemplateController.php,
api/app/Http/Controllers/EmailSettingsController.php e o change central-de-alertas.

Fato importante que você deve confirmar antes de escrever: email_templates tem CRUD
completo e NENHUM código do app consome esses templates hoje. Confirme com uma busca
e registre a constatação no proposal.md.

Tarefa: criar o change "notificacoes-de-alertas".

DESTINATÁRIOS — duas tabelas, de propósito:

alerta_destinatarios (do tenant):
  tenant_id, email, nome, niveis (json), chaves (json — null = todas),
  canal (digest|imediato|ambos), horario_digest, ativo, verificado_em
O filtro por chave é o que faz a coisa sobreviver: a recepcionista quer senha
vencendo, o financeiro quer glosa, o suporte quer worker offline. Um campo único com
e-mails separados por vírgula manda tudo para todos e em um mês pedem para desligar.

alerta_destinatarios_globais (a Xiax, suporte@xiax.com.br):
  email, nome, niveis, chaves, canal, horario_digest, ativo
Tabela separada, SEM tenant_id e SEM o trait BelongsToTenant. Justifique no design.md:
com tenant_id nullable, o global scope do trait esconde o registro e a falha é
silenciosa — o suporte simplesmente para de receber e ninguém percebe.
O digest global é UM e-mail por dia com todos os tenants agrupados, nunca um por
tenant.

CANAIS
- digest_diario: tudo que está aberto, agrupado por nível, no horário configurado.
  Amarelo e vermelho. NÃO ENVIA se não houver alerta aberto — digest vazio todo dia
  treina a pessoa a ignorar.
- imediato: só vermelho, só em regras marcadas como críticas, com
  (a) janela de silêncio: não reenvia a mesma chave+entidade por X horas, e
  (b) agrupamento: N falhas em série viram 1 e-mail, não N.
  As duas travas são requisito.

MODELOS DE E-MAIL
Consumir email_templates. Chave = chave da regra; mais alertas.digest_diario e
alertas.imediato. Se o template não existir para uma chave, cai num texto padrão do
código — a notificação nunca deixa de sair por falta de template.
NÃO crie um segundo CRUD de modelos.

SMTP
- O e-mail do tenant sai pelo EmailSmtpSetting do tenant.
- O e-mail do suporte sai pelo SMTP da Xiax, NÃO pelo do tenant. Senão o alerta de
  "SMTP do tenant falhando" nunca chegaria — ele depende justamente do que quebrou.
  Especifique como o SMTP da Xiax é configurado (env ou tabela global).
- A tela de alertas mostra o estado do SMTP do tenant e reaproveita o endpoint
  /configuracoes/emails/teste que já existe.

HIGIENE DE DESTINATÁRIO
- verificado_em: e-mail de confirmação no cadastro.
- Após N falhas de envio consecutivas, desativar o destinatário e abrir alerta
  email.destinatario_falhando (amarelo). Bounce derruba a reputação do SMTP e você
  não fica sabendo.

Extensibilidade: canal é string, não boolean 'enviar_email'. WhatsApp entra depois
sem migration. NÃO implemente WhatsApp agora.

Rode openspec validate.
```

### P5.2 — Implementação

```
Contexto: repo gestao_de_convenios. Leia AGENTS.md e a spec aprovada em
openspec/changes/notificacoes-de-alertas/.

Tarefa: implementar.

1. Migrations e models das duas tabelas de destinatários (a global SEM BelongsToTenant).
2. Serviço de notificação: monta o corpo a partir de email_templates (com fallback),
   respeita níveis, chaves e canal de cada destinatário.
3. EnviarDigestAlertasJob no scheduler DE HORA EM HORA, disparando para quem tem
   horario_digest naquela hora. Não crie um agendamento por horário possível.
4. Envio imediato disparado pelo avaliador da fase 4, com a janela de silêncio e o
   agrupamento da spec.
5. Digest global do suporte: um e-mail com todos os tenants agrupados, pelo SMTP da
   Xiax.
6. Desativação automática de destinatário após N falhas + alerta
   email.destinatario_falhando.
7. Telas: bloco "Destinatários" em /alertas/configuracoes (CRUD), e o bloco de
   modelos LINKANDO para /configuracoes/emails/templates — não uma cópia.
8. Seed dos templates padrão de alertas.digest_diario e alertas.imediato em
   email_templates.

Testes:
- digest não envia quando não há alerta aberto;
- destinatário com chaves=['senha.vencendo'] não recebe alerta de guia.negada;
- imediato não reenvia dentro da janela de silêncio;
- 5 alertas da mesma chave viram 1 e-mail;
- destinatário global recebe de todos os tenants num e-mail só.

Ao final, liste as specs lidas e os comandos executados.
```

---

# FASE 6 — Manual como produto + Novidades

Change do openspec: **`manual-produto-e-novidades`**

**Antes de qualquer código, rode isto em produção:**

```sql
SELECT tenant_id, tipo, updated_at, LENGTH(conteudo_html) FROM manuais;
```

Com 1 tenant só, são cinco minutos. Se a NeuroKids nunca editou, a remoção é limpa. Se editou, você precisa preservar o texto dela antes — é o único ponto do plano onde dá para perder dado de cliente.

### P6.1 — Proposta + implementação

```
Contexto: repo gestao_de_convenios. Leia AGENTS.md, api/app/Models/Manual.php,
api/app/Http/Controllers/ManualController.php, web/src/features/manual/ManualPage.tsx,
api/app/Support/PermissionCatalog.php e as migrations de 'manuais'.

Premissa já decidida: o manual deixa de ser conteúdo do TENANT e passa a ser conteúdo
do PRODUTO. Só assim "atualizou o manual -> vira novidade" faz sentido: novidade é
release note do produto, não edição da clínica.
Confirmação feita: verifiquei em produção o conteúdo de 'manuais' antes de remover.

Tarefa: criar o change "manual-produto-e-novidades" e implementá-lo.

MANUAL
- Servir manual e mapa mental direto de resources/manual/*.html, versionados no git.
- Remover: ManualController::update, UpdateManualRequest, a permissão manual.manage do
  PermissionCatalog, os botões de edição e o textarea de ManualPage.tsx.
- Dropar a tabela manuais (migration com down que a recria, e um passo documentado de
  exportar o conteúdo atual antes).
- Trade-off aceito e a registrar em docs/decisoes-arquitetura.md: toda correção de
  texto passa a exigir deploy. Em troca, some a divergência de manual entre tenants.

NOVIDADES — versão 1 deliberadamente enxuta
- Novidades são arquivos markdown em resources/novidades/AAAA-MM-DD-slug.md, com
  frontmatter: titulo, tipo (manual|melhoria|correcao|aviso), data.
- GET /novidades?limit=5 e GET /novidades leem o diretório, com cache.
- Sem tabela de conteúdo. Sem tela de admin. Quando publicar sem deploy virar dor
  real, aí vira CRUD — hoje não é.
- Atualizou o manual? Você escreve o arquivo da novidade no mesmo commit. É manual de
  propósito: diff de HTML não vira texto legível, e novidade boa é escrita por alguém.

- Tabela novidade_leituras (user_id, slug, lido_em): é o que faz o card mostrar
  "2 não lidas" e parar de aparecer depois. Sem isso o card vira paisagem em duas
  semanas.
- Card no dashboard: 5 últimas + "Ver todas". Tela /novidades com a lista completa.

Rode openspec validate, php artisan test e npm run lint.
```

---

# FASE 7 — Métricas e gráficos (adiada)

**Não construa agora.** Com menos de 1 mês de dado e um tenant, qualquer gráfico de evolução é ruído. Reavalie quando tiver 3-4 meses de `guia_status_historico` acumulado.

Quando for a hora, dois pré-requisitos que já dá para anotar:

1. **`metricas_diarias` agregada por job.** O `ExpurgarAuditoriaJob` apaga `audit_logs` por `auditoria_retencao_meses` — série histórica que dependa dele morre junto. A agregada sobrevive.
2. **Tokens de série no design system antes da biblioteca de gráfico.** Não há nenhuma lib no `web/package.json`, e o `ds:check` reprova hex e valor mágico — qualquer Recharts/Chart.js entra cuspindo cor hex em SVG e reprova o build. Defina `--serie-1..8` validados no teste de daltonismo (você tem tema de alto contraste por requisito real de um profissional), ou desenhe em SVG puro com os tokens existentes.

E separe as duas coisas que hoje estão na mesma frase:

- **Uso do sistema** (logins, ações por usuário) — métrica sua, para saber se o tenant está engajado. Relatório interno, não tela do cliente.
- **Evolução da operação** (guias/mês, taxa de aprovação, dias até autorização, conciliado × glosado) — é o que o cliente paga para ver. Faça esta primeiro.

---

## 3. Checklist de "pronto" por fase

- [ ] **F0** — `/api/health` responde 200; devolve 503 com o cron parado; Uptime Kuma fora da VPS notificando WhatsApp e suporte@xiax.com.br; worker com `restart: unless-stopped`
- [ ] **F1** — `GET /saude` lista componentes; card no dashboard; matar o worker de propósito muda o card em até 3x o intervalo
- [ ] **F2** — transição de status grava histórico e carimbo; teste da trava falha se alguém escrever status por fora
- [ ] **F3** — card de Guias com 3 linhas, cada uma abrindo a listagem filtrada; sem `count()` extra no polling
- [ ] **F4** — avaliador roda 2x sem duplicar; alerta some sozinho quando a causa some; `GuiaAlertaNegacoes` removido **com** as ações preservadas
- [ ] **F5** — digest chega em suporte@xiax.com.br com todos os tenants num e-mail; alerta em loop não vira enxurrada; destinatário filtrado por chave recebe só o dele
- [ ] **F6** — manual read-only servido do repo; novidades com "não lidas" funcionando

## 4. Ordem de execução resumida

```
F0 (meia tarde)  ->  F1  ->  F2  ->  F3  ->  F4  ->  F5
                                                     F6 encaixa em qualquer intervalo
                                                     F7 daqui a 3-4 meses
```

A fase 0 vem antes de tudo por um motivo só: hoje, se o worker cair às 2h da manhã, você descobre quando a NeuroKids reclamar.
