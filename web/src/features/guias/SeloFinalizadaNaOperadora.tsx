/**
 * "Finalizada na operadora" — a guia que a Unimed já dava por encerrada antes
 * de a automação existir.
 *
 * Não é o status da guia, e por isso não é um `Badge`: os dois aparecem lado a
 * lado e dizem coisas diferentes. O status conta o ciclo da guia NESTE sistema
 * (com as sessões lançadas aqui); o selo conta o que o portal respondeu sobre
 * uma guia que talvez nunca tenha passado por esse ciclo.
 *
 * O contorno é o que separa os dois visualmente: o badge de status é sólido, o
 * selo é contornado. Usar os tokens de `sucesso` em vez de inventar um verde
 * novo é deliberado — a paleta é semântica e cobre três temas (claro, escuro e
 * alto contraste), e uma família de token para um único selo sairia cara em
 * todos eles.
 *
 * A data anda junto: o selo afirma o que o portal disse NUM MOMENTO, e sem ela
 * ninguém sabe quando isso foi verdade.
 */

function formatarData(iso: string | null): string | null {
  if (!iso) {
    return null
  }

  const data = new Date(iso)

  return Number.isNaN(data.getTime()) ? null : data.toLocaleDateString('pt-BR')
}

export function SeloFinalizadaNaOperadora({
  finalizadaEm,
  conferidaEm,
  testId,
}: {
  finalizadaEm: string | null | undefined
  conferidaEm?: string | null
  testId?: string
}) {
  if (!finalizadaEm) {
    return null
  }

  // A data que interessa é a da conferência que produziu o selo; na falta
  // dela, a da própria marca.
  const quando = formatarData(conferidaEm ?? null) ?? formatarData(finalizadaEm)

  return (
    <span
      /*
       * A borda usa o token de TEXTO de propósito.
       *
       * `--sucesso-borda` existe no CSS, mas não é exportado como cor do
       * Tailwind — uma classe com esse nome não geraria estilo nenhum, e o
       * selo renderizaria sem a borda que o distingue do badge de status (foi
       * o que a guarda §11.3 do design system pegou). No tema claro os dois
       * valores são o mesmo; no tema de contraste a própria folha de estilo já
       * aplica uma borda sobre `bg-sucesso-suave`.
       */
      className="inline-flex items-center gap-1 whitespace-nowrap rounded-pilula border border-sucesso-texto/50 bg-sucesso-suave px-2.5 py-1 text-meta font-semibold text-sucesso-texto"
      title={quando ? `Conferido no portal da Unimed em ${quando}` : undefined}
      data-testid={testId ?? 'guia-selo-finalizada-operadora'}
    >
      ✓ Finalizada na operadora
      {quando ? <span className="font-normal opacity-80">· {quando}</span> : null}
    </span>
  )
}
