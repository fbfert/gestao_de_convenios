Decisões de Arquitetura
Registro das decisões já validadas. Não reabrir sem motivo forte — se uma decisão precisar mudar, documentar o porquê aqui mesmo, não só no código.

ADR-01 — Multi-tenancy: banco único + tenant_id
Banco único, isolamento por coluna tenant_id + Global Scope no Eloquent (TenantScope). Mais simples de operar sozinho do que banco-por-tenant. Migrar pra banco-por-tenant só se algum cliente exigir isolamento físico.

ADR-02 — Conectores de convênio: Strategy Pattern
Cada convênio aponta pra um connector_type (manual | api | scraping). Todos nascem manual. Trocar por automação é implementar uma nova classe de conector, sem tocar no resto do sistema. Job diário roda contra a interface, não contra a implementação.

ADR-03 — Regras de convênio são dado, não código
Frequência de lançamento, quantidade autorizada, validade de senha e valor por profissional ficam em tabelas configuráveis (convenio_regras, tabela_valores), com vigência por data. Nenhuma regra de negócio de convênio deve ser hardcoded em Service/Controller.

ADR-04 — Rollout em duas etapas (Rota A)
Etapa 1 ataca só a esteira de convênios, convivendo com o Clínica Ágil (que segue responsável por agenda/cadastro/estoque/CRM). Etapa 2 expande pra substituição completa, só depois da Etapa 1 validada em produção com o primeiro cliente. Motivo: menor risco, validação rápida, sem competir de imediato com um produto maduro (agenda/cadastro) que já funciona.

ADR-05 — Paciente "leve" na Etapa 1
pacientes carrega só o necessário pro fluxo de convênio (nome, carteirinha, convênio, contato) + clinica_agil_id nullable como referência externa. Prontuário/anamnese completos só entram na Etapa 2.

ADR-06 — Idioma dos dados: status em inglês, UI em português
Valores de status padronizados em inglês no banco/API (under_review, approved, finalized, open, closed, pending, reviewed, paid...). Tradução acontece só na camada de apresentação, via um mapa central de labels (statusLabels.ts no frontend) — evita string em português espalhada pelo código e facilita internacionalização futura.

ADR-07 — Cascata de valor em tabela_valores
Override em cascata: convênio → convênio+especialidade → convênio+especialidade+profissional (mais específico vence), respeitando vigência por data. Lógica isolada em TabelaValoresService, não misturada no ConciliacaoService.

ADR-08 — Permissões: simples na Etapa 1, granular na Etapa 2
role enum (admin/operador) resolve a Etapa 1. Etapa 2 expande com permissoes + role_permissoes granular por tela/ação (via spatie/laravel-permission, que já cobre os dois estágios sem precisar trocar de pacote no meio do caminho).

ADR-09 — Job diário de verificação
Um job por convênio, agendado de madrugada, percorre guias em aberto e aciona o conector configurado. Manual = sinaliza pendência de conferência humana. API/scraping = consulta de fato. Ver ADR-02.

ADR-10 — Ponto de fusão Etapa 1 → Etapa 2
agendamentos.guia_id (nullable, só existe na Etapa 2) é o campo que conecta sessão agendada ao consumo de antecipacao, eliminando o lançamento duplicado que existe hoje entre agenda e controle de convênio.

ADR-11 — User fica fora do TenantScope
O login busca por e-mail antes de existir tenant resolvido na requisição (chicken-and-egg). Por isso User não usa a trait BelongsToTenant/ TenantScope: é o tenant_id do usuário autenticado que alimenta o TenantContext via ResolveTenant middleware, nunca o contrário. Pressupõe e-mail único globalmente entre tenants — se e-mail duplicado entre clínicas virar requisito, revisar (ex: login por slug da clínica + e-mail).

ADR-12 — ciclo_fim é inclusivo
Em antecipacoes, ciclo_inicio e ciclo_fim definem uma janela inclusiva dos dois lados. Ex: ciclo diário → ciclo_inicio == ciclo_fim; ciclo semanal → 7 dias corridos incluindo o último; ciclo mensal → do primeiro ao último dia do mês, ambos incluídos. AntecipacaoService::abrirCiclo() calcula os dois extremos a partir de frequencia_lancamento seguindo essa convenção.

ADR-13 — Route-model-binding implícito não respeita TenantScope sozinho
O SubstituteBindings do Laravel roda numa prioridade de middleware que antecede o ResolveTenant (mesmo com ResolveTenant "append"ado no grupo api), então o binding implícito resolve o Model pelo id cru, antes do TenantContext existir — o TenantScope global não tem o que filtrar nesse momento. Resultado: sem tratamento explícito, um usuário do Tenant A consegue acessar por HTTP um registro do Tenant B pelo id, mesmo com TenantScope implementado corretamente (a falha é de ordem de execução, não do scope).

Mitigação obrigatória: todo Model usado como parâmetro de rota (Guia, Antecipacao, Lancamento, ConciliacaoFinanceira, Solicitacao, e qualquer um que vier depois) precisa de um Route::bind() explícito em AppServiceProvider, buscando pelo tenant_id do usuário autenticado e pelo id, retornando 404 se não bater — não confiar no binding implícito puro do Eloquent nesses casos.

ADR-08 (revisada) — Permissões granulares entram na Etapa 1, não na Etapa 2
A decisão original (role simples admin/operador, granularidade só na Etapa 2) foi superada por necessidade real do cliente: 3 papéis com visibilidade genuinamente diferente (admin, funcionário, profissional), com o profissional restrito aos próprios registros. Substituído por spatie/laravel-permission (já instalado desde o Bloco 1, sem uso até agora), usando o recurso de teams do próprio pacote com team_foreign_key = tenant_id — cada tenant tem seu próprio conjunto de atribuições papel→permissão, isolado nativamente pelo mecanismo do pacote (não é gambiarra em cima do TenantScope).

A coluna users.role (enum admin/operador) é removida; o papel do usuário passa a ser 100% via Spatie ($user->assignRole(...)).

ADR-14 — Catálogo de permissões é fixo no código; CRUD só edita atribuição
O admin, pelo CRUD de permissões, escolhe quais permissões cada papel tem, nunca cria uma permissão nova. O catálogo (nomeado dominio.acao) é seedado uma vez e corresponde 1:1 a checagens reais (Gate::authorize) espalhadas pelo código. Catálogo inicial:

solicitacoes.view, solicitacoes.manage
guias.view, guias.viewOwn, guias.manage
antecipacoes.view, antecipacoes.viewOwn
lancamentos.view, lancamentos.viewOwn, lancamentos.manage
conciliacoes.view, conciliacoes.viewOwn, conciliacoes.manage
medicos.manage
usuarios.manage
convenios.manage
permissoes.manage
Default seedado por papel (editável depois pelo CRUD):

admin: todas
funcionário: tudo exceto convenios.manage, usuarios.manage, permissoes.manage
profissional: só as variantes *.viewOwn (guias, antecipações, lançamentos, conciliações)
ADR-15 — Escopo "Own" é aplicado no Service, não em Policy por model
*.viewOwn não é resolvido por Policy de instância (can('view', $guia)), porque isso exigiria uma query por registro. É aplicado como filtro na query de listagem: se o usuário não tem dominio.view mas tem dominio.viewOwn, o Service adiciona where('profissional_id', $user->profissional_id) antes de paginar. Exige users.profissional_id (nullable FK) — preenchido só quando o papel é "profissional" (ADR anterior: vínculo 1:1, não criação inline).

ADR-07 — Auditoria por observer, não por chamada em controller
A trilha de auditoria é escrita por um trait com observer do Eloquent, e não por `AuditLog::create()` espalhado pelos controllers. Chamada manual garante que todo endpoint novo nasça sem auditoria, e o defeito só aparece quando alguém procura o histórico que não existe. Evento explícito continua existindo para o que não é mudança de modelo — login, recusa de acesso, importação em lote, expurgo — e para o caso em que o evento semântico diz mais que o diff, como a pausa da automação, que registra o motivo. Nesses pontos o registro automático é suspenso para a mesma ação não virar dois registros.

ADR-08 — Censura de campo sensível por nome específico, nunca genérico
Valor de credencial nunca entra na trilha: fica registrado que o campo mudou, quem mudou e quando. A lista de padrões é deliberadamente específica (`password`, `api_key`, `token`, `secret`, `credential`) e **não** inclui `senha`, `chave` ou `key` soltos. Neste domínio, "senha" é o código de autorização que o convênio devolve (`guias.senha`, `validade_senha`, `senha_alerta_dias`, `chave_conciliacao`): um padrão genérico esconderia o miolo da trilha para proteger o que já está protegido por nomes específicos.

ADR-09 — Ordenação de listagem com lista fechada de colunas
O nome da coluna vem da query string e vai direto para o `ORDER BY`, então cada listagem declara o mapa do que aceita ordenar; qualquer outro valor cai no padrão. Colunas de relação ordenam pelo nome exibido, com `join`, e não pelo identificador. A ordenação é sempre resolvida no servidor: ordenar só a página carregada daria a impressão de listar tudo em ordem quando não é o caso. Há desempate por coluna estável, sem o qual páginas seguidas repetem ou pulam registros que empatam na coluna escolhida.

ADR-10 — Leitura de documento por IA nunca grava sozinha
Carteirinha, pedido médico e registro de sessões são lidos pela OpenAI com prompt editável por clínica, mas o resultado sempre volta para conferência: nenhum cadastro é criado ou alterado sem confirmação de quem está com o documento na mão. Campo que o modelo não reconhece volta nulo e não apaga o que já estava preenchido; dado que exige casamento com cadastro — o convênio da carteirinha — só preenche o campo acima de um corte alto de semelhança, e abaixo dele vira sugestão com a nota de proximidade. O contrato de saída (as chaves do JSON) fica no código, e não no prompt editável, porque a tela depende exatamente daquelas chaves.

ADR-16 — A pele do produto é a do xiax-agenda, e é contrato fiscalizado
O gescon e o clinica.gestaonossa.com.br são sistemas irmãos da mesma clínica; parecer produtos de empresas diferentes é defeito, não liberdade. Os tokens vêm de `design-system-xiax-agenda.md` (raiz) copiados verbatim — não se ajusta tom "só um pouquinho", porque os neutros quentes e o verde-petróleo são a identidade. A adoção não exigiu reescrever telas: as ~1.500 ocorrências de utilitário cru (`text-slate-200`, `bg-cyan-400/10`) continuam onde estavam, e é a **paleta nativa do Tailwind** que foi reapontada para os primitivos do design system. O reaponte vive em `@media screen` porque a impressão precisa de `bg-white` branco de verdade.

ADR-17 — Tema entra por necessidade, não por preferência
O tema escuro foi removido em 26/08/2026 — store, seletor, bootstrap e bloco CSS — em vez de mantido desligado: tema sem uso é pele morta que ninguém testa e que diverge a cada mudança de token. No mesmo dia entrou um segundo tema, "Alto contraste", por requisito de acessibilidade de um profissional da clínica com deficiência de visão de cor. A diferença entre os dois casos é o critério: preferência estética não paga o custo de manutenção, necessidade de uso paga.

O tema novo redefine apenas os papéis semânticos, como a arquitetura de dois níveis previa (ADR-16) — e é isso que fez o custo caber num bloco de CSS em vez de numa varredura por telas.

ADR-23 — No tema de alto contraste, o canal cromático é a borda
"Alto contraste" não é o tema padrão com a borda mais grossa. Medindo a paleta padrão sob deuteranopia, `sucesso` e `perigo` ficam a distância perceptual 18 — e 14 sob protanopia. Ou seja: "Aprovado" e "Negado" são praticamente a mesma cor para quem tem a forma mais comum de daltonismo. Borda grossa não resolveria isso.

A paleta do tema foi redesenhada com a oposição azul/laranja, que é a que sobrevive ao daltonismo vermelho-verde, e `info` virou neutro para liberar orçamento de matiz. Os dez pares semânticos foram medidos nas três formas (deuteranopia, protanopia, tritanopia) e nenhum colapsa.

A decisão menos óbvia é a separação de canais. O texto precisa cumprir 4,5:1 sobre o preenchimento, o que o obriga a ser escuro e limita a variação de matiz — foi por isso que a primeira tentativa de separar `perigo` de `alerta` só passava num dos dois critérios de cada vez. A borda não tem essa amarra (piso 3:1, WCAG 1.4.11), então é ela que carrega a identidade da cor. O pedido do profissional — "bordas bem marcadas" — e a restrição técnica apontavam para a mesma solução.

A espessura mora em regra própria, fora de `@layer`, e não em token de paleta: espessura não é cor. O seletor pega quem já tem borda (`[class*="border"]`) em vez de dar borda a quem não tem — a intenção é reforçar a delimitação existente, não inventar caixa onde o desenho não previa.

ADR-18 — Tamanho de texto só por papel
Sete papéis (`display`, `titulo`, `subtitulo`, `corpo-lg`, `corpo`, `rotulo`, `meta`), cada um com entrelinha e peso próprios. A escala crua do framework está banida e é reprovada pelo contrato. Não é purismo: `text-sm` traz entrelinha 1,43 e `text-corpo` traz 1,5, então uma área escrita com a escala crua desalinha o ritmo vertical do produto **por construção**, sem erro visível. A migração revelou o sintoma: `text-4xl` e `text-3xl` conviviam como dois tamanhos para o mesmo papel — título de página.

ADR-19 — O compositor de classes precisa conhecer os papéis de tamanho
`tailwind-merge` classifica `text-*` por lista interna. Ele sabe que `text-white` é cor, mas `text-corpo` e `text-sobre-acento` são nomes novos: sem declaração, os dois caem no grupo de COR e o último vence. No xiax-agenda isso chegou a produção — o botão primário perdia a cor e pintava o rótulo em `--texto` sobre o verde do acento, 2,52:1 medido. O contrato de contraste não viu nada, porque ele confere os tokens, não o que a tela pinta. Por isso a configuração precede a existência do primeiro papel.

ADR-20 — Tabela vira cartão por atributo, com corte por número de colunas
Uma tabela de 8 a 15 colunas não cabe em celular nem em tablet retrato. Abaixo do corte, cada linha vira cartão de pares "rótulo → valor" — o rótulo sai de `data-rotulo` na célula, porque CSS não consegue ler o texto do `<th>` correspondente. O corte é por tabela (`data-cartoes="md"` abaixo de 48rem, `"lg"` abaixo de 64rem): forçar cartão numa tabela de três colunas em tablet é pior que a tabela. O CSS mora fora de `@layer` porque no Tailwind v4 quem vence empate é a camada, e as células carregam utilitários (`px-4 py-3`, `w-px`) da camada `utilities`.

ADR-21 — HTML de modelo de impressão roda isolado
Os modelos de impressão são HTML editável pela clínica e trazem `<style>` próprio. Injetados com `dangerouslySetInnerHTML`, esse `<style>` vale para a **página inteira**, mesmo com a seção escondida: o modelo padrão declara `.grid`, `.box`, `table`, `th`, `td` e `body`, nomes que colidem de frente com as classes utilitárias. O efeito era `.grid` virando duas colunas fixas em três telas, fonte do app trocada por Arial e tabelas de verdade reestilizadas. Shadow root resolve nos dois sentidos; `body` do modelo vira `:host`, já que dentro de shadow root aquele seletor não casa com nada.

ADR-22 — Contrato de design reprova o build
Quatro guardas em `web/scripts/verificar-design-system.mjs`, ligadas ao `npm run lint`: contraste calculado a partir do próprio CSS, ausência de valor mágico, classe com cara de token que não existe, e configuração do compositor de classes. A terceira é a menos óbvia e a mais valiosa — `border-borda` (o token é `borda-campo`) não gera CSS nenhum, o componente renderiza sem erro e simplesmente fica sem a pele da casa. Isenção é permitida, mas só com o motivo registrado na lista: guarda que vira ruído é guarda que alguém desliga.
