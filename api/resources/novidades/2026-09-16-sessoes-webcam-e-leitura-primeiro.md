---
titulo: Lançar sessões pela webcam, lendo a folha antes de escolher a guia
tipo: melhoria
data: 2026-09-16
---

Em **Sessões → Nova**, o botão "Ler foto ou PDF do registro" abria a câmera no celular, mas no computador virava só um seletor de arquivo. Como a recepção trabalha no computador, lançar as sessões de uma folha que está na mesa passava por: fotografar no celular, mandar para si mesmo, baixar — com a webcam ligada ali do lado.

**Agora existe o botão "Usar webcam".** Ele aparece quando o navegador permite o acesso à câmera. Você enquadra a folha, tira a foto, **confere na tela** e só então envia para a IA. Uma foto tremida ou cortada custaria a chamada e a espera até descobrir que não deu — por isso a foto congela antes, com "Usar esta foto" e "Tirar outra".

## A ordem mudou: leia primeiro, escolha depois

Antes era preciso escolher a guia e o executante **para só então** liberar a leitura. A ordem estava invertida: quem chega com a folha na mão tem os dados na folha — ela traz o número da guia, o paciente e o número do cartão. Procurar a guia antes era fazer à mão exatamente o que a IA leria em seguida.

**Os dois caminhos de leitura — foto/PDF e webcam — agora liberam com a tela recém-aberta.** E a leitura passa a preencher a escolha:

- Reconhecendo o número da guia, o sistema **procura e escolhe a guia sozinho**, avisando na tela que a escolha veio da leitura.
- Não encontrando, ou encontrando mais de uma, **abre a busca de guia já preenchida** com o número lido, para você escolher em um clique.
- Não reconhecendo o número, deixa a escolha com você e não tenta adivinhar por outro dado.

Confirmar as sessões continua exigindo guia e executante. O que mudou é a ordem de descobrir, não o que é obrigatório para gravar.

**O nome do executante lido aparece, mas não preenche o campo.** A folha é manuscrita, e lançar no nome de quem não atende aquela especialidade gera glosa na conciliação. O sistema mostra "a folha diz: Fulano" ao lado do campo, e a escolha continua sua.

## A folha e a guia precisam contar a mesma história

Escolher a guia pelo número lido resolveu o trabalho manual e criou um risco novo: um dígito lido errado raramente cai no vazio — cai em **outra guia real**, de outro paciente. As sessões entrariam na cota de quem não foi atendido, e o erro só apareceria na conciliação.

Por isso o sistema agora **confere o paciente**: compara o nome e o número do cartão da folha com os do paciente da guia.

- Fechando, a guia é escolhida normalmente.
- **Não fechando, o sistema não escolhe** — abre a busca dizendo o que não bateu.
- Se você escolher essa guia mesmo assim, um **aviso fica fixo na tela** nomeando os dois lados: "a folha diz X, a guia é de Y".
- E ao confirmar as sessões, aparece uma **janela pedindo o motivo por escrito**. Divergir não bloqueia — paciente que trocou de nome, cartão reemitido e folha com cabeçalho antigo são casos legítimos —, mas a decisão fica registrada na auditoria com o seu nome e a data.

A comparação é propositalmente tolerante: nome abreviado, sem acento, com sobrenome acrescentado, e cartão lido pela metade continuam conferindo. **Basta um dos dois identificadores confirmar.** Um aviso que dispara à toa ensina a clicar sem ler — e aí deixa de proteger no dia em que a divergência é real.

> **Se a webcam não aparecer:** o navegador só libera a câmera em endereço seguro (`https://`). No endereço de produção funciona; abrindo o sistema por um endereço `http://` da rede interna, o botão não aparece.
