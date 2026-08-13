import { useRef, useState } from 'react'
import { getHttpErrorMessage, useLerCarteirinha } from './usePacientes'
import type { LeituraCarteirinha } from './types'

/**
 * Botão de leitura da carteirinha.
 *
 * No celular abre a câmera direto (`capture`), no computador cai no seletor de
 * arquivo — é o mesmo input, e o atributo é ignorado onde não faz sentido.
 *
 * Nada é gravado aqui: a leitura preenche o formulário e quem confere é o
 * operador, com a carteirinha na mão.
 */
export function LerCarteirinha({
  onLeitura,
}: {
  onLeitura: (leitura: LeituraCarteirinha) => void
}) {
  const inputRef = useRef<HTMLInputElement | null>(null)
  const [erro, setErro] = useState<string | null>(null)
  const [aviso, setAviso] = useState<string | null>(null)
  const ler = useLerCarteirinha()

  const selecionar = async (arquivo: File | undefined) => {
    if (!arquivo) {
      return
    }

    setErro(null)
    setAviso(null)

    try {
      const leitura = await ler.mutateAsync(arquivo)
      const { dados, convenio } = leitura

      const nadaLido =
        !dados.carteirinha && !dados.nome && !dados.cpf && !dados.data_nascimento

      if (nadaLido) {
        setAviso('Não consegui reconhecer nada nesta imagem. Tente uma foto mais nítida e enquadrada.')
      } else if (convenio.lido && !convenio.id) {
        // O convênio manda no formato da carteirinha e no valor pago: preencher
        // por semelhança seria pior que deixar em branco.
        setAviso(`Li a operadora "${convenio.lido}", mas ela não corresponde a nenhum convênio cadastrado. Escolha o convênio à mão.`)
      }

      onLeitura(leitura)
    } catch (error) {
      setErro(getHttpErrorMessage(error, 'Não foi possível ler a carteirinha.'))
    } finally {
      // Sem isso, escolher o mesmo arquivo de novo não dispara o evento.
      if (inputRef.current) {
        inputRef.current.value = ''
      }
    }
  }

  return (
    <div className="space-y-3">
      <div className="flex flex-wrap items-center gap-3">
        <button
          type="button"
          onClick={() => inputRef.current?.click()}
          disabled={ler.isPending}
          className="inline-flex items-center gap-2 rounded-2xl bg-cyan-400 px-4 py-3 text-sm font-semibold text-slate-950 transition hover:bg-cyan-300 disabled:opacity-60"
          data-testid="paciente-ler-carteirinha"
        >
          <svg aria-hidden="true" viewBox="0 0 20 20" className="h-4 w-4">
            <rect x="1.5" y="4" width="17" height="12" rx="2" fill="none" stroke="currentColor" strokeWidth="1.6" />
            <circle cx="6.5" cy="9" r="1.8" fill="none" stroke="currentColor" strokeWidth="1.6" />
            <path d="M11 8.5h5M11 12h5M3 13.5c1-1.6 2.4-1.6 3.5-1.6s2.5 0 3.5 1.6" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" />
          </svg>
          {ler.isPending ? 'Lendo carteirinha...' : 'Ler Carteirinha'}
        </button>

        <span className="text-xs text-slate-400">
          Foto ou arquivo do cartão. Os dados lidos vêm para conferência antes de salvar.
        </span>
      </div>

      <input
        ref={inputRef}
        type="file"
        accept="image/*,application/pdf"
        capture="environment"
        onChange={(event) => selecionar(event.target.files?.[0])}
        className="hidden"
        data-testid="paciente-carteirinha-arquivo"
      />

      {aviso ? (
        <p className="rounded-2xl border border-amber-300/20 bg-amber-400/10 px-4 py-3 text-sm text-amber-100">
          {aviso}
        </p>
      ) : null}

      {erro ? (
        <p className="rounded-2xl border border-rose-400/20 bg-rose-500/10 px-4 py-3 text-sm text-rose-100">
          {erro}
        </p>
      ) : null}
    </div>
  )
}
