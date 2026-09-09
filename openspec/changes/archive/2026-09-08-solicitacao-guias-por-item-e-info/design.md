## Context

A multi-especialidade mudou a cardinalidade — cada `SolicitacaoItem` tem a sua guia — mas duas superfícies ficaram com a suposição antiga de uma guia por solicitação: o modal de detalhes e a listagem.

A listagem já carrega, por eager load, tudo que a coluna Info precisa. O custo desta entrega é de apresentação, não de consulta.

## Goals / Non-Goals

**Goals:**
- A tela deixa de mentir sobre quantas guias existem.
- O número exibido é o que a operadora reconhece, ou uma frase honesta quando não há número.
- Informação de apoio (datas, observações, anexos, CID) alcançável sem abrir o registro.
- Zero consulta nova.

**Non-Goals:**
- Remover a relação legada.
- Alterar o `GuiaDetalheResumo`.
- Mudar a geração de guias.

## Decisions

- **A fonte das guias passa a ser `itens[].guia`, e a relação legada é abandonada apenas no consumo.** Racional: o defeito não é "falta aba", é a fonte estar errada — `hasOne` sem ordenação devolve uma guia arbitrária. Trocar a fonte corrige o modal e, de quebra, torna as abas triviais. A relação continua existindo porque outro arquivo ainda depende dela.

- **Uma aba por ITEM, e não por guia.** Racional: a informação mais valiosa é justamente a ausência — ver que saíram 1 de 3 especialidades. Abas só para as guias existentes esconderiam exatamente o que exige ação.

- **O rótulo da aba é especialidade + número.** Racional: "Guia 1 / Guia 2" numera a posição na tela, que não é um dado do mundo; quem opera procura pela especialidade e confere pelo número.

- **O prefixo `GUIA-SOLICITACAO-` é tratado como AUSÊNCIA de número, por constante compartilhada.** Racional: é o ponto onde o change pode falhar em silêncio. Trocar o id interno pelo placeholder substituiria um número errado por outro — os dois igualmente inúteis num telefonema para a operadora. A constante vem do backend para o front não repetir string solta; string solta duplicada é como a regra se perde na terceira tela que precisar dela.

- **Sem número, o texto afirma que a guia existe.** "Guia gerada · sem nº da operadora" e "Guia gerada · nº pendente". Racional: a leitura errada mais cara aqui é "a guia falhou" — ela levaria alguém a gerar a guia de novo.

- **O badge passa a ser o status real, traduzido, para qualquer convênio.** Racional: "Guia gerada" ao lado do número é redundante (se tem guia, foi gerada) e some para convênio manual, que é metade dos casos. O status diz o que ainda precisa acontecer. A tradução usa o mapa central (`translateStatus`), que já cobre o prefixo `historico_`.

- **Os quatro ícones da coluna Info aparecem sempre; o vazio fica apagado, `aria-hidden` e não focável.** Racional: posição fixa é o que torna a coluna legível de relance — ícone que aparece e some obriga a reler a linha. E quatro botões focáveis por linha, em quinze linhas, seriam sessenta paradas de Tab entre a tabela e a paginação; um ícone sem conteúdo não tem o que anunciar a um leitor de tela.

- **O ícone de CID não é uma cruz vermelha.** Racional: neste sistema vermelho significa perigo ou negado — é a cor do alerta de guia negada. Uma cruz vermelha em toda linha faria o olho ler "algo errado aqui" em quinze linhas saudáveis. Some-se o ADR-23: o tema de alto contraste existe por requisito real de acessibilidade, e cor não pode carregar significado sozinha.

- **O tooltip do calendário mostra as três datas.** Racional: `solicitado_em` é a data do pedido médico e `created_at` é quando alguém digitou no sistema. Sem as duas, ninguém sabe há quanto tempo um pedido está parado *dentro* do sistema — que é a pergunta operacional real.

- **`solicitado_em` sai do tooltip e vira linha secundária sob o paciente.** Racional: é o dado mais consultado da linha, e exigir hover para o mais consultado é caro.

## Risks / Trade-offs

- **[O placeholder vazar para a tela como se fosse número]** -> É o risco central, e a defesa é a constante compartilhada mais um caso de aceite explícito. Um teste que exercite convênio manual é o que impede a regressão silenciosa.

- **[A coluna Info trazer N+1]** -> Os dados vêm de `cidCadastros`, `documentos.arquivo` e `itens.documentos.arquivo`, já presentes no eager load. A garantia não é a leitura do código e sim o teste de contagem de consultas: sem ele, o primeiro campo novo que alguém adicionar reabre o problema sem que ninguém perceba.

- **[Abandonar `solicitacao.guia` no modal, mas mantê-la no payload]** -> Fica uma relação carregada que quase ninguém lê, e o custo dela são os oito eager loads registrados como não-objetivo. Aceito por ora: removê-la agora exigiria mexer na exclusão de anexos, que é outro fluxo e outro risco.

- **[Sete listas de eager load duplicadas]** -> Descoberto na conferência. Não é criado por este change nem piorado por ele, mas significa que a próxima pessoa que precisar de um campo novo terá de lembrar de sete lugares. Registrado no `proposal.md` como candidato a change próprio.
