import { Dialog, DialogBackdrop, DialogPanel, DialogTitle } from '@headlessui/react'
import { useState } from 'react'
import { Link } from 'react-router-dom'
import { LoaderCircle } from 'lucide-react'
import { Botao } from '../../components/ui/Botao'
import { usePode } from '../../lib/permissoes'
import { getHttpErrorMessage } from '../../lib/httpError'
import { useReativarUnimed, useUnimedSettings } from './useUnimedSettings'

export type AutomacaoUnimedDesativadaModalProps = {
  aberto: boolean
  onClose: () => void
  /** Chamado depois que "Ativar agora" reativa a automação com sucesso. */
  onAtivada: () => void
}

export function AutomacaoUnimedDesativadaModal({
  aberto,
  onClose,
  onAtivada,
}: AutomacaoUnimedDesativadaModalProps) {
  const pode = usePode()
  const canManageUnimed = pode('configuracoes.unimed.manage')
  // GET /configuracoes/unimed também exige essa permissão — sem ela nem
  // tenta buscar, pra não estourar 403 à toa.
  const settingsQuery = useUnimedSettings({ enabled: aberto && canManageUnimed })
  const reativar = useReativarUnimed()
  const [erroAtivar, setErroAtivar] = useState<string | null>(null)

  const credential = settingsQuery.data?.credential ?? null
  const existeCredencial = Boolean(credential)

  const handleAtivar = async () => {
    setErroAtivar(null)

    try {
      await reativar.mutateAsync()
      onAtivada()
    } catch (error) {
      setErroAtivar(getHttpErrorMessage(error, 'Não foi possível ativar a automação.'))
    }
  }

  const handleClose = () => {
    setErroAtivar(null)
    onClose()
  }

  return (
    <Dialog open={aberto} onClose={handleClose} className="relative z-(--z-dialogo)">
      <DialogBackdrop className="fixed inset-0 bg-slate-950/75 backdrop-blur-sm" />
      <div className="fixed inset-0 overflow-y-auto p-4 sm:p-6">
        <div className="flex min-h-full items-center justify-center">
          <DialogPanel
            className="w-full max-w-md rounded-janela border border-white/10 bg-slate-950 p-6 text-white shadow-e3 shadow-black/60"
            data-testid="automacao-unimed-desativada-modal"
          >
            <DialogTitle className="text-titulo font-semibold">
              Automação da Unimed desativada
            </DialogTitle>

            <div className="mt-4 space-y-4 text-corpo text-slate-300">
              {!canManageUnimed ? (
                <p data-testid="automacao-unimed-desativada-sem-permissao">
                  A automação da Unimed está desativada no momento. Peça para um administrador
                  reativá-la em Configurações antes de tentar de novo.
                </p>
              ) : settingsQuery.isLoading ? (
                <div className="flex items-center gap-2">
                  <LoaderCircle className="size-4 animate-spin" aria-hidden="true" />
                  Verificando o status da automação...
                </div>
              ) : settingsQuery.isError ? (
                <p>Não foi possível verificar o status da automação. Tente de novo em instantes.</p>
              ) : !existeCredencial ? (
                <>
                  <p data-testid="automacao-unimed-desativada-nao-configurada">
                    A automação da Unimed ainda não foi configurada para esta clínica.
                  </p>
                  <Link
                    to="/configuracoes"
                    className="inline-block font-semibold text-cyan-200 underline decoration-current/40 underline-offset-4"
                  >
                    Ir para Configurações
                  </Link>
                </>
              ) : (
                <>
                  <p data-testid="automacao-unimed-desativada-pausada">
                    A automação da Unimed está desativada
                    {credential?.automation_paused_reason
                      ? ' — foi pausada automaticamente após falhas repetidas no portal'
                      : ''}
                    . Deseja ativá-la agora?
                  </p>
                  {erroAtivar ? (
                    <p className="rounded-2xl border border-rose-400/20 bg-rose-500/10 px-4 py-3 text-rose-100">
                      {erroAtivar}
                    </p>
                  ) : null}
                </>
              )}
            </div>

            <div className="mt-6 flex flex-wrap justify-end gap-3">
              <Botao type="button" variante="secundario" onClick={handleClose}>
                Fechar
              </Botao>
              {canManageUnimed && !settingsQuery.isLoading && existeCredencial ? (
                <Botao
                  type="button"
                  variante="primario"
                  onClick={handleAtivar}
                  disabled={reativar.isPending}
                  data-testid="automacao-unimed-ativar-agora"
                >
                  {reativar.isPending ? 'Ativando...' : 'Ativar agora'}
                </Botao>
              ) : null}
            </div>
          </DialogPanel>
        </div>
      </div>
    </Dialog>
  )
}
