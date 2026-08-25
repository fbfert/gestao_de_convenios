import { useEffect, useState } from 'react'
import { Select } from '../../components/ui/Select'
import { Botao } from '../../components/ui/Botao'
import { useCids } from '../../lib/queries/useReferenceData'
import { useCriarCidRapido, getHttpErrorMessage } from '../solicitacoes/useSolicitacoes'

type CidsCampoProps = {
  value: string[]
  onChange: (ids: string[]) => void
  testIdPrefix: string
  /**
   * Termo lido pela IA sem cadastro parecido — quando muda pra um valor
   * não-vazio, abre o cadastro rápido já com código/descrição sugeridos
   * ("F84.0 - Autismo infantil" vira código "F84.0", descrição o resto).
   * Chamador limpa o valor (via `onTermoConsumido`) depois de abrir, senão
   * reabriria de novo a cada render.
   */
  abrirNovoComTermo?: string | null
  onTermoConsumido?: () => void
}

function separarTermo(termo: string): { codigo: string; descricao: string } {
  const [codigo, ...resto] = termo.split(/\s+-\s+/)
  return { codigo: (codigo ?? '').trim(), descricao: resto.join(' - ').trim() }
}

/**
 * Multi-seleção de CID — uma solicitação pode citar mais de um (N-pra-N
 * desde 25/08/2026, antes era um Select único). Mesma ideia do cadastro
 * rápido de paciente/especialidade/médico: dá pra cadastrar um CID novo sem
 * sair do formulário.
 */
export function CidsCampo({
  value,
  onChange,
  testIdPrefix,
  abrirNovoComTermo,
  onTermoConsumido,
}: CidsCampoProps) {
  const cidsQuery = useCids()
  const criarCidRapido = useCriarCidRapido()
  const cids = cidsQuery.data ?? []

  const [abrirNovo, setAbrirNovo] = useState(false)
  const [novoCodigo, setNovoCodigo] = useState('')
  const [novaDescricao, setNovaDescricao] = useState('')
  const [erro, setErro] = useState<string | null>(null)

  useEffect(() => {
    if (!abrirNovoComTermo) {
      return
    }

    const { codigo, descricao } = separarTermo(abrirNovoComTermo)
    setNovoCodigo(codigo)
    setNovaDescricao(descricao)
    setAbrirNovo(true)
    onTermoConsumido?.()
    // onTermoConsumido só limpa o gatilho no pai — não deve reexecutar este
    // efeito por identidade nova de função a cada render.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [abrirNovoComTermo])

  const selecionados = value
    .map((id) => cids.find((cid) => String(cid.id) === id))
    .filter((cid): cid is NonNullable<typeof cid> => Boolean(cid))
  const disponiveis = cids.filter((cid) => !value.includes(String(cid.id)))

  const adicionar = (id: string) => {
    if (id && !value.includes(id)) {
      onChange([...value, id])
    }
  }

  const remover = (id: string) => {
    onChange(value.filter((atual) => atual !== id))
  }

  const fecharNovo = () => {
    setAbrirNovo(false)
    setNovoCodigo('')
    setNovaDescricao('')
    setErro(null)
  }

  const salvarNovo = async () => {
    setErro(null)

    try {
      const cid = await criarCidRapido.mutateAsync({
        codigo: novoCodigo.trim(),
        descricao: novaDescricao.trim(),
      })
      adicionar(String(cid.id))
      fecharNovo()
    } catch (error) {
      setErro(getHttpErrorMessage(error, 'Não foi possível cadastrar o CID.'))
    }
  }

  return (
    <div className="space-y-2">
      {selecionados.length > 0 ? (
        <div className="flex flex-wrap gap-2" data-testid={`${testIdPrefix}-selecionados`}>
          {selecionados.map((cid) => (
            <span
              key={cid.id}
              className="inline-flex items-center gap-2 rounded-full border border-cyan-400/30 bg-cyan-400/10 px-3 py-1.5 text-xs font-semibold text-cyan-100"
            >
              {cid.codigo} — {cid.descricao}
              <button
                type="button"
                onClick={() => remover(String(cid.id))}
                aria-label={`Remover CID ${cid.codigo}`}
                className="text-cyan-200 hover:text-white"
                data-testid={`${testIdPrefix}-remover-${cid.id}`}
              >
                ×
              </button>
            </span>
          ))}
        </div>
      ) : null}

      <div className="flex gap-2">
        <div className="flex-1">
          <Select
            value=""
            onChange={(event) => adicionar(event.target.value)}
            disabled={cidsQuery.isLoading || disponiveis.length === 0}
            data-testid={`${testIdPrefix}-select`}
          >
            <option value="" disabled>
              {selecionados.length > 0 ? 'Adicionar outro CID...' : 'Selecione'}
            </option>
            {disponiveis.map((cid) => (
              <option key={cid.id} value={cid.id}>
                {cid.codigo} — {cid.descricao}
              </option>
            ))}
          </Select>
        </div>
        <Botao
          type="button"
          variante="secundario"
          tamanho="sm"
          onClick={() => setAbrirNovo((current) => !current)}
          data-testid={`${testIdPrefix}-novo-abrir`}
        >
          +
        </Botao>
      </div>

      {abrirNovo ? (
        <div className="space-y-2 rounded-2xl border border-white/10 bg-white/5 p-4">
          <label className="block space-y-1">
            <span className="text-xs uppercase tracking-[0.25em] text-slate-400">Código</span>
            <input
              value={novoCodigo}
              onChange={(event) => setNovoCodigo(event.target.value)}
              className="w-full rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm text-white outline-none transition placeholder:text-slate-500 focus:border-cyan-300/70 focus:ring-2 focus:ring-cyan-300/20"
              placeholder="F84.0"
              data-testid={`${testIdPrefix}-novo-codigo`}
            />
          </label>
          <label className="block space-y-1">
            <span className="text-xs uppercase tracking-[0.25em] text-slate-400">Descrição</span>
            <input
              value={novaDescricao}
              onChange={(event) => setNovaDescricao(event.target.value)}
              className="w-full rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm text-white outline-none transition placeholder:text-slate-500 focus:border-cyan-300/70 focus:ring-2 focus:ring-cyan-300/20"
              placeholder="Autismo infantil"
              data-testid={`${testIdPrefix}-novo-descricao`}
            />
          </label>

          {erro ? <p className="text-xs text-rose-100">{erro}</p> : null}

          <div className="flex gap-2">
            <Botao
              type="button"
              tamanho="sm"
              carregando={criarCidRapido.isPending}
              disabled={!novoCodigo.trim() || !novaDescricao.trim()}
              onClick={() => void salvarNovo()}
              data-testid={`${testIdPrefix}-novo-salvar`}
            >
              Salvar
            </Botao>
            <Botao type="button" variante="secundario" tamanho="sm" onClick={fecharNovo}>
              Cancelar
            </Botao>
          </div>
        </div>
      ) : null}
    </div>
  )
}
