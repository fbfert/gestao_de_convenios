import { useMemo, useRef, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { Botao } from '../../components/ui/Botao'
import { Badge } from '../../components/ui/Badge'
import { Select } from '../../components/ui/Select'
import {
  baixarTemplateConciliacoes,
  getHttpErrorMessage,
  useConfirmarImportConciliacoes,
  usePrevisualizarImportConciliacoes,
} from './useConciliacoesImport'
import type {
  ConciliacaoImportLinha,
  ConciliacaoImportLinhaDados,
  ConciliacaoImportPreview,
} from './types'

const celula =
  'w-full rounded-xl border border-white/10 bg-white/5 px-3 py-2 text-white outline-none focus:border-cyan-300/70'

const STATUS_TONE: Record<ConciliacaoImportLinha['status'], { tone: 'sucesso' | 'perigo' | 'info' | 'neutro'; rotulo: string }> = {
  valida: { tone: 'info', rotulo: 'Pronta para importar' },
  erro: { tone: 'perigo', rotulo: 'Erro' },
  importado: { tone: 'sucesso', rotulo: 'Importado' },
  atualizado: { tone: 'sucesso', rotulo: 'Atualizado' },
  ignorado: { tone: 'neutro', rotulo: 'Ignorado' },
}

const STATUS_OPCOES = [
  { valor: 'pending', rotulo: 'Pendente' },
  { valor: 'reviewed', rotulo: 'Conferida' },
  { valor: 'paid', rotulo: 'Paga' },
]

export function ImportarConciliacoesPage() {
  const navigate = useNavigate()
  const arquivoRef = useRef<HTMLInputElement | null>(null)

  const [preview, setPreview] = useState<ConciliacaoImportPreview | null>(null)
  const [linhas, setLinhas] = useState<ConciliacaoImportLinha[]>([])
  const [selecionadas, setSelecionadas] = useState<Set<number>>(new Set())
  const [edicoes, setEdicoes] = useState<Record<number, Partial<ConciliacaoImportLinhaDados>>>({})
  const [erro, setErro] = useState<string | null>(null)
  const [confirmado, setConfirmado] = useState(false)

  const previsualizar = usePrevisualizarImportConciliacoes()
  const confirmar = useConfirmarImportConciliacoes()

  const todasSelecionadas = linhas.length > 0 && selecionadas.size === linhas.length
  const algumasSelecionadas = selecionadas.size > 0 && !todasSelecionadas

  const resumo = useMemo(() => (confirmado && preview ? preview.lote : null), [confirmado, preview])

  const escolherArquivo = async (arquivo: File | undefined) => {
    if (!arquivo) return

    setErro(null)
    setConfirmado(false)

    try {
      const resultado = await previsualizar.mutateAsync(arquivo)
      setPreview(resultado)
      setLinhas(resultado.linhas)
      setEdicoes({})
      setSelecionadas(new Set(resultado.linhas.filter((linha) => linha.status === 'valida').map((linha) => linha.id)))
    } catch (error) {
      setErro(getHttpErrorMessage(error, 'Não foi possível ler a planilha.'))
    } finally {
      if (arquivoRef.current) arquivoRef.current.value = ''
    }
  }

  const alternarTodas = () => {
    setSelecionadas(todasSelecionadas ? new Set() : new Set(linhas.map((linha) => linha.id)))
  }

  const alternarLinha = (linhaId: number) => {
    setSelecionadas((atual) => {
      const proxima = new Set(atual)
      if (proxima.has(linhaId)) proxima.delete(linhaId)
      else proxima.add(linhaId)
      return proxima
    })
  }

  const editarCampo = (linhaId: number, campo: keyof ConciliacaoImportLinhaDados, valor: string | number | null) => {
    setLinhas((atual) =>
      atual.map((linha) => (linha.id === linhaId ? { ...linha, dados: { ...linha.dados, [campo]: valor } } : linha)),
    )
    setEdicoes((atual) => ({ ...atual, [linhaId]: { ...atual[linhaId], [campo]: valor } }))
  }

  const confirmarImportacao = async () => {
    if (!preview) return
    setErro(null)

    try {
      const resultado = await confirmar.mutateAsync({
        loteId: preview.lote.id,
        linhaIds: Array.from(selecionadas),
        edicoes,
      })
      setPreview(resultado)
      setLinhas(resultado.linhas)
      setConfirmado(true)
    } catch (error) {
      setErro(getHttpErrorMessage(error, 'Não foi possível confirmar a importação.'))
    }
  }

  return (
    <div className="space-y-6" data-testid="importar-conciliacoes-page">
      <div>
        <p className="text-meta uppercase tracking-[0.3em] text-cyan-300/80">Conciliação financeira</p>
        <h2 className="mt-2 text-display font-semibold text-white">Importar planilha</h2>
        <p className="mt-1 text-corpo text-slate-300">
          Grava os valores direto, por cima do fluxo normal (gerar pela guia). Não cria os
          movimentos financeiros de repasse/retenção — só o registro da conciliação em si.
        </p>
      </div>

      <section className="space-y-4 rounded-janela border border-linha bg-superficie-elevada shadow-e2 p-6">
        <div className="flex flex-wrap items-center gap-3">
          <Botao variante="secundario" onClick={() => void baixarTemplateConciliacoes()} data-testid="importar-conciliacoes-modelo">
            Baixar modelo
          </Botao>
          <Botao
            variante="primario"
            onClick={() => arquivoRef.current?.click()}
            disabled={previsualizar.isPending}
            data-testid="importar-conciliacoes-arquivo-botao"
          >
            {previsualizar.isPending ? 'Lendo planilha...' : 'Escolher planilha (.xlsx)'}
          </Botao>
          <input
            ref={arquivoRef}
            type="file"
            accept=".xlsx"
            onChange={(event) => void escolherArquivo(event.target.files?.[0])}
            className="hidden"
            data-testid="importar-conciliacoes-arquivo"
          />
        </div>

        {erro ? (
          <p className="rounded-2xl border border-rose-400/20 bg-rose-500/10 px-4 py-3 text-corpo text-rose-100">
            {erro}
          </p>
        ) : null}
      </section>

      {preview && resumo ? (
        <section className="space-y-3 rounded-janela border border-emerald-400/20 bg-emerald-500/10 p-6 text-emerald-50">
          <h3 className="text-subtitulo font-semibold">Importação confirmada</h3>
          <p className="text-corpo">
            {resumo.total_importados} nova(s), {resumo.total_atualizados} atualizada(s),{' '}
            {resumo.total_ignorados} ignorada(s)
            {resumo.total_invalidas > 0 ? `, ${resumo.total_invalidas} com erro (não importada)` : ''}.
          </p>
          <Botao variante="primario" onClick={() => navigate('/conciliacao')} data-testid="importar-conciliacoes-concluir">
            Voltar para Conciliação
          </Botao>
        </section>
      ) : null}

      {preview && !confirmado ? (
        <section className="space-y-4 rounded-janela border border-linha bg-superficie-elevada shadow-e2 p-6">
          <div className="rounded-2xl border border-cyan-400/20 bg-cyan-500/10 px-4 py-3 text-corpo text-cyan-50">
            Nenhuma conciliação foi salva ainda. Confira e edite as linhas abaixo, marque as que
            deseja importar e confirme.
          </div>

          <div className="overflow-x-auto rounded-superficie border border-linha">
            <table className="w-full min-w-[90rem] border-collapse text-left text-corpo" data-cartoes="lg">
              <thead className="bg-fundo text-meta uppercase tracking-[0.25em] text-texto-suave">
                <tr>
                  <th className="px-4 py-3">
                    <input
                      type="checkbox"
                      checked={todasSelecionadas}
                      ref={(el) => {
                        if (el) el.indeterminate = algumasSelecionadas
                      }}
                      onChange={alternarTodas}
                      className="size-4 rounded border-white/20 bg-white/10"
                      data-testid="importar-conciliacoes-selecionar-todas"
                    />
                  </th>
                  <th className="px-4 py-3">Linha</th>
                  <th className="px-4 py-3">Número guia</th>
                  <th className="px-4 py-3">Convênio</th>
                  <th className="px-4 py-3">Profissional</th>
                  <th className="px-4 py-3">Qtd</th>
                  <th className="px-4 py-3">Valor unitário</th>
                  <th className="px-4 py-3">Valor total</th>
                  <th className="px-4 py-3">Status</th>
                  <th className="px-4 py-3">Situação</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-linha bg-superficie">
                {linhas.map((linha) => (
                  <tr key={linha.id} data-testid={`importar-conciliacoes-linha-${linha.id}`}>
                    <td className="px-4 py-3">
                      <input
                        type="checkbox"
                        checked={selecionadas.has(linha.id)}
                        onChange={() => alternarLinha(linha.id)}
                        className="size-4 rounded border-white/20 bg-white/10"
                        data-testid={`importar-conciliacoes-checkbox-${linha.id}`}
                      />
                    </td>
                    <td className="px-4 py-3 text-slate-400">{linha.linha}</td>
                    <td className="px-4 py-3">
                      <input
                        value={linha.dados.numero_guia}
                        onChange={(event) => editarCampo(linha.id, 'numero_guia', event.target.value)}
                        className={celula}
                      />
                      {linha.erros.numero_guia ? (
                        <p className="mt-1 text-meta text-rose-300">{linha.erros.numero_guia}</p>
                      ) : null}
                    </td>
                    <td className="px-4 py-3">
                      <input
                        value={linha.dados.convenio}
                        onChange={(event) => editarCampo(linha.id, 'convenio', event.target.value)}
                        className={celula}
                      />
                      {linha.erros.convenio ? (
                        <p className="mt-1 text-meta text-rose-300">{linha.erros.convenio}</p>
                      ) : null}
                    </td>
                    <td className="px-4 py-3">
                      <input
                        value={linha.dados.profissional}
                        onChange={(event) => editarCampo(linha.id, 'profissional', event.target.value)}
                        className={celula}
                      />
                      {linha.erros.profissional ? (
                        <p className="mt-1 text-meta text-rose-300">{linha.erros.profissional}</p>
                      ) : null}
                    </td>
                    <td className="px-4 py-3">
                      <input
                        type="number"
                        min={0}
                        value={linha.dados.quantidade ?? ''}
                        onChange={(event) => editarCampo(linha.id, 'quantidade', event.target.value)}
                        className={`${celula} w-20`}
                      />
                      {linha.erros.quantidade ? (
                        <p className="mt-1 text-meta text-rose-300">{linha.erros.quantidade}</p>
                      ) : null}
                    </td>
                    <td className="px-4 py-3">
                      <input
                        value={linha.dados.valor_unitario ?? ''}
                        onChange={(event) => editarCampo(linha.id, 'valor_unitario', event.target.value)}
                        className={`${celula} w-28`}
                      />
                    </td>
                    <td className="px-4 py-3">
                      <input
                        value={linha.dados.valor_total ?? ''}
                        onChange={(event) => editarCampo(linha.id, 'valor_total', event.target.value)}
                        className={`${celula} w-28`}
                      />
                    </td>
                    <td className="px-4 py-3">
                      <Select
                        value={linha.dados.status ?? 'pending'}
                        onChange={(event) => editarCampo(linha.id, 'status', event.target.value)}
                        className={celula}
                      >
                        {STATUS_OPCOES.map((opcao) => (
                          <option key={opcao.valor} value={opcao.valor}>
                            {opcao.rotulo}
                          </option>
                        ))}
                      </Select>
                    </td>
                    <td className="px-4 py-3">
                      <Badge tone={STATUS_TONE[linha.status].tone}>{STATUS_TONE[linha.status].rotulo}</Badge>
                      {linha.matched_conciliacao_id ? (
                        <p className="mt-1 text-meta text-slate-400">Atualiza #{linha.matched_conciliacao_id}</p>
                      ) : null}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>

          <div className="flex flex-wrap items-center justify-between gap-3">
            <p className="inline-flex min-h-6 items-center text-corpo text-slate-300">
              {selecionadas.size} de {linhas.length} linha(s) selecionada(s) para importar.
            </p>
            <div className="flex gap-3">
              <Botao variante="secundario" onClick={() => navigate('/conciliacao')}>
                Cancelar
              </Botao>
              <Botao
                variante="primario"
                onClick={() => void confirmarImportacao()}
                disabled={confirmar.isPending || selecionadas.size === 0}
                data-testid="importar-conciliacoes-confirmar"
              >
                {confirmar.isPending ? 'Confirmando...' : 'Confirmar importação'}
              </Botao>
            </div>
          </div>
        </section>
      ) : null}
    </div>
  )
}
