---
titulo: Tela de erro em vez de tela branca
tipo: correcao
data: 2026-09-22
---

A tela ficava branca "do nada", às vezes no meio de um lançamento. Sem mensagem, sem recarregar, sem saber se o que estava sendo feito chegou a ser salvo.

**Isso acabou.** Quando uma tela falha agora, aparece uma tela de erro que diz o que aconteceu, garante que **os dados no servidor não foram perdidos**, e oferece dois caminhos: recarregar a página ou voltar ao início. "Voltar ao início" funciona na hora, sem recarregar nada.

A tela mostra também um **código curto do erro**. Se acontecer de novo, informe esse código ao suporte: é por ele que encontramos exatamente o que houve.

## E o erro agora deixa rastro

Esta é a parte que não aparece na tela, e é a mais importante para o futuro: **até agora, nenhuma dessas quedas ficava registrada**. Quando a clínica ligava avisando, não havia um único registro para consultar — só a descrição do que a pessoa lembrava.

Agora toda queda da interface é enviada ao servidor e registrada, com o usuário e a clínica quando houver sessão. Erro na tela de login também chega. O envio é limitado de propósito: o mesmo erro não é enviado duas vezes, para um problema em laço não virar enxurrada.

## A causa que já estava identificada

O gatilho mais comum era o **tradutor automático do navegador** (ou uma extensão que faça o mesmo). Ele reescreve o texto de cada botão, e o sistema perdia a referência do rótulo — no clique seguinte em qualquer botão que mostra "carregando" (Sair, Salvar, Finalizar, Enviar, Importar), a página caía.

Em setembro o sistema passou a declarar que não deve ser traduzido, o que resolveu para o tradutor nativo do Chrome e do Edge. **Agora o botão foi corrigido por dentro**, então mesmo uma extensão que reescreva o texto não derruba mais nada.

> **Se você usa extensão de tradução no navegador**, ela não é mais um problema para o sistema. Mas vale saber: o Gescon é escrito em português, e traduzir de português para português costuma piorar a leitura.
