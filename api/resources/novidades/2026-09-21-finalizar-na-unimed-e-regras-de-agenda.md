---
titulo: Finalizar a guia na Unimed pelo sistema, com as sessões conferidas antes
tipo: novidade
data: 2026-09-21
---

Finalizar uma guia na Unimed era o último passo do ciclo e continuava sendo digitado à mão: achar a guia no portal, conferir regime e tipo de atendimento, digitar até dez datas de sessão uma a uma, anexar a folha assinada e clicar em Gravar e Finalizar. **Agora o robô faz isso.**

Em **Sessões**, no grupo de cada guia, guia de convênio Unimed passa a mostrar o botão **"Finalizar na Unimed"** no lugar do Finalizar manual. O robô busca a guia no portal, fixa regime *Ambulatorial* e tipo *Outras Terapias*, preenche as datas das sessões registradas aqui — da mais antiga para a mais recente —, anexa as folhas e finaliza.

**Guia Unimed só fica finalizada no Gescon quando a operadora aceitar.** Essa é a mudança de fundo: antes dava para marcar a guia como finalizada aqui sem ter finalizado lá, e o portal continuava esperando. Convênio sem automação continua com a finalização manual de senha e validade, como sempre.

## O sistema pergunta antes de encerrar

Finalizar na operadora não tem desfazer, então tudo o que depende de decisão aparece **antes** de o robô abrir o portal:

- **Menos sessões do que o autorizado?** O sistema mostra as duas quantidades e pede que você confirme que a guia deve ser encerrada com menos.
- **Mais sessões do que o autorizado?** Você escolhe entre enviar apenas as mais antigas, até o limite autorizado, ou parar e revisar.
- **Sem folha de registro anexada?** Avisa — e avisa mais forte quando a carteirinha é da regional 0220, que exige a folha — e pede confirmação explícita.

## Começa em modo simulação

Nas primeiras vezes, o robô **percorre o portal inteiro e para antes de Gravar e Finalizar**, guardando uma foto da tela preenchida. Serve para conferir, com os próprios olhos, o que teria sido enviado — a Unimed não tem ambiente de teste, então a primeira execução real seria contra uma guia de um paciente de verdade.

O painel do botão diz, em amarelo, quando a simulação está ligada, e a guia **não** muda de situação nessas execuções. Depois de conferir, é só desligar em Configurações.

No detalhe da guia há uma seção **"Finalizações na Unimed"** com cada tentativa: quando foi, o resultado e, havendo falha, o motivo. Execução em simulação aparece marcada como tal — ela se parece com um sucesso em tudo, e confundir as duas faria alguém dar por encerrada uma guia que o portal continua esperando.

## Sessões conferidas antes de ir para a operadora

O portal exige pelo menos **50 minutos entre o início de uma sessão e o da seguinte**, e até agora nada impedia gravar duas no mesmo horário. O erro só apareceria na hora de finalizar — ou, pior, depois dela.

Ao registrar sessões, o sistema passa a conferir três coisas, enquanto você digita:

- **Cinquenta minutos entre os inícios** de duas sessões do mesmo paciente.
- **Oito sessões por dia** na mesma especialidade quando é terapia ABA, **uma por dia** quando não é.
- **Choque com sessões já gravadas em outras guias do paciente** — não só com as linhas da folha que está sendo lançada.

A linha em conflito fica marcada na grade, com o motivo e a guia contra a qual bateu, e o botão de registrar espera a correção. **Conflito de agenda não tem "confirmar assim mesmo"**: diferente da divergência de paciente, aqui não há caso legítimo a preservar — o horário está errado de um dos dois lados, e enviar assim leva o erro para a operadora.

Um aviso separado, **que não bloqueia**, mostra quando o executante já tem sessão com outro paciente naquele horário. Quem está com a folha na mão raramente pode resolver a agenda alheia, e o erro pode estar do outro lado.

As regras valem para o que for gravado de agora em diante. Para o que já está no sistema, um card novo no dashboard, em Guias, mostra **"Sessões em conflito"** quando houver — e leva à lista dessas guias. Ele só aparece quando existe algo a corrigir.

## Uma guia, várias folhas

Guia de dez sessões costuma ser impressa em duas vias e preenchida em partes. O sistema aceitava uma folha por remessa; **agora aceita quantas forem**, enviadas de uma vez na confirmação das sessões ou anexadas depois, no detalhe da guia, em "Folhas de registro".

Todas as folhas da guia vão para o portal na finalização, uma por vez. Depois que a guia é finalizada na operadora, as folhas não podem mais ser removidas: elas passam a ser o comprovante do que foi enviado.
