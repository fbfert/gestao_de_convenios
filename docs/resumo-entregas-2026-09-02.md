# Entregas de 02/09/2026 — liga/desliga por automação, guias "A DEFINIR" fora do padrão, intervalo próprio de senha/validade, clínicas

## 1. Liga/desliga por automação; busca de senha/validade direto na lista de Guias

Todas as automações de fundo (reconsulta de status Unimed, captura de senha/validade, verificação
de guia incerta, sincronização com a clínica, expurgo de auditoria, expurgo de carteirinhas,
verificação diária de guias não-Unimed) ganharam um liga/desliga por tenant em
`/automacoes/configuracoes`, além do intervalo de sincronização com a clínica já existente.
Desligar uma automação não afeta a ação manual equivalente ("Buscar senha/validade",
"Sincronizar Agora" etc.) — só o agendamento em lote.

Na tela de Guias, os campos Senha e Validade mostram um botão de busca ("Buscar Senha"/"Buscar
Validade") quando vazios, reaproveitando o mesmo endpoint de captura que já existia na tela de
detalhe da guia.

**Bug latente corrigido no caminho:** `ConfiguracaoGlobal::doTenant()` devolvia a linha
recém-criada sem os defaults do banco carregados em memória (o `INSERT` só leva `tenant_id`) —
a primeira automação a rodar para um tenant que nunca abriu a tela de configurações lia todo
campo com default como `null`, inclusive os liga/desliga novos. Corrigido com `fresh()` depois de
criar.

Commit: `e10976d`.

## 2. Guias "A DEFINIR" saem da automação, do alerta e da listagem padrão

Guias com Especialidade ou Profissional ainda `"A DEFINIR"` (placeholder deixado por
importação/sincronização incompleta) agora ficam fora por padrão: não entram nas automações da
Unimed (lote agendado nem clique manual), não aparecem no card de Atenção de guias negadas, e
somem da listagem padrão de Guias. Botão novo **"Mostrar guias A DEFINIR"**, ao lado do "Mostrar
vencendo em 7 dias", revela essas guias sob demanda.

Commit: `67fcc93`. Testes: `GuiaServiceTest`, `GuiasApiTest`.

## 3. Busca de senha/validade Unimed ganha intervalo próprio (1h/6h/12h/24h)

Antes a busca de senha e validade seguia o mesmo ciclo de 30 min do job de fila, sem cooldown
próprio — uma guia pendente era reprocessada a cada tick. Agora cada guia só volta a ficar
elegível depois do intervalo configurado em Automações > Configurações — novo campo
`unimed_captura_senha_validade_intervalo_horas` (1/6/12/24h, padrão 6h) e uma coluna de controle
`unimed_senha_validade_next_check_at` na guia, seguindo o mesmo padrão já usado pela reconsulta de
status.

Commit: `ebe0845`. Testes: `ConfiguracoesGlobaisApiTest`, `GerarGuiaUnimedApiTest`.

## 4. Clínicas: slug editável, sem slug no cabeçalho, botão de tema só ícone

- `/clinicas`: o identificador (`slug`) da clínica passa a ser editável na tela de edição — antes
  era travado por design, só decidia unicidade/formato na criação. Não é usado para resolver o
  tenant em runtime (login/middleware usam `tenant_id`), então editar é seguro.
- Cabeçalho para de mostrar o slug junto do nome da clínica (usuário e mobile) — fica só o nome.
- `BotaoTema` fica só o ícone, sem o texto "Alto contraste"/"Padrão" — a informação continua
  acessível via `aria-label`/`title`.

Commit: `7c6e440`. Testes: `TenantsApiTest`.

## 5. Super admin acessa outra clínica com acesso total e auditado

Novo botão **"Acessar"** na tela de Clínicas (visível só pro super admin) gera um token novo, da
própria conta, marcado via ability Sanctum com o tenant-alvo — não é login como outro usuário, é
um acesso temporário à clínica escolhida, sem precisar de uma conta separada por tenant.

Peça central: `User::getTenantIdAttribute()` se autocorrige para o tenant-alvo enquanto esse
token estiver em uso. Como todo o resto do sistema (dezenas de controllers) lê essa mesma
propriedade pra gravar e ler dados por tenant, a correção se propaga automaticamente sem tocar em
nenhum desses pontos — só o super admin, e só quando o token atual carrega a ability, é afetado.
`User::hasPermissionTo()` usa o mesmo sinal pra liberar toda permissão nesse modo (o super admin
não tem papel atribuído no tenant alheio).

Sair do acesso reusa `POST /logout` (que já só derruba o token da própria requisição) e restaura
o token/usuário de origem guardados no `authStore`. Uma faixa de aviso fixa fica visível o tempo
todo, com o botão de voltar. Entrada e saída ficam na auditoria
(`acesso.super_admin_entrar`/`acesso.super_admin_sair`).

Commit: `352ff9a`. Testes: `TenantsApiTest`. Validado ao vivo em produção — escopo de dados,
bypass de permissão, isolamento do token de origem e trilha de auditoria.

## Verificação — como cada entrega foi validada

Todas: `run-tests.sh` (backend, sqlite isolado) + `tsc -b`/`oxlint`/`verificar-design-system.mjs`
(frontend) antes de cada deploy, com confirmação do usuário antes de recriar os containers de
produção. Itens 2, 4 e 5 também verificados ao vivo com usuário QA descartável (Playwright contra
a produção), limpo depois.

## Pendente

- Item 2 (guias "A DEFINIR"): a causa raiz — como essas 2238 guias chegaram com o placeholder —
  só foi investigada e corrigida no dia seguinte (ver `resumo-entregas-2026-09-03.md`).
