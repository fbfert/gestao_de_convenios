## Why

Pedido de 23/09/2026, seis ajustes pequenos que o uso diário expôs:

1. O card "Sessões em conflito" do dashboard abre a lista **inteira** de guias. A API já sabe filtrar (`sessoes_em_conflito=1`), mas a tela de Guias só lê da URL os filtros que declara, e esse não estava entre eles. O mesmo defeito derruba `pendente=1` (Negadas, Verificar Restrição) e `senha_vencendo=1` (Senha vencendo) — o card promete um número e a lista mostra outro.
2. O Acesso rápido do dashboard tem "Ver auditoria", mas não um atalho para Relatórios.
3. O Resumo por área não mostra antecipações, embora `dashboard.antecipacoes` já esteja no catálogo de permissões e a spec do dashboard liste antecipações entre os blocos.
4. Em Lançamentos, quando a leitura da folha erra o número da guia (letra ilegível, espaço), o modal "Selecionar guia" diz só "Nenhuma guia com sessão disponível encontrada" — sem dizer ao operador que o problema provável é a leitura.
5. O aviso "A folha diz: …" do executante é pequeno demais para cumprir o papel de conferência.
6. O modo simulação da finalização Unimed é ligado por configuração (`automacao_finalizar_guia_simulacao_ativo`, padrão ligado), mas a coluna nunca foi exposta na API nem na tela. A mensagem do sistema manda "desligar em Configurações", e não há onde. É o avesso de "Parâmetro salvo tem efeito observável": um parâmetro com efeito que ninguém consegue alterar.

## What Changes

- Tela de Guias passa a ler da URL `sessoes_em_conflito`, `pendente` e `senha_vencendo`, e mostra um selo removível quando algum deles está ativo.
- Botão "Ver relatórios" ao lado de "Ver auditoria", visível para quem tem alguma permissão `relatorios.*`.
- Dois blocos novos no Resumo por área, sob `dashboard.antecipacoes`:
  - **Antecipações elegíveis**: a mesma contagem de entradas da fila da tela de Antecipações (agrupada por solicitação). Abre `/antecipacoes`.
  - **Antecipações realizadas**: total de antecipações geradas; detalhe "X neste mês · Y dispensadas". Abre o histórico filtrado por gerada.
- Modal "Selecionar guia": quando aberto a partir da leitura e sem resultado, acrescenta "Confira se o número foi lido corretamente no arquivo de origem e ajuste-o."
- Aviso do executante lido: fonte maior e nome em destaque.
- Liga/desliga do modo simulação em Configurações > Automações (API, validação, tela), com confirmação ao desligar.
- Migration que **desliga** a simulação em todas as clínicas no deploy (decisão do responsável em 23/09/2026). O padrão da coluna continua ligado para clínicas novas.

## Capabilities

### New Capabilities
- `dashboard-resumo-e-atalhos`: atalho para relatórios e blocos de antecipação no dashboard. Capability própria porque `dashboard-home` ainda não foi promovida a `openspec/specs/`.

### Modified Capabilities
- `sessoes-regras-de-agenda`: o alerta de conflito abre só as guias em conflito.
- `automacao-unimed-finalizar-guia`: o modo simulação é alterável pela tela de configurações.
- `importacao-de-sessoes`: orientação quando o número lido não encontra guia; destaque do executante lido.

## Impact

**API**
- `DashboardController` — blocos `antecipacoes_elegiveis` e `antecipacoes_realizadas`
- `ConfiguracaoGlobalController`, `UpdateConfiguracaoGlobalRequest` — `automacao_finalizar_guia_simulacao_ativo`
- migration nova desligando a simulação nas linhas existentes

**Web**
- `features/guias/GuiasPage.tsx`, `features/guias/types.ts`
- `features/dashboard/DashboardPage.tsx`
- `features/configuracoes/useConfiguracoesGlobais.ts` e as telas que editam as automações
- `features/lancamentos/SelecionarGuiaModal.tsx`, `features/lancamentos/LancamentosPage.tsx`

**Risco**
- Com a simulação desligada, a próxima finalização grava e finaliza a guia na Unimed, sem volta.
