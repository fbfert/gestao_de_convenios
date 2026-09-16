import { Dialog, DialogBackdrop, DialogPanel, DialogTitle } from '@headlessui/react'
import { useEffect, useState } from 'react'
import { X } from 'lucide-react'
import { Botao } from '../../components/ui/Botao'
import { useFechamentoExplicito } from '../../lib/useFechamentoExplicito'
import type { ConferenciaDaFolha } from './conferenciaDaFolha'

type ConfirmarDivergenciaModalProps = {
  /** Aberto quando há divergência a confirmar; `null` fecha. */
  conferencia: ConferenciaDaFolha | null
  descricao: string
  onCancelar: () => void
  onConfirmar: (justificativa: string) => void
  enviando: boolean
}

/** Justificativa curta demais não explica nada — e é o que alguém digita para passar da tela. */
const MINIMO_JUSTIFICATIVA = 10

/**
 * Última parada antes de lançar sessões numa guia que contradiz a folha.
 *
 * Não é um "tem certeza?". Um diálogo de sim/não sob divergência vira reflexo
 * em uma semana e para de proteger; escrever o motivo obriga a olhar o
 * conflito, e deixa na auditoria por que alguém decidiu assim — que é o que
 * falta quando a cota de um paciente aparece consumida meses depois.
 *
 * Divergir não bloqueia: paciente que trocou de nome, cartão reemitido e folha
 * com cabeçalho antigo são motivos legítimos. Quem tem a folha na mão decide;
 * o sistema só impede que a decisão passe calada.
 */
export function ConfirmarDivergenciaModal({
  conferencia,
  descricao,
  onCancelar,
  onConfirmar,
  enviando,
}: ConfirmarDivergenciaModalProps) {
  const [justificativa, setJustificativa] = useState('')
  const [erro, setErro] = useState<string | null>(null)
  const fechamento = useFechamentoExplicito(conferencia !== null, onCancelar)

  useEffect(() => {
    if (!conferencia) return

    setJustificativa('')
    setErro(null)
  }, [conferencia])

  if (!conferencia) {
    return null
  }

  const confirmar = () => {
    if (justificativa.trim().length < MINIMO_JUSTIFICATIVA) {
      setErro(`Escreva o motivo com pelo menos ${MINIMO_JUSTIFICATIVA} caracteres.`)

      return
    }

    onConfirmar(justificativa.trim())
  }

  return (
    <Dialog {...fechamento} className="relative z-(--z-dialogo)">
      <DialogBackdrop className="fixed inset-0 bg-slate-950/75 backdrop-blur-sm" />
      <div className="fixed inset-0 overflow-y-auto p-4 sm:p-6">
        <div className="flex min-h-full items-center justify-center">
          <DialogPanel
            className="w-full max-w-lg rounded-janela border border-white/10 bg-slate-950 p-6 text-white shadow-e3 shadow-black/60"
            data-testid="confirmar-divergencia-modal"
          >
            <div className="flex items-start justify-between gap-4">
              <DialogTitle className="text-titulo font-semibold">
                A folha não confere com a guia
              </DialogTitle>
              <button
                type="button"
                onClick={onCancelar}
                className="rounded-full border border-white/10 bg-white/5 p-2 text-white transition hover:bg-white/10"
                aria-label="Fechar"
              >
                <X className="size-4" aria-hidden="true" />
              </button>
            </div>

            <p
              className="mt-4 rounded-2xl border border-amber-300/30 bg-amber-400/10 px-4 py-3 text-corpo text-amber-100"
              data-testid="confirmar-divergencia-descricao"
            >
              {descricao}
            </p>

            <p className="mt-4 text-corpo text-slate-300">
              Lançar assim consome a cota da guia escolhida. Se ela for de outro paciente, as
              sessões entram na conta de quem não foi atendido — e o erro costuma aparecer só na
              conciliação.
            </p>

            <label className="mt-4 block space-y-2">
              <span className="text-meta uppercase tracking-[0.25em] text-slate-400">
                Por que lançar mesmo assim?
              </span>
              <textarea
                value={justificativa}
                onChange={(evento) => {
                  setJustificativa(evento.target.value)
                  setErro(null)
                }}
                rows={3}
                placeholder="Ex.: paciente trocou de nome após casamento; carteirinha reemitida em agosto."
                className="w-full rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-corpo text-white outline-none transition focus:border-cyan-300/70 focus:ring-2 focus:ring-cyan-300/20"
                data-testid="confirmar-divergencia-justificativa"
              />
              <span className="block text-meta text-slate-400">
                Fica registrado na auditoria com o seu nome e a data.
              </span>
            </label>

            {erro ? (
              <p
                className="mt-4 rounded-2xl border border-rose-400/20 bg-rose-500/10 px-4 py-3 text-corpo text-rose-100"
                data-testid="confirmar-divergencia-erro"
              >
                {erro}
              </p>
            ) : null}

            <div className="mt-6 flex flex-wrap justify-end gap-3">
              <Botao
                type="button"
                variante="secundario"
                onClick={onCancelar}
                data-testid="confirmar-divergencia-cancelar"
              >
                Cancelar e conferir
              </Botao>
              <Botao
                type="button"
                variante="perigo"
                disabled={enviando}
                onClick={confirmar}
                data-testid="confirmar-divergencia-confirmar"
              >
                {enviando ? 'Lançando...' : 'Lançar mesmo assim'}
              </Botao>
            </div>
          </DialogPanel>
        </div>
      </div>
    </Dialog>
  )
}
