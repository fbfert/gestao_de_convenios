---
titulo: A tela de Solicitações parou de cair ao passar o mouse na dica
tipo: correcao
data: 2026-09-25
---

Nos dias 24 e 25/09 a tela de Solicitações caiu seis vezes, sempre ao filtrar por paciente e passar o mouse na dica (o "?") da coluna Info. Em vez da página em branco, apareceu a tela de erro com um código — e foi por esse código que o problema foi encontrado.

**O que era:** a dica media o próprio tamanho para caber na tela e, em certas condições (zoom do navegador ou a escala de tela do Windows em 125%), a medição nunca dava o mesmo número duas vezes. A tela ficava recalculando para sempre, até o sistema desistir.

**Corrigido.** A dica agora mede uma vez só ao abrir. Nada muda na aparência: ela continua cabendo na tela, como antes.

## Por que vale registrar

Foi a primeira vez que o caminho inteiro funcionou: você viu um código na tela em vez de uma página branca, o registro chegou ao servidor com a mensagem e o componente exato, e a correção saiu dali. Se acontecer de novo em outra tela, é o mesmo caminho — anote o código.
