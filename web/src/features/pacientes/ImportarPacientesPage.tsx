import { useMemo, useRef, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { Botao } from '../../components/ui/Botao'
import { Badge } from '../../components/ui/Badge'
import {
  baixarTemplatePacientes,
  getHttpErrorMessage,
  useConfirmarImportPacientes,
  usePrevisualizarImportPacientes,
} from './usePacientesImport'
import type { PacienteImportLinha, PacienteImportLinhaDados, PacienteImportPreview } from './types'

const celula =
  'w-full rounded-xl border border-white/10 bg-white/5 px-3 py-2 text-white outline-none focus:border-cyan-300/70'

const STATUS_TONE: Record<PacienteImportLinha['status'], { tone: 'sucesso' | 'perigo' | 'info' | 'neutro'; rotulo: string }> = {
  valida: { tone: 'info', rotulo: 'Pronta para importar' },
  erro: { tone: 'perigo', rotulo: 'Erro' },
  importado: { tone: 'sucesso', rotulo: 'Importado' },
  atualizado: { tone: 'sucesso', rotulo: 'Atualizado' },
  ignorado: { tone: 'neutro', rotulo: 'Ignorado' },
}

export function ImportarPacientesPage() {
  const navigate = useNavigate()
  const arquivoRef = useRef<HTMLInputElement | null>(null)

  const [preview, setPreview] = useState<PacienteImportPreview | null>(null)
  const [linhas, setLinhas] = useState<PacienteImportLinha[]>([])
  const [selecionadas, setSelecionadas] = useState<Set<number>>(new Set())
  const [edicoes, setEdicoes] = useState<Record<number, Partial<PacienteImportLinhaDados>>>({})
  const [erro, setErro] = useState<string | null>(null)
  const [confirmado, setConfirmado] = useState(false)

  const previsualizar = usePrevisualizarImportPacientes()
  const confirmar = useConfirmarImportPacientes()

  const todasSelecionadas = linhas.length > 0 && selecionadas.size === linhas.length
  const algumasSelecionadas = selecionadas.size > 0 && !todasSelecionadas

  const resumo = useMemo(() => {
    if (!confirmado || !preview) {
      return null
    }

    return preview.lote
  }, [confirmado, preview])

  const escolherArquivo = async (arquivo: File | undefined) => {
    if (!arquivo) {
      return
    }

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
      if (arquivoRef.current) {
        arquivoRef.current.value = ''
      }
    }
  }

  const alternarTodas = () => {
    setSelecionadas(todasSelecionadas ? new Set() : new Set(linhas.map((linha) => linha.id)))
  }

  const alternarLinha = (linhaId: number) => {
    setSelecionadas((atual) => {
      const proxima = new Set(atual)
      if (proxima.has(linhaId)) {
        proxima.delete(linhaId)
      } else {
        proxima.add(linhaId)
      }
      return proxima
    })
  }

  const editarCampo = (
    linhaId: number,
    campo: keyof PacienteImportLinhaDados,
    valor: string | boolean,
  ) => {
    setLinhas((atual) =>
      atual.map((linha) =>
        linha.id === linhaId ? { ...linha, dados: { ...linha.dados, [campo]: valor } } : linha,
      ),
    )
    setEdicoes((atual) => ({
      ...atual,
      [linhaId]: { ...atual[linhaId], [campo]: valor },
    }))
  }

  const confirmarImportacao = async () => {
    if (!preview) {
      return
    }

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
    <div className="space-y-6" data-testid="importar-pacientes-page">
      <div>
        <p className="text-meta uppercase tracking-[0.3em] text-cyan-300/80">Pacientes</p>
        <h2 className="mt-2 text-display font-semibold text-white">Importar planilha</h2>
      </div>

      <section className="space-y-4 rounded-janela border border-linha bg-superficie-elevada shadow-e2 p-6">
        <div className="flex flex-wrap items-center gap-3">
          <Botao
            variante="secundario"
            onClick={() => void baixarTemplatePacientes()}
            data-testid="importar-pacientes-modelo"
          >
            Baixar modelo
          </Botao>

          <Botao
            variante="primario"
            onClick={() => arquivoRef.current?.click()}
            disabled={previsualizar.isPending}
            data-testid="importar-pacientes-arquivo-botao"
          >
            {previsualizar.isPending ? 'Lendo planilha...' : 'Escolher planilha (.xlsx)'}
          </Botao>

          <input
            ref={arquivoRef}
            type="file"
            accept=".xlsx"
            onChange={(event) => void escolherArquivo(event.target.files?.[0])}
            className="hidden"
            data-testid="importar-pacientes-arquivo"
          />

          <span className="text-meta text-slate-400">
            Preencha o modelo baixado e envie de volta para conferência antes de importar.
          </span>
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
            {resumo.total_importados} paciente(s) novo(s), {resumo.total_atualizados} atualizado(s),{' '}
            {resumo.total_ignorados} ignorado(s)
            {resumo.total_invalidas > 0 ? `, ${resumo.total_invalidas} com erro (não importado)` : ''}.
          </p>
          <Botao variante="primario" onClick={() => navigate('/pacientes')} data-testid="importar-pacientes-concluir">
            Voltar para Pacientes
          </Botao>
        </section>
      ) : null}

      {preview && !confirmado ? (
        <section className="space-y-4 rounded-janela border border-linha bg-superficie-elevada shadow-e2 p-6">
          <div className="rounded-2xl border border-cyan-400/20 bg-cyan-500/10 px-4 py-3 text-corpo text-cyan-50">
            Nenhum paciente foi salvo ainda. Confira e edite as linhas abaixo, marque as que deseja
            importar e confirme.
          </div>

          <div className="overflow-x-auto rounded-superficie border border-linha">
            <table className="w-full min-w-[64rem] border-collapse text-left text-corpo" data-cartoes="lg">
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
                      data-testid="importar-pacientes-selecionar-todas"
                    />
                  </th>
                  <th className="px-4 py-3">Linha</th>
                  <th className="px-4 py-3">Nome</th>
                  <th className="px-4 py-3">CPF</th>
                  <th className="px-4 py-3">Carteirinha</th>
                  <th className="px-4 py-3">Convênio</th>
                  <th className="px-4 py-3">Nascimento</th>
                  <th className="px-4 py-3">Validade carteirinha</th>
                  <th className="px-4 py-3">Telefone</th>
                  <th className="px-4 py-3">Ativo</th>
                  <th className="px-4 py-3">Status</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-linha bg-superficie">
                {linhas.map((linha) => (
                  <tr key={linha.id} data-testid={`importar-pacientes-linha-${linha.id}`}>
                    <td className="px-4 py-3">
                      <input
                        type="checkbox"
                        checked={selecionadas.has(linha.id)}
                        onChange={() => alternarLinha(linha.id)}
                        className="size-4 rounded border-white/20 bg-white/10"
                        data-testid={`importar-pacientes-checkbox-${linha.id}`}
                      />
                    </td>
                    <td className="px-4 py-3 text-slate-400">{linha.linha}</td>
                    <td className="px-4 py-3">
                      <input
                        value={linha.dados.nome}
                        onChange={(event) => editarCampo(linha.id, 'nome', event.target.value)}
                        className={celula}
                      />
                      {linha.erros.nome ? (
                        <p className="mt-1 text-meta text-rose-300">{linha.erros.nome}</p>
                      ) : null}
                    </td>
                    <td className="px-4 py-3">
                      <input
                        value={linha.dados.cpf ?? ''}
                        onChange={(event) => editarCampo(linha.id, 'cpf', event.target.value)}
                        className={celula}
                      />
                      {linha.erros.cpf ? (
                        <p className="mt-1 text-meta text-rose-300">{linha.erros.cpf}</p>
                      ) : null}
                    </td>
                    <td className="px-4 py-3">
                      <input
                        value={linha.dados.carteirinha}
                        onChange={(event) => editarCampo(linha.id, 'carteirinha', event.target.value)}
                        className={celula}
                      />
                      {linha.erros.carteirinha ? (
                        <p className="mt-1 text-meta text-rose-300">{linha.erros.carteirinha}</p>
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
                        type="date"
                        value={linha.dados.data_nascimento ?? ''}
                        onChange={(event) => editarCampo(linha.id, 'data_nascimento', event.target.value)}
                        className={celula}
                      />
                      {linha.erros.data_nascimento ? (
                        <p className="mt-1 text-meta text-rose-300">{linha.erros.data_nascimento}</p>
                      ) : null}
                    </td>
                    <td className="px-4 py-3">
                      <input
                        type="date"
                        value={linha.dados.validade_carteirinha ?? ''}
                        onChange={(event) =>
                          editarCampo(linha.id, 'validade_carteirinha', event.target.value)
                        }
                        className={celula}
                      />
                      {linha.erros.validade_carteirinha ? (
                        <p className="mt-1 text-meta text-rose-300">{linha.erros.validade_carteirinha}</p>
                      ) : null}
                    </td>
                    <td className="px-4 py-3">
                      <input
                        value={linha.dados.telefone ?? ''}
                        onChange={(event) => editarCampo(linha.id, 'telefone', event.target.value)}
                        className={celula}
                      />
                    </td>
                    <td className="px-4 py-3">
                      <input
                        type="checkbox"
                        checked={linha.dados.ativo}
                        onChange={(event) => editarCampo(linha.id, 'ativo', event.target.checked)}
                        className="size-4 rounded border-white/20 bg-white/10"
                      />
                    </td>
                    <td className="px-4 py-3">
                      <Badge tone={STATUS_TONE[linha.status].tone}>{STATUS_TONE[linha.status].rotulo}</Badge>
                      {linha.matched_paciente_id ? (
                        <p className="mt-1 text-meta text-slate-400">Atualiza #{linha.matched_paciente_id}</p>
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
              <Botao variante="secundario" onClick={() => navigate('/pacientes')}>
                Cancelar
              </Botao>
              <Botao
                variante="primario"
                onClick={() => void confirmarImportacao()}
                disabled={confirmar.isPending || selecionadas.size === 0}
                data-testid="importar-pacientes-confirmar"
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
