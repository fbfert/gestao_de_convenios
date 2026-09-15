---
titulo: Convênios e credenciais, no lugar da aba Unimed RDA
tipo: melhoria
data: 2026-09-15
---

Em Configurações, a aba **Unimed RDA** deu lugar a **Convênios e credenciais**. A diferença não é o nome: antes cabia uma credencial de automação só, para a clínica inteira, e o convênio era descoberto por dedução. Agora **cada convênio tem a sua**.

A consequência prática aparece quando algo dá errado. Quando a automação de um convênio falha de forma que exige atenção, o sistema pausa **só aquele convênio** — os demais seguem trabalhando. Antes, uma falha isolada parava tudo, e era preciso reativar à mão. A tela mostra a pausa com o motivo e um botão para retomar aquele convênio.

Dois selos no alto de cada convênio dizem coisas diferentes, de propósito:

- **Automação ligada / Fluxo manual** — se o convênio entra em automação, decidido no cadastro de Convênios.
- **Credencial pronta / incompleta** — se a credencial está preenchida e ativa.

Cadastrar credencial **não liga a automação**. Dá para guardar o acesso de um convênio que ainda opera no manual, sem que isso mude o fluxo das guias dele.

O formulário de cada convênio é montado a partir do que aquele fornecedor exige — quem não tem a forma de acesso definida ainda aparece com um aviso, em vez de campos inventados. Senha nunca volta preenchida na tela: deixe em branco para manter a que já está gravada.

## Os de-para ficaram mais claros

Os quadros **Especialidade × Convênio** e **Profissional × Convênio** foram reorganizados. O que já está configurado virou tabela, com colunas de verdade; o formulário só aparece ao acrescentar ou editar, mostrando o nome do que está sendo alterado.

Todo campo ganhou rótulo fixo e uma dica ao lado, explicando o que é **e o que acontece se ficar errado**. Os campos de descrição só aparecem nos procedimentos de **item genérico**, que são os únicos em que a operadora os exige — e a tabela marca com um aviso as linhas em que essa descrição está faltando, que é justamente a configuração que faz a operadora recusar a guia.
