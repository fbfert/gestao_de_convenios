import type { ReactNode } from 'react'

/**
 * A caixa de todo gráfico: título, altura fixa e os dois estados que não podem
 * faltar.
 *
 * **Carregando** desenha um esqueleto, e não um gráfico vazio: gráfico vazio
 * durante a consulta é indistinguível de "não houve nada no período", e a
 * pessoa tira a conclusão errada antes de o número chegar.
 *
 * **Sem dado** diz isso em palavras. A alternativa — eixos desenhados com a
 * linha no zero — afirma que a medição aconteceu e deu zero, que é outra coisa.
 *
 * A altura é fixa para o layout não pular quando a resposta chega. Recharts
 * precisa de altura definida no pai de qualquer forma: dentro de um contêiner
 * de altura automática, o `ResponsiveContainer` colapsa para zero.
 */
export function MolduraGrafico({
  titulo,
  descricao,
  carregando = false,
  vazio = false,
  altura = 'h-72',
  testId,
  children,
}: {
  titulo: string
  descricao?: string
  carregando?: boolean
  vazio?: boolean
  /** Classe de altura do miolo. `h-72` serve para quase tudo; barras horizontais longas pedem mais. */
  altura?: string
  testId?: string
  children: ReactNode
}) {
  return (
    <section
      className="rounded-janela border border-linha bg-superficie p-4 shadow-e1"
      data-testid={testId}
    >
      <header className="mb-3">
        <h3 className="text-subtitulo font-semibold text-texto">{titulo}</h3>
        {descricao ? <p className="text-meta text-texto-suave">{descricao}</p> : null}
      </header>

      <div className={altura}>
        {carregando ? (
          <div
            className="h-full w-full animate-pulse rounded-superficie bg-neutro-desativado"
            aria-label="Carregando gráfico"
            data-testid={testId ? `${testId}-carregando` : undefined}
          />
        ) : vazio ? (
          <div
            className="flex h-full items-center justify-center rounded-superficie border border-dashed border-linha"
            data-testid={testId ? `${testId}-vazio` : undefined}
          >
            <p className="text-corpo text-texto-suave">Sem dados no período</p>
          </div>
        ) : (
          children
        )}
      </div>
    </section>
  )
}
