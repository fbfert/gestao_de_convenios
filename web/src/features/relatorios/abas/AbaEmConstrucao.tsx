/**
 * Aba cujo gráfico ainda não foi desenhado.
 *
 * Os KPIs e as tabelas já aparecem — quem monta é a página, e valem para
 * qualquer aba. O que falta aqui é só a escolha de qual gráfico conta melhor
 * cada história, que é o bloco 5 do tasks.md.
 *
 * Diz isso em vez de mostrar uma área em branco: área em branco numa tela de
 * relatório é indistinguível de "não houve nada no período".
 */
export function AbaEmConstrucao({ nome }: { nome: string }) {
  return (
    <section
      className="rounded-janela border border-dashed border-linha bg-superficie p-8 text-center"
      data-testid="aba-em-construcao"
    >
      <p className="text-corpo-lg font-medium text-texto">Gráficos de {nome} em construção</p>
      <p className="mt-1 text-corpo text-texto-suave">
        Os indicadores e as tabelas desta aba já estão acima e podem ser exportados.
      </p>
    </section>
  )
}
