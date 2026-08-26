# Entregas de 26/08/2026 — design system, responsividade e contrato

Sessão de front. O que segue é o registro do que mudou, por quê, e o que ficou de fora.

## Ambiente

O repositório não trazia `.env` nem `.env.example` na API. O ambiente local subiu com **SQLite**
em vez do MariaDB do `docker-compose.yml` — o `phpunit.xml` já roda em SQLite, então as 82
migrations são compatíveis, e o daemon do Docker estava parado.

Com **PHP 8.5**, `php artisan serve` imprime avisos de *deprecated* do Laravel 11
(`PDO::MYSQL_ATTR_SSL_CA`) **dentro do corpo das respostas**, corrompendo todo JSON. O servidor
local sobe com `-d display_errors=0 -d error_reporting="E_ALL & ~E_DEPRECATED"`. Em produção, com
Apache/FPM, o aviso vai para o log — não é urgente, mas instalar `php@8.4` é a correção de raiz.

## Dados de demonstração

`api/database/seeders/DemoDataSeeder.php`, determinístico. Carrega com:

```bash
php artisan migrate:fresh --seed --force
php artisan db:seed --class=DemoDataSeeder --force
```

46 pacientes · 72 solicitações · 93 guias · 43 antecipações · 320 lançamentos · 42 conciliações ·
80 linhas de analítico · 4 clínicas. Todos os status de cada domínio têm linha, mais casos de borda
de propósito: carteirinha vencida, cadastro inativo, automação com falha.

Conta nova: `superadmin@clinica-exemplo.test` / `password`. A tela `/clinicas` exige
`users.super_admin` e nenhuma conta do seeder padrão tinha a marca.

## Defeitos corrigidos

**`/convenios/:id` dava HTTP 500.** `ConvenioController::regras()` chamava
`Collection::toResourceCollection()`, que só existe no Laravel 12; o projeto roda 11.54. A aba de
regras estava morta também em produção.

**`admin` nunca recebia `configuracoes.clinica.manage`.** A permissão era concedida só por
migration, que roda **antes** dos seeders — em banco novo não havia papel para receber, e o
`RoleCatalog` (fonte do seeder e da criação de clínica pela tela) não a listava. Toda clínica nova
nascia sem acesso a `/configuracoes/clinica`.

**O CSS do modelo de impressão vazava para o app inteiro.** Ver ADR-21.

**Guias.** Os cartões do detalhe declaravam `xl:grid-cols-4` com contagem que não é múltipla de 4 —
Paciente+Convênio eram 2 cartões numa fila de 4, metade da largura vazia no topo da tela. E os selos
da lista usavam `w-fit` em coluna estreita: herdavam largura de `min-content`, o texto quebrava a
cada espaço e a pílula virava um blob de três linhas, inflando a altura da linha de 123px para 91px.

**Mobile.** O culpado dos estouros não eram as tabelas — 11 das 21 já tinham contêiner com rolagem.
Era o `Tooltip`, `invisible` (escondido da vista, mas com a caixa no layout): um painel de 288px
ancorado à esquerda esticava a página para 634px num viewport de 390px, em 12 das 14 listagens.

**Alinhamento.** Uma fila de filtro tinha campo de 47px, seletor de 50px e botão de 40px lado a
lado. Altura única de 44px para campo e seletor no base layer — a receita está duplicada em ~13
helpers locais, e o mesmo helper serve `<input>` e `<textarea>`, então fixar altura lá achataria o
textarea. Mais 21 botões feitos à mão normalizados para a altura do `Botao`.

## Verificação

Auditoria automatizada de 64 rotas × 4 larguras (390, 768, 1024, 1440), medindo no navegador:
rolagem horizontal da página, conteúdo estourando o pai, texto cortado, botões desalinhados na
mesma fila, alvo de toque abaixo de 24px, **contraste do que a tela realmente pinta**, erro de JS e
resposta HTTP.

De **1.195 achados para 28**, dos quais 8 são esperados: 422 em `/api/configuracoes/ia/modelos`
(sem chave da OpenAI) e 403 em `/api/tenants` (rota de super admin). Os 20 restantes são
desalinhamentos de 6 a 8 pixels em filas de conteúdo misto.

O auditor tinha três falsos positivos próprios, corrigidos no caminho: conteúdo dentro de `sr-only`
(1px por construção), caixa de seleção medida sem o rótulo que a envolve, e contraste de componente
desabilitado — que a WCAG 1.4.3 isenta.

## Tema de alto contraste

Entrou por requisito de um profissional da clínica com deficiência de visão de cor, que pediu bordas
marcadas em cada cartão. O botão fica na barra superior, e no celular dentro do painel de menu.

A medição mostrou um problema maior que o pedido: na paleta padrão, sob deuteranopia, `sucesso` e
`perigo` ficam a distância perceptual **18** (e **14** sob protanopia) — "Aprovado" e "Negado" são
quase a mesma cor. O tema redesenha a paleta com a oposição azul/laranja e deixa `info` neutro; os
dez pares foram medidos nas três formas de daltonismo e nenhum colapsa.

O texto de cada chip fica escuro (4,5:1 sobre o preenchimento) e é a **borda** que carrega a cor —
ela tem piso de 3:1, então pode variar de matiz o quanto for preciso. O pedido e a restrição técnica
levavam à mesma solução. Ver ADR-23.

O contrato da §11 passou a fiscalizar todo bloco `[data-theme]` que existir, não uma lista fixa.

## Pendências registradas

- **Senha de administrador estava em constante no fonte** (`2026_08_07_100000_create_admin_inicial_
  fbfert`), num repositório **público**, desde 11/08/2026. Corrigido em 26/08: a senha da primeira
  criação vem de `SEED_ADMIN_PASSWORD` e, sem a variável, a conta não é criada — mesma regra que o
  `UserSeeder` já aplicava. **Remover do fonte não remove do histórico:** a senha continua legível
  em quatro commits antigos, então ela precisa ser trocada no servidor, e não apenas no código.
- **`npm run test:e2e` apaga o banco de desenvolvimento.** Roda `migrate:fresh --seed
  --env=testing` e não existe `.env.testing`, então cai no `.env`.
- **`UsuariosApiTest` falha (pré-existente).** Espera 4 usuários; a migration do admin inicial só
  cria a conta se já houver tenant, e em banco novo migration roda antes do seeder — mesmo padrão
  do defeito de permissão acima.
- **Raio de campo e controle** ainda na escala crua (`rounded-2xl` em input e botão). É a mesma
  classe da migração de tipografia e merece guarda própria.

## Automação Unimed — confirmação de guia incerta pós-submit (sessão separada)

Motivado pela Solicitação #5 (Laura de Faveri): 3 itens travados em `status_operacional =
'uncertain'` (`UNCERTAIN_AFTER_SUBMIT` — o worker clicou em Finalizar mas não leu a confirmação de
volta, sem número de guia). Como nenhuma Guia local é criada nesse caso, e
`GerarGuiaUnimedService::avaliar()` bloqueia reenvio enquanto existir qualquer execução `uncertain`
do item, esses itens ficavam travados **para sempre** — o endpoint `reprocessar()` já recusava
explicitamente esse caso com a mensagem "exige confirmação idempotente antes de reprocessar", mas
essa peça nunca tinha sido construída. É também o caso 14, ainda `Pendente`, do roteiro de
homologação real (`docs/automacao-unimed/v2-05-homologacao-real.md`).

**Validação ao vivo antes de codificar.** Havia um script de diagnóstico avulso
(`worker-unimed/scripts/checar-guia-paciente.js`) que assumia que abrir "Exames em aberto" sem
preencher `s_nr_guia` já lista os exames abertos reais, paginados — nunca confirmado contra o
portal de produção. Rodado ao vivo em 26/08 contra `rda.unimedsc.com.br`: confirmado (11 páginas
varridas), e ao mesmo tempo resolveu o caso real — nenhuma das 3 guias da Laura apareceu na
listagem, indicando que o Finalizar não chegou a criar a guia no portal. Os 3 itens foram destravados
manualmente no banco (execução `gerar_guia` original: `uncertain` → `failed`,
`erro_codigo = CONFIRMADO_NAO_CRIADA`; item: `status_operacional` → `pending`) — mesma transição que
o serviço novo abaixo automatiza.

**O que foi construído:**
- `worker-unimed/src/operations/confirmarGuiaIncerta.js` — nova operação `confirmar_guia_incerta`:
  login, abre "Exames em aberto" sem filtrar, pagina até achar uma linha cujo texto contenha os
  últimos 6 dígitos da carteirinha do paciente (promovendo a técnica do script de diagnóstico),
  entra na guia encontrada e lê `NR_GUIA`/`DT_AUTORIZACAO`/`NR_SENHA`/etc. pela mesma tela de
  execução já usada em `consultarStatusGuia`.
- `App\Services\Automation\ConfirmarGuiaIncertaUnimedService` — se achou: cria/atualiza a Guia local
  e resolve a execução `gerar_guia` original para `succeeded`. Se não achou: resolve a execução
  original para `failed` (`CONFIRMADO_NAO_CRIADA`) e o item volta a `pending` — **nunca reenvia
  sozinho**, só libera o botão manual.
- `EnfileirarConsultasUnimedDueJob` ganhou um terceiro bloco: busca itens `uncertain` sem Guia,
  respeitando um intervalo mínimo e uma janela de horário (`[início, fim]`) configuráveis por tenant
  em **Automações → Configurações** (novos campos em `configuracoes_globais`; o tick do job continua
  a cada 30 min, então intervalo menor que isso não tem efeito prático).
- Tela de Solicitações: quando o item está travado nesse estado específico
  (`operacao === 'gerar_guia' && status === 'uncertain'`), o botão apagado "Enviar para Unimed" vira
  "Verificar Andamento" (`POST /solicitacao-itens/{id}/verificar-andamento`).

**Pendente:** rodar a homologação real de ponta a ponta (deploy + exercitar o botão "Verificar
Andamento" contra o portal real) antes de marcar o caso 14 como aprovado — hoje só o mecanismo de
busca por paciente foi validado ao vivo, não o pipeline novo completo.
