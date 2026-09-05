import { useState } from 'react'
import { Botao } from '../../components/ui/Botao'
import { useConfirm } from '../../components/ui/ConfirmDialog'
import {
  getHttpErrorMessage,
  useMesclarPacientes,
  usePacientesDuplicados,
  usePreviewMesclagem,
  type MesclarInput,
  type PacienteDuplicado,
} from './usePacientesDuplicados'

function chavePar(par: PacienteDuplicado): string {
  return `${par.paciente_a.id}:${par.paciente_b.id}`
}

function outroLado(par: PacienteDuplicado, vencedorId: number) {
  return par.paciente_a.id === vencedorId ? par.paciente_b : par.paciente_a
}

function CardDuplicado({
  par,
  vencedorId,
  clinicaEscolhida,
  selecionado,
  onEscolherVencedor,
  onEscolherClinica,
  onAlternarSelecao,
}: {
  par: PacienteDuplicado
  vencedorId: number | undefined
  clinicaEscolhida: number | undefined
  selecionado: boolean
  onEscolherVencedor: (id: number) => void
  onEscolherClinica: (clinicaId: number) => void
  onAlternarSelecao: () => void
}) {
  const conflitoClinica =
    par.paciente_a.clinica_id !== null &&
    par.paciente_b.clinica_id !== null &&
    par.paciente_a.clinica_id !== par.paciente_b.clinica_id

  const precisaEscolherClinica = conflitoClinica && vencedorId !== undefined && clinicaEscolhida === undefined
  const pronto = vencedorId !== undefined && (!conflitoClinica || clinicaEscolhida !== undefined)

  return (
    <div className="rounded-superficie border border-linha bg-fundo p-4 shadow-e1" data-testid="paciente-duplicado-card">
      <div className="flex items-start gap-3">
        <input
          type="checkbox"
          checked={selecionado}
          disabled={!pronto}
          onChange={onAlternarSelecao}
          className="mt-1 size-4 rounded border-white/20 bg-white/10 disabled:opacity-30"
          data-testid="paciente-duplicado-checkbox"
        />
        <div className="flex-1 space-y-3">
          <p className="text-meta text-slate-400">{par.similaridade}% parecido</p>

          <div className="flex flex-wrap gap-2">
            {[par.paciente_a, par.paciente_b].map((lado) => (
              <button
                key={lado.id}
                type="button"
                onClick={() => onEscolherVencedor(lado.id)}
                className={`rounded-full border px-3 py-1.5 text-meta font-semibold transition ${
                  vencedorId === lado.id
                    ? 'border-cyan-300/60 bg-cyan-400/25 text-cyan-50'
                    : 'border-cyan-400/30 bg-cyan-400/10 text-cyan-100 hover:bg-cyan-400/20'
                }`}
                data-testid="paciente-duplicado-manter"
              >
                Manter #{lado.id} {lado.nome}
                {lado.carteirinha ? ` · ${lado.carteirinha}` : ''}
                {!lado.ativo ? ' · inativo' : ''}
              </button>
            ))}
          </div>

          {precisaEscolherClinica ? (
            <div className="space-y-1">
              <p className="text-meta text-amber-200">
                Os dois têm vínculo diferente com a clinica — qual mantém?
              </p>
              <div className="flex flex-wrap gap-2">
                {[par.paciente_a, par.paciente_b].map((lado) =>
                  lado.clinica_id !== null ? (
                    <button
                      key={lado.clinica_id}
                      type="button"
                      onClick={() => onEscolherClinica(lado.clinica_id!)}
                      className="rounded-full border border-amber-300/30 bg-amber-400/10 px-3 py-1.5 text-meta font-semibold text-amber-100 transition hover:bg-amber-400/20"
                    >
                      clinica_id {lado.clinica_id} (#{lado.id})
                    </button>
                  ) : null,
                )}
              </div>
            </div>
          ) : null}
        </div>
      </div>
    </div>
  )
}

export function PacientesDuplicadosSecao({ onFechar }: { onFechar: () => void }) {
  const query = usePacientesDuplicados(true)
  const preview = usePreviewMesclagem()
  const mesclar = useMesclarPacientes()
  const confirmar = useConfirm()

  const [vencedores, setVencedores] = useState<Record<string, number>>({})
  const [clinicaEscolhidos, setClinicaEscolhidos] = useState<Record<string, number>>({})
  const [selecionados, setSelecionados] = useState<Set<string>>(new Set())
  const [processando, setProcessando] = useState(false)
  const [erro, setErro] = useState<string | null>(null)

  const pares = query.data ?? []

  const prontoParaSelecao = (par: PacienteDuplicado): boolean => {
    const chave = chavePar(par)
    const vencedorId = vencedores[chave]
    if (vencedorId === undefined) return false

    const conflitoClinica =
      par.paciente_a.clinica_id !== null &&
      par.paciente_b.clinica_id !== null &&
      par.paciente_a.clinica_id !== par.paciente_b.clinica_id

    return !conflitoClinica || clinicaEscolhidos[chave] !== undefined
  }

  const paresProntos = pares.filter(prontoParaSelecao)

  const todasProntasSelecionadas = paresProntos.length > 0 && paresProntos.every((par) => selecionados.has(chavePar(par)))

  const alternarTodas = () => {
    setSelecionados(todasProntasSelecionadas ? new Set() : new Set(paresProntos.map(chavePar)))
  }

  const alternarPar = (chave: string) => {
    setSelecionados((atual) => {
      const proxima = new Set(atual)
      if (proxima.has(chave)) proxima.delete(chave)
      else proxima.add(chave)
      return proxima
    })
  }

  const montarInput = (par: PacienteDuplicado): MesclarInput => {
    const chave = chavePar(par)
    const vencedorId = vencedores[chave]!
    const perdedor = outroLado(par, vencedorId)
    const clinicaEscolhida = clinicaEscolhidos[chave]

    return {
      vencedor_id: vencedorId,
      perdedor_id: perdedor.id,
      ...(clinicaEscolhida !== undefined ? { clinica_id_escolhido: clinicaEscolhida } : {}),
    }
  }

  const unificarSelecionados = async () => {
    setErro(null)
    const selecionadosPares = pares.filter((par) => selecionados.has(chavePar(par)))
    if (selecionadosPares.length === 0) return

    setProcessando(true)
    try {
      const inputs = selecionadosPares.map(montarInput)
      const previews = await Promise.all(inputs.map((input) => preview.mutateAsync(input)))
      const totais = previews.reduce(
        (acc, item) => ({
          solicitacoes: acc.solicitacoes + item.solicitacoes,
          guias: acc.guias + item.guias,
          antecipacoes: acc.antecipacoes + item.antecipacoes,
        }),
        { solicitacoes: 0, guias: 0, antecipacoes: 0 },
      )

      const confirmado = await confirmar({
        titulo: `Unificar ${inputs.length} par(es) de pacientes?`,
        descricao: `Isso vai mover ${totais.solicitacoes} solicitação(ões), ${totais.guias} guia(s) e ${totais.antecipacoes} antecipação(ões) para o cadastro vencedor de cada par. O outro lado fica inativo e marcado como mesclado — nada é apagado.`,
        confirmarTexto: 'Unificar',
        variante: 'perigo',
      })

      if (!confirmado) return

      for (const input of inputs) {
        await mesclar.mutateAsync(input)
      }

      setSelecionados(new Set())
      setVencedores({})
      setClinicaEscolhidos({})
    } catch (error) {
      setErro(getHttpErrorMessage(error, 'Não foi possível unificar os pacientes selecionados.'))
    } finally {
      setProcessando(false)
    }
  }

  return (
    <section className="rounded-janela border border-linha bg-superficie-elevada shadow-e2 p-6" data-testid="pacientes-duplicados-secao">
      <div className="flex flex-wrap items-center justify-between gap-4">
        <div>
          <h3 className="text-subtitulo font-semibold text-white">Pacientes possivelmente duplicados</h3>
          <p className="mt-1 text-meta text-slate-400">
            Escolha qual cadastro fica em cada par — o outro é desativado e marcado como mesclado, sem apagar histórico.
          </p>
        </div>
        <Botao type="button" variante="secundario" onClick={onFechar}>
          Fechar
        </Botao>
      </div>

      {query.isPending ? <p className="mt-4 text-corpo text-slate-400">Buscando duplicados...</p> : null}
      {query.isError ? (
        <p className="mt-4 text-corpo text-rose-300">
          {getHttpErrorMessage(query.error, 'Não foi possível buscar duplicados.')}
        </p>
      ) : null}
      {erro ? <p className="mt-4 text-corpo text-rose-300">{erro}</p> : null}

      {query.isSuccess && pares.length === 0 ? (
        <p className="mt-4 text-corpo text-slate-400">Nenhum par parecido encontrado.</p>
      ) : null}

      {pares.length > 0 ? (
        <>
          <div className="mt-4 flex items-center gap-2 text-meta text-slate-400">
            <input
              type="checkbox"
              checked={todasProntasSelecionadas}
              onChange={alternarTodas}
              className="size-4 rounded border-white/20 bg-white/10"
              data-testid="paciente-duplicado-selecionar-todos"
            />
            <span>
              Selecionar todos os pares prontos ({paresProntos.length} de {pares.length} já têm vencedor escolhido)
            </span>
          </div>

          <div className="mt-3 space-y-3">
            {pares.map((par) => {
              const chave = chavePar(par)
              return (
                <CardDuplicado
                  key={chave}
                  par={par}
                  vencedorId={vencedores[chave]}
                  clinicaEscolhida={clinicaEscolhidos[chave]}
                  selecionado={selecionados.has(chave)}
                  onEscolherVencedor={(id) => setVencedores((atual) => ({ ...atual, [chave]: id }))}
                  onEscolherClinica={(clinicaId) => setClinicaEscolhidos((atual) => ({ ...atual, [chave]: clinicaId }))}
                  onAlternarSelecao={() => alternarPar(chave)}
                />
              )
            })}
          </div>

          <div className="mt-4 flex flex-wrap items-center justify-between gap-3">
            <p className="text-corpo text-slate-300">{selecionados.size} de {pares.length} par(es) selecionado(s).</p>
            <Botao
              type="button"
              variante="perigo"
              carregando={processando}
              disabled={selecionados.size === 0}
              onClick={() => void unificarSelecionados()}
              data-testid="paciente-duplicado-unificar"
            >
              Unificar selecionados
            </Botao>
          </div>
        </>
      ) : null}
    </section>
  )
}
