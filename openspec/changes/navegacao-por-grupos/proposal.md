## Why

O menu superior era uma fileira única de 16 links irmãos, de `Dashboard` a `Manual`, sem hierarquia. Três problemas práticos:

1. A fileira quebrava em duas ou três linhas e empurrava o conteúdo para baixo.
2. Nada distinguia uma tela de cadastro (dado que muda pouco) de uma tela de operação (uso diário). Quem estava aprendendo o sistema não tinha como deduzir a ordem de uso a partir do menu.
3. O título "Gestão de Convênios" no cabeçalho ocupava a lateral esquerda sem informar nada — o nome do sistema já está no domínio e na aba do navegador.

Junto disso, as listas suspensas nativas (`<select>`) apareciam ilegíveis: as telas usam `bg-white/5 text-white` no controle, e o navegador reaproveita essas cores na lista aberta, resultando em letra branca sobre fundo branco.

## What Changes

- Remover o título do cabeçalho e renomear `Dashboard` para **Gestão de Convênios**, que passa a ser a tela inicial (`/` e rota desconhecida caem nela).
- Agrupar o menu em **Cadastros**, **Operação** e **Configurações**, cada um com submenu suspenso. O rótulo do grupo navega para uma tela de visão geral; a seta abre a lista.
- Criar as telas de grupo `/cadastros` e `/operacao-convenios`, com um cartão explicativo por subitem e um bloco de métricas abaixo. Operação numera os cartões na ordem de uso; Cadastros não numera, porque as telas são independentes.
- Transformar as abas internas de Configurações em rotas próprias (`/configuracoes/emails`, `/configuracoes/ia`, `/configuracoes/unimed`), servidas pelo submenu. `/configuracoes` passa a ser a antiga aba Geral: aparência no topo e cartões explicativos abaixo.
- Adicionar as métricas de **Especialidades** e **Analíticos** ao `GET /dashboard`, que não tinha bloco para nenhuma das duas.
- Corrigir o contraste das listas de `<select>` nos dois temas e o fundo dos dois iframes que usavam `bg-white`.
- Tirar o cartão **Usuários** da grade do dashboard, para fechar em múltiplo de 3, e trocar o texto de abertura por uma chamada aos atalhos com link para o Manual.

## Capabilities

### New Capabilities

- `navegacao-por-grupos`: hierarquia do menu superior, telas de visão geral de cada grupo e a ordem de uso das telas de operação.

### Modified Capabilities

- Nenhuma capability existente muda de comportamento. As telas de destino continuam as mesmas; muda como se chega até elas.

## Impact

- **Frontend**: novo `web/src/routes/navigation.ts` (fonte única do menu e dos cartões), `ShellLayout` reescrito com submenus, novas telas em `web/src/features/grupos/`, `ConfiguracoesGeralPage`, `ConfiguracoesPage` recebendo a aba por prop, `AppRoutes`, `DashboardPage` e `index.css`.
- **API**: dois blocos novos em `DashboardController` e duas permissões novas (`dashboard.especialidades`, `dashboard.analiticos`), com migration de sync.
- **Banco**: nenhuma tabela nova. A migration só concede permissões a papéis existentes.

## Decisões

- **A ordem dos submenus e a dos cartões vêm da mesma lista.** `navigation.ts` alimenta o menu e as telas de grupo. Duplicar a ordem em dois lugares garantiria divergência na primeira alteração.
- **`Pacientes` aparece em Cadastros e em Operação.** É o mesmo destino. A tela é cadastro por natureza, mas é também o primeiro passo do fluxo operacional, e obrigar o operador a sair do grupo Operação para conferir um paciente seria pior que a duplicidade.
- **As permissões novas são derivadas de uma vizinha, não de nomes de papel.** Quem vê o bloco de Profissionais passa a ver Especialidades; quem vê a conciliação da clínica passa a ver Analíticos. Assim um papel ajustado à mão na tela de Permissões não ganha visibilidade que o administrador tinha retirado.
- **O gatilho de Analíticos é `conciliacoes.view`, não `dashboard.guias`.** O analítico é o demonstrativo de pagamento da operadora, dado financeiro do consultório inteiro. O papel `profissional` tem `dashboard.guias`, mas só as variantes `viewOwn` — derivar de guias lhe daria um agregado que o `RoleSeeder` nunca concede.
- **O cartão Usuários é filtrado na tela, não removido da API.** A tela de Cadastros consome os mesmos blocos e perderia a métrica junto.

## Non-Goals

- Não há filtro do menu por permissão: os itens continuam todos visíveis, como antes. Quem não tem acesso recebe o erro na tela de destino.
- Não há menu lateral nem versão mobile dedicada; o cabeçalho continua quebrando linha em telas estreitas.
- A grade do dashboard fecha em múltiplo de 3 para o papel `admin` (12 blocos). Papéis com menos permissões continuam com a contagem que sobrar.
