---
titulo: Relatórios por período, com comparação e exportação
tipo: melhoria
data: 2026-09-18
---

O sistema só sabia contar o **agora**. O painel mostra o que está aberto hoje, as listagens filtram o presente — e não havia como responder "a taxa de negação piorou?", "qual convênio glosa mais?", "a automação está mais lenta que mês passado?" nem "quem usa o sistema, e quando?".

O dado já existia. Cada transição de guia tem data, cada execução do robô tem duração, cada ação de cada pessoa está na auditoria, e os analíticos importados trazem pago e glosado. Faltava agregar e comparar.

**Entra a tela de Relatórios**, em Gestão de Convênios → Relatórios.

## O menu virou grupo

"Gestão de Convênios" passou a ser um **grupo** no menu, com dois cartões: **Painel** — o de sempre — e **Relatórios**. O padrão é o mesmo de Cadastros e Operação.

Isso custa um clique a mais para chegar ao painel, e é uma escolha consciente: as duas telas respondem perguntas diferentes e merecem entradas diferentes. O login continua levando direto ao Painel.

## Quatro abas sobre os mesmos filtros

| Aba | Responde |
|---|---|
| **Operação** | Solicitações e guias criadas, taxa de aprovação e negação, tempo até a decisão, sessões e faltas, senhas a vencer, antecipações dispensadas |
| **Financeiro** | Valor executado, apresentado, pago e glosado, % de glosa, situação das conciliações, repasse estimado por profissional |
| **Automações** | Execuções do robô, taxa de sucesso, duração média e no percentil 95, tempo em fila, erros por código, horas fora do ar |
| **Uso do sistema** | Usuários ativos, acessos, ações por dia e por hora do dia, importações confirmadas, alertas reconhecidos |

Os filtros do topo — período, convênio, especialidade, profissional — **continuam valendo ao trocar de aba**. Investigar um número quase sempre passa por olhar o mesmo mês sob outro ângulo: a negação subiu, e aí você quer ver o que a automação fez naquele mês. Refiltrar a cada aba quebraria a investigação.

A permissão é **por aba**. Quem não tem a do Financeiro não vê essa aba — nem digitando o endereço. Quem não tem nenhuma das quatro não vê a entrada no menu. Ajuste em Perfis e Permissões.

## A comparação diz se melhorou, não se subiu

Marcando **"Comparar com o período anterior"**, cada número ganha o valor do período imediatamente anterior, **de mesmo tamanho** — 30 dias comparam com os 30 dias anteriores, não com "o mês passado" genérico. Comparar 30 dias com 28 faria a variação mentir por 7% sem nada na tela avisar.

A cor segue a **melhora**, e não a direção: taxa de negação caindo fica verde; sessões realizadas caindo fica vermelho. Indicador que não tem lado bom, como "execuções", aparece sem cor.

## Travessão não é zero

Onde aparecer **—**, o sistema não tinha base para calcular. Taxa de aprovação sem nenhuma guia decidida no período é uma pergunta sem resposta, não uma clínica que não aprovou nada. O mesmo vale para "Sem dados no período" nos gráficos, e para os valores do convênio quando nenhum analítico foi importado naquele recorte: zero ali diria que a operadora não pagou nada.

## O endereço guarda o recorte

Período, filtros, aba aberta e comparação ficam na URL. **Copie o endereço e mande para alguém** — a pessoa abre exatamente o mesmo relatório. É por isso que o link traz as datas, e não só "30 dias": o atalho mudaria de significado amanhã.

## Exportar para CSV ou Excel

Toda tabela tem os dois botões, com os mesmos filtros da tela. O **CSV** já vem formatado em português, para abrir direto no Excel sem virar uma coluna só. O **XLSX** traz os números como número — é o que permite somar e ordenar na planilha.

Toda exportação fica registrada em Logs de Auditoria: quem exportou, de qual aba, qual tabela e sob quais filtros. O arquivo sai do sistema para um lugar onde nenhuma permissão alcança, e o registro é o que permite responder depois.

## Três coisas para saber antes de estranhar um número

- **Aprovação e negação contam pela data da DECISÃO**, não pela data em que a guia foi criada. Uma guia aberta em agosto e negada em setembro é negação de setembro.
- **"Valor apresentado" é pago + glosado** dos analíticos importados no período. A operadora não manda esse campo; ele é derivado do que ela processou.
- **"Horas fora do ar" só enxerga de 18/09/2026 em diante.** O registro de queda dos componentes começa nesta versão; período anterior aparece como "—", porque não ter registro é diferente de estar sempre no ar.

O resultado de cada consulta é reaproveitado por 5 minutos — trocar de aba e voltar não refaz a conta. O rodapé da tela avisa quando o número veio desse reaproveitamento.
