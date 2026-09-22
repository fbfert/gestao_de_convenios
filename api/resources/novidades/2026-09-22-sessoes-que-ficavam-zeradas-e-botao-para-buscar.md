---
titulo: Guias autorizadas com sessões zeradas — corrigido, com botão pra buscar as que faltaram
tipo: melhoria
data: 2026-09-22
---

Algumas guias Unimed já autorizadas, com senha e validade certinhas, ficavam com **"Nº de Sessões" e "Sessões Autorizadas" zerados** mesmo tendo esse número disponível no portal da operadora. Em alguns casos o robô nem chegava a tentar buscar o dado, porque a guia tinha saído da lista onde ele normalmente procura.

Os dois motivos foram corrigidos, e o robô volta a preencher isso sozinho daqui pra frente. Para as guias que já tinham ficado travadas nesse estado, foi preciso ir buscar o número certo uma a uma no portal.

## Botão "Buscar sessões"

Pra qualquer guia que apareça nesse mesmo estado no futuro — aprovada, com senha e validade, mas sessões em branco — agora existe um botão manual **"Buscar sessões"**, no mesmo lugar onde já existiam "Buscar Senha" e "Buscar Validade": nas colunas de sessões da lista de **Guias**, e na tela de detalhe da guia. Um clique dispara a mesma consulta ao portal que o robô faria sozinho, sem precisar esperar o próximo ciclo automático.
