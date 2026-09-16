---
titulo: Antecipações: Ignorar agora pergunta antes, dá para desfazer, e o histórico tem busca
tipo: melhoria
data: 2026-09-16
---

**Ignorar era um clique só, e sem volta.** O botão fica colado no "Gerar", e errar o alvo tirava a solicitação da lista de elegíveis para sempre — sem aviso e sem caminho de retorno. Era o ciclo inteiro do paciente perdido por um clique torto.

Agora **Ignorar abre uma confirmação**, mostrando paciente, convênio e a data prevista, com um campo opcional para você escrever o motivo. O motivo fica guardado e aparece depois no histórico.

**E dá para desfazer.** Toda antecipação com status *Ignorada* ganhou um botão **Desfazer**, que também pede confirmação. Desfazer tira o registro do histórico e devolve a solicitação para a lista de elegíveis, se ela ainda estiver na data. Só vale para as ignoradas: uma antecipação **Gerada** já criou itens e guias, e apagar o registro dela deixaria os efeitos de pé sem a prova de quem os criou.

**O histórico virou pesquisável.** Antes só havia o filtro de status, e achar "aquela antecipação da paciente X" era rolar página por página. Agora tem busca por:

- **paciente** — parte do nome basta;
- **número da guia** — encontra também as linhas *Ignoradas*, que não geraram guia nenhuma, pelo número da guia que motivou o aviso;
- **convênio**;
- **período** — De/Até sobre a data em que alguém gerou ou ignorou.

Os critérios combinam entre si, e o botão **Limpar** devolve a lista inteira.

**A busca e a página agora sobrevivem.** Clicar numa guia do histórico e voltar devolvia a página 1, sem filtro nenhum. Os critérios passaram a viver no endereço da página: o "Voltar" do navegador reabre exatamente onde você estava, e o link filtrado pode ser guardado nos favoritos ou mandado para alguém.

**Duas coisas novas para conferir sem sair da tela:**

- No histórico, uma **lupa ao lado de "Paciente · Convênio"** mostra o detalhe da ação: status, quem fez e quando, a solicitação de origem, a data prevista, os itens e guias gerados, e o motivo registrado.
- Nos elegíveis, **clicar no nome do paciente** abre a solicitação de origem — data do pedido, médico, CIDs e cada item com a sua guia e situação. É consulta apenas: fechar não gera nem dispensa nada.
