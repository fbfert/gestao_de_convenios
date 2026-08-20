import { useEffect, useMemo, useRef, useState, type FormEvent } from 'react'
import { useNavigate, useSearchParams } from 'react-router-dom'
import { Botao } from '../../components/ui/Botao'
import { Select } from '../../components/ui/Select'
import { useProfissionais } from '../../lib/queries/useReferenceData'
import { useAntecipacoes } from '../antecipacoes/useAntecipacoes'
import {
  getHttpErrorMessage,
  useConfirmarLancamentosTranscritos,
  useImportarLancamentosTranscritos,
  useLerRegistroSessoes,
} from './useLancamentos'
import type {
  LancamentoConfirmImportForm,
  LancamentoImportForm,
  LancamentoTranscricaoPreview,
  LancamentoTranscricaoSessao,
} from './types'

const campo =
  'w-full rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-white outline-none transition focus:border-cyan-300/70 focus:ring-2 focus:ring-cyan-300/20'
const celula =
  'w-full rounded-xl border border-white/10 bg-white/5 px-3 py-2 text-white outline-none focus:border-cyan-300/70'

function formatEmpty(valor: string | null | undefined) {
  return valor === null || valor === undefined || valor === '' ? '—' : valor
}

/**
 * Importação de registro de sessões, em tela própria.
 *
 * Antes vivia embutida na listagem, com uma caixa de texto de setenta linhas
 * permanentemente aberta acima da lista. São três passos — ler, revisar e
 * confirmar — e nenhum deles cabe dividindo espaço com a listagem.
 *
 * A leitura entra por dois caminhos que terminam no mesmo lugar: foto ou PDF
 * lido por IA, e o texto colado de quem já tem a transcrição pronta.
 */
export function ImportarSessoesPage() {
  const navigate = useNavigate()
  const [searchParams] = useSearchParams()
  const arquivoRef = useRef<HTMLInputElement | null>(null)

  const [form, setForm] = useState<LancamentoImportForm>({
    antecipacao_id: searchParams.get('antecipacao_id') ?? '',
    profissional_id: '',
    transcricao: '',
  })
  const [preview, setPreview] = useState<LancamentoTranscricaoPreview | null>(null)
  const [sessoes, setSessoes] = useState<LancamentoTranscricaoSessao[]>([])
  const [pdf, setPdf] = useState<File | null>(null)
  const [erro, setErro] = useState<string | null>(null)
  const [aviso, setAviso] = useState<string | null>(null)

  const antecipacoesQuery = useAntecipacoes({ status: '', paciente_id: '', convenio_id: '' }, 1)
  const profissionaisQuery = useProfissionais()
  const analisarTexto = useImportarLancamentosTranscritos()
  const lerArquivo = useLerRegistroSessoes()
  const confirmar = useConfirmarLancamentosTranscritos()

  const antecipacoes = useMemo(() => antecipacoesQuery.data?.data ?? [], [antecipacoesQuery.data])
  const profissionais = useMemo(() => profissionaisQuery.data ?? [], [profissionaisQuery.data])

  const antecipacao = useMemo(
    () => antecipacoes.find((item) => String(item.id) === form.antecipacao_id),
    [antecipacoes, form.antecipacao_id],
  )

  /*
    Só quem atende a especialidade da antecipação. Lançar sessão no nome de
    quem não faz aquela terapia gera glosa na conciliação, e a lista completa
    da clínica torna o erro fácil.
  */
  const executantes = useMemo(() => {
    const especialidadeId = antecipacao?.especialidade?.id

    if (!especialidadeId) {
      return profissionais
    }

    const doEspecialidade = profissionais.filter((profissional) =>
      (profissional.especialidade_ids?.length
        ? profissional.especialidade_ids
        : [profissional.especialidade_id]
      ).includes(especialidadeId),
    )

    return doEspecialidade.length > 0 ? doEspecialidade : profissionais
  }, [profissionais, antecipacao])

  // Um executante só não precisa de escolha; vários, sim.
  useEffect(() => {
    setForm((atual) => {
      if (atual.profissional_id && executantes.some((p) => String(p.id) === atual.profissional_id)) {
        return atual
      }

      return { ...atual, profissional_id: executantes.length === 1 ? String(executantes[0].id) : '' }
    })
  }, [executantes])

  const exigePdf =
    preview?.cabecalho.numero_cartao?.replace(/\D+/g, '').startsWith('0220') ?? false
  const lendo = analisarTexto.isPending || lerArquivo.isPending

  const aplicarResultado = (resultado: {
    confirmacao_pendente?: boolean
    cabecalho: LancamentoTranscricaoPreview['cabecalho']
    sessoes: LancamentoTranscricaoSessao[]
  }) => {
    setPreview({
      confirmacao_pendente: resultado.confirmacao_pendente ?? true,
      cabecalho: resultado.cabecalho,
      sessoes: resultado.sessoes,
    })
    setSessoes(resultado.sessoes)
    setPdf(null)
    setAviso(resultado.sessoes.length === 0 ? 'Nenhuma sessão foi reconhecida no documento.' : null)
  }

  const analisar = async (event: FormEvent) => {
    event.preventDefault()
    setErro(null)
    setAviso(null)

    try {
      aplicarResultado(await analisarTexto.mutateAsync(form))
    } catch (error) {
      setErro(getHttpErrorMessage(error, 'Não foi possível analisar a transcrição.'))
    }
  }

  const ler = async (arquivo: File | undefined) => {
    if (!arquivo) {
      return
    }

    setErro(null)
    setAviso(null)

    try {
      aplicarResultado(
        await lerArquivo.mutateAsync({ antecipacaoId: form.antecipacao_id, arquivo }),
      )
    } catch (error) {
      setErro(getHttpErrorMessage(error, 'Não foi possível ler o registro de sessões.'))
    } finally {
      if (arquivoRef.current) {
        arquivoRef.current.value = ''
      }
    }
  }

  const atualizarSessao = (
    indice: number,
    campoAlterado: keyof LancamentoTranscricaoSessao,
    valor: string,
  ) => {
    setSessoes((atual) =>
      atual.map((sessao, i) => (i === indice ? { ...sessao, [campoAlterado]: valor } : sessao)),
    )
  }

  const enviar = async () => {
    setErro(null)

    if (exigePdf && !pdf) {
      setErro('O PDF do registro de sessões é obrigatório para a regional 0220.')

      return
    }

    try {
      const payload: LancamentoConfirmImportForm = {
        ...form,
        sessoes,
        pdf_registro_sessoes: pdf,
      }

      await confirmar.mutateAsync(payload)
      navigate('/lancamentos')
    } catch (error) {
      setErro(getHttpErrorMessage(error, 'Não foi possível confirmar o envio do registro.'))
    }
  }

  const prontoParaLer = form.antecipacao_id !== '' && form.profissional_id !== ''

  return (
    <div className="space-y-6" data-testid="importar-sessoes-page">
      <div>
        <p className="text-xs uppercase tracking-[0.3em] text-cyan-300/80">Sessões</p>
        <h2 className="mt-2 text-3xl font-semibold text-white">Importar registro de sessões</h2>
      </div>

      <section className="space-y-4 rounded-[1.75rem] border border-white/10 bg-slate-950/60 p-6">
        <div className="grid gap-4 md:grid-cols-2">
          <label className="block space-y-2">
            <span className="text-sm font-medium text-slate-200">Antecipação</span>
            <Select
              value={form.antecipacao_id}
              onChange={(event) =>
                setForm((atual) => ({ ...atual, antecipacao_id: event.target.value }))
              }
              className={campo}
              data-testid="importar-antecipacao"
            >
              <option value="">Selecione</option>
              {antecipacoes.map((item) => (
                <option key={item.id} value={item.id}>
                  #{item.id} · {item.paciente?.nome ?? `Paciente ${item.paciente_id}`}
                  {item.especialidade?.nome ? ` · ${item.especialidade.nome}` : ''} ·{' '}
                  {item.qtd_utilizada}/{item.qtd_autorizada} usadas
                </option>
              ))}
            </Select>
          </label>

          <label className="block space-y-2">
            <span className="text-sm font-medium text-slate-200">Profissional executante</span>
            <Select
              value={form.profissional_id}
              onChange={(event) =>
                setForm((atual) => ({ ...atual, profissional_id: event.target.value }))
              }
              className={campo}
              disabled={form.antecipacao_id === ''}
              data-testid="importar-profissional"
            >
              <option value="">Selecione</option>
              {executantes.map((profissional) => (
                <option key={profissional.id} value={profissional.id}>
                  {profissional.nome}
                </option>
              ))}
            </Select>
            {antecipacao?.especialidade?.nome ? (
              <span className="block text-xs text-slate-400">
                Mostrando quem atende {antecipacao.especialidade.nome}.
              </span>
            ) : null}
          </label>
        </div>

        <div className="flex flex-wrap items-center gap-3">
          <Botao
            variante="primario"
            onClick={() => arquivoRef.current?.click()}
            disabled={!prontoParaLer || lendo}
            data-testid="importar-arquivo-botao"
          >
            {lerArquivo.isPending ? 'Lendo registro...' : 'Ler foto ou PDF do registro'}
          </Botao>

          <input
            ref={arquivoRef}
            type="file"
            accept="image/*,application/pdf"
            capture="environment"
            onChange={(event) => void ler(event.target.files?.[0])}
            className="hidden"
            data-testid="importar-arquivo"
          />

          <span className="text-xs text-slate-400">
            {prontoParaLer
              ? 'A IA lê o documento e traz as sessões para conferência.'
              : 'Escolha a antecipação e o executante para começar.'}
          </span>
        </div>

        {lendo ? (
          <div
            className="flex items-center gap-3 rounded-2xl border border-cyan-300/30 bg-cyan-400/10 px-4 py-3 text-sm text-cyan-50"
            role="status"
            aria-live="polite"
            data-testid="importar-lendo"
          >
            <span className="h-4 w-4 shrink-0 animate-spin rounded-full border-2 border-cyan-200/40 border-t-cyan-100" />
            <span>
              <strong className="font-semibold">Lendo o registro…</strong> costuma levar de 5 a 30
              segundos. Não feche a tela nem clique de novo.
            </span>
          </div>
        ) : null}

        <details className="rounded-2xl border border-white/10 bg-white/5 p-4">
          <summary className="cursor-pointer text-sm font-semibold text-slate-200">
            Colar a transcrição em texto
          </summary>

          <form onSubmit={analisar} className="mt-4 space-y-3">
            <textarea
              value={form.transcricao}
              onChange={(event) =>
                setForm((atual) => ({ ...atual, transcricao: event.target.value }))
              }
              className={`${campo} min-h-48 font-mono text-sm leading-6`}
              placeholder={`GUIA Nº: 521381566206\nPaciente: ...\nNúmero Cartão: 0220 090000 551.330-8\n\n08/04/26 14:50 15:40 Bruno Marinho Aplicação de testes`}
              data-testid="importar-transcricao"
            />

            <button
              type="submit"
              disabled={!prontoParaLer || lendo || form.transcricao.trim() === ''}
              className="rounded-2xl border border-cyan-300/30 bg-cyan-400/10 px-4 py-2 text-sm font-semibold text-cyan-100 transition hover:bg-cyan-400/20 disabled:opacity-60"
              data-testid="importar-analisar-texto"
            >
              {analisarTexto.isPending ? 'Analisando...' : 'Analisar texto colado'}
            </button>
          </form>
        </details>

        {erro ? (
          <p className="rounded-2xl border border-rose-400/20 bg-rose-500/10 px-4 py-3 text-sm text-rose-100">
            {erro}
          </p>
        ) : null}

        {aviso ? (
          <p className="rounded-2xl border border-amber-300/20 bg-amber-400/10 px-4 py-3 text-sm text-amber-100">
            {aviso}
          </p>
        ) : null}
      </section>

      {preview ? (
        <section className="space-y-4 rounded-[1.75rem] border border-white/10 bg-slate-950/60 p-6">
          <div className="rounded-2xl border border-cyan-400/20 bg-cyan-500/10 px-4 py-3 text-sm text-cyan-50">
            Nenhuma sessão foi salva ainda. Ajuste datas e horários, depois confirme o envio.
          </div>

          {exigePdf ? (
            <div className="rounded-2xl border border-amber-400/20 bg-amber-500/10 px-4 py-3 text-sm text-amber-50">
              Regional 0220 detectada pela carteirinha. O PDF do registro de sessões é obrigatório
              para confirmar o envio.
            </div>
          ) : null}

          <label className="block space-y-2">
            <span className="text-sm font-medium text-slate-200">PDF do registro de sessões</span>
            <input
              type="file"
              accept="application/pdf,.pdf"
              onChange={(event) => setPdf(event.target.files?.[0] ?? null)}
              className="block w-full rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm text-slate-200 file:mr-4 file:rounded-full file:border-0 file:bg-cyan-400 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-slate-950"
              data-testid="importar-pdf"
            />
          </label>

          <div className="grid gap-3 md:grid-cols-3">
            {Object.entries(preview.cabecalho).map(([chave, valor]) => (
              <div key={chave} className="rounded-2xl border border-white/10 bg-white/5 p-3">
                <p className="text-[0.65rem] uppercase tracking-[0.2em] text-slate-400">{chave}</p>
                <p className="mt-1 text-sm font-medium text-white">{formatEmpty(valor)}</p>
              </div>
            ))}
          </div>

          <div className="overflow-x-auto rounded-3xl border border-white/10">
            <table className="w-full min-w-[48rem] border-collapse text-left text-sm">
              <thead className="bg-white/5 text-xs uppercase tracking-[0.25em] text-slate-400">
                <tr>
                  <th className="px-4 py-3">Data</th>
                  <th className="px-4 py-3">Início</th>
                  <th className="px-4 py-3">Fim</th>
                  <th className="px-4 py-3">Acompanhante</th>
                  <th className="px-4 py-3">Resumo</th>
                  <th className="px-4 py-3" />
                </tr>
              </thead>
              <tbody className="divide-y divide-white/10 bg-slate-950/30">
                {sessoes.map((sessao, indice) => (
                  <tr key={indice}>
                    <td className="px-4 py-3">
                      <input
                        type="date"
                        value={sessao.data_sessao ?? ''}
                        onChange={(event) =>
                          atualizarSessao(indice, 'data_sessao', event.target.value)
                        }
                        className={celula}
                      />
                    </td>
                    <td className="px-4 py-3">
                      <input
                        type="time"
                        value={sessao.hora_inicio ?? ''}
                        onChange={(event) =>
                          atualizarSessao(indice, 'hora_inicio', event.target.value)
                        }
                        className={celula}
                      />
                    </td>
                    <td className="px-4 py-3">
                      <input
                        type="time"
                        value={sessao.hora_fim ?? ''}
                        onChange={(event) => atualizarSessao(indice, 'hora_fim', event.target.value)}
                        className={celula}
                      />
                    </td>
                    <td className="px-4 py-3">
                      <input
                        value={sessao.acompanhante ?? ''}
                        onChange={(event) =>
                          atualizarSessao(indice, 'acompanhante', event.target.value)
                        }
                        className={celula}
                      />
                    </td>
                    <td className="px-4 py-3">
                      <textarea
                        value={sessao.resumo_atividades ?? ''}
                        onChange={(event) =>
                          atualizarSessao(indice, 'resumo_atividades', event.target.value)
                        }
                        className={`${celula} min-h-16`}
                      />
                    </td>
                    <td className="px-4 py-3">
                      <button
                        type="button"
                        onClick={() => setSessoes((atual) => atual.filter((_, i) => i !== indice))}
                        className="text-xs font-semibold text-rose-200"
                        data-testid={`importar-remover-sessao-${indice}`}
                      >
                        Remover
                      </button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>

          <div className="flex flex-wrap items-center justify-between gap-3">
            <p className="text-sm text-slate-300">
              {sessoes.length} sessão(ões) pronta(s) para confirmação.
            </p>

            <div className="flex gap-3">
              <button
                type="button"
                onClick={() => navigate('/lancamentos')}
                className="rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm font-semibold text-white transition hover:bg-white/10"
              >
                Cancelar
              </button>
              <button
                type="button"
                onClick={enviar}
                disabled={confirmar.isPending || sessoes.length === 0 || (exigePdf && !pdf)}
                className="rounded-2xl bg-emerald-400 px-4 py-3 text-sm font-semibold text-slate-950 transition hover:bg-emerald-300 disabled:opacity-60"
                data-testid="importar-confirmar"
              >
                {confirmar.isPending ? 'Confirmando...' : 'Confirmar envio'}
              </button>
            </div>
          </div>
        </section>
      ) : null}
    </div>
  )
}
