---
titulo: Antecipação repensada, e sessões liberadas assim que a guia é aprovada
tipo: melhoria
data: 2026-09-10
---

Duas mudanças ligadas, na forma como o sistema trata guia aprovada e o ciclo seguinte de tratamento.

**Lançar sessão não espera mais o Finalizar.** Antes, o CRUD de sessões só abria depois de clicar Finalizar na guia. Agora, toda guia **Autorizada** já aceita lançamento — Finalizar continua existindo, mas virou só o registro de senha e validade, sem efeito nenhum sobre lançar sessão. A cota de sessões (quantas ainda cabem) é contada ao vivo a partir de `sessões autorizadas`, sem depender de nenhum cadastro extra.

**Antecipação virou um alerta, não um cadastro.** A antiga tela de Antecipações (com cota por ciclo) saiu de circulação. No lugar, a Central de Alertas ganhou uma quinta situação vigiada: **Antecipação devida** — dispara quando uma guia aprovada está perto da validade da senha (ou da data de finalização, à sua escolha) e ainda não tem sucessora. O alerta nunca gera nem envia nada sozinho: ele só convida a revisar, com um botão **Nova Solicitação** que já vem preenchido com médico, CIDs e todos os itens (especialidade + profissional) do ciclo anterior — só conferir e confirmar. Também dá pra ocultar o alerta de uma guia específica, igual já funciona hoje para Guia negada.

O prazo de antecedência é configurável em dois níveis: um padrão para todo o tenant, em **Configurações → Globais**, e um valor por convênio, na própria tela de edição do convênio — que sobrescreve o padrão quando preenchido. Também é possível travar manualmente a data de antecipação de uma guia específica, direto na tela da guia, quando o automático não se aplica àquele caso.
