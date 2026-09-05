import { useEffect, useState, type FormEvent } from 'react'

type PaginacaoProps = {
  page: number
  totalPages: number
  onChange: (page: number) => void
}

/**
 * Rodapé de paginação compartilhado — Anterior/Próxima + "Página X de Y" +
 * campo para ir direto a uma página, usado por toda lista paginada do
 * sistema (ver useListaNaUrl, que guarda a página escolhida na URL).
 */
export function Paginacao({ page, totalPages, onChange }: PaginacaoProps) {
  const [valor, setValor] = useState(String(page))

  useEffect(() => {
    setValor(String(page))
  }, [page])

  const irParaPagina = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    const alvo = Math.min(totalPages, Math.max(1, Math.trunc(Number(valor)) || page))
    onChange(alvo)
  }

  return (
    <div className="mt-5 flex flex-wrap items-center justify-between gap-3">
      <button
        type="button"
        onClick={() => onChange(Math.max(1, page - 1))}
        disabled={page <= 1}
        className="rounded-2xl border border-white/10 bg-white/5 px-4 py-2 text-corpo text-white disabled:opacity-50"
      >
        Anterior
      </button>

      <div className="flex flex-wrap items-center gap-3">
        <p className="inline-flex min-h-6 items-center text-corpo text-slate-300">
          Página {page} de {totalPages}
        </p>

        <form onSubmit={irParaPagina} className="flex items-center gap-2">
          <label className="flex items-center gap-2 text-meta text-slate-400">
            Ir para
            <input
              type="number"
              min={1}
              max={totalPages}
              value={valor}
              onChange={(event) => setValor(event.target.value)}
              className="w-16 rounded-2xl border border-white/10 bg-white/5 px-2 py-1 text-center text-corpo text-white outline-none transition focus:border-cyan-300/70 focus:ring-2 focus:ring-cyan-300/20"
              data-testid="paginacao-ir-para-input"
            />
          </label>
          <button
            type="submit"
            className="rounded-2xl border border-white/10 bg-white/5 px-3 py-1.5 text-meta font-semibold text-white transition hover:bg-white/10"
            data-testid="paginacao-ir-para-submit"
          >
            Ir
          </button>
        </form>
      </div>

      <button
        type="button"
        onClick={() => onChange(Math.min(totalPages, page + 1))}
        disabled={page >= totalPages}
        className="rounded-2xl border border-white/10 bg-white/5 px-4 py-2 text-corpo text-white disabled:opacity-50"
      >
        Próxima
      </button>
    </div>
  )
}
