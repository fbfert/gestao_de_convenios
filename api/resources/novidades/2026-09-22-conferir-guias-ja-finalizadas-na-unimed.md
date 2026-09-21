---
titulo: Descobrir quais guias a Unimed já dava por finalizadas
tipo: novidade
data: 2026-09-22
---

A automação de finalização nasceu ontem. As guias que a clínica encerrou no portal da Unimed **antes dela** — à mão, ao longo de meses — continuavam aparecendo aqui como se ainda houvesse trabalho a fazer. A operadora enxergava uma guia concluída e o Gescon enxergava a mesma guia pendente.

Agora dá para perguntar ao portal. Em **Guias**, o botão **"Conferir na Unimed"** procura a guia entre os *Exames finalizados* do portal; **"Conferir finalizadas na Unimed"**, no topo da tela, faz isso para todas as guias Unimed ainda não conferidas de uma vez. A conferência é uma pergunta: **nada é alterado no portal.**

A guia encontrada ganha o selo verde **"Finalizada na operadora"**, com a data da conferência, ao lado do status.

## O que o selo NÃO faz

Isto é o mais importante de entender, e é deliberado:

- **O selo não muda o status da guia.** Ela continua Autorizada, Em análise, ou o que estiver. São duas informações diferentes: o status conta o ciclo da guia **neste sistema**, com as sessões lançadas aqui; o selo conta o que o **portal** respondeu sobre uma guia que talvez nunca tenha passado por esse ciclo.
- **O selo não cria as sessões que faltam.** Ele diz que a guia foi encerrada na operadora, não inventa o histórico que nunca foi digitado.
- **O selo não mexe em cota, antecipação, conciliação ou relatório.** Nada que hoje depende do status passa a depender dele.

Se a guia virasse "Finalizada" de verdade, duas coisas ruins aconteceriam: ela passaria a **aceitar lançamento de sessão** (guia finalizada aceita, no fluxo normal), e a diferença entre "guia que percorreu o ciclo aqui" e "guia encerrada lá atrás, sem registro nenhum" se perderia para sempre.

## Onde isso aparece

- **Em Guias**, ao lado do status. O botão **"Mostrar finalizadas na operadora"** filtra só essas.
- **No detalhe da guia**, separado do status.
- **Em Solicitações**, o item cuja guia está finalizada na operadora aparece **recolhido**: uma linha com a especialidade, o número da guia e o selo, sem os botões de ação. Um clique expande. Numa solicitação antiga, com tudo já resolvido no portal, essas ações eram ruído que competia por atenção com os itens que ainda pedem trabalho.

## Duas coisas úteis de saber

**Conferência que não acha a guia também fica registrada.** Ela não ganha o selo, mas a data da conferência é guardada — é assim que a próxima varredura em lote sabe o que já foi olhado, e é assim que você vê que a guia foi conferida e não está lá.

**Guia já marcada não entra na finalização.** Pedir ao robô que finalize o que o portal já deu por finalizado não faria sentido, e o botão "Finalizar na Unimed" avisa isso em vez de tentar.
