import { useState } from 'react'
import { Select } from '../../components/ui/Select'
import { Botao } from '../../components/ui/Botao'
import { useCids } from '../../lib/queries/useReferenceData'
import { useCriarCidRapido, getHttpErrorMessage } from '../solicitacoes/useSolicitacoes'

type CidCampoProps = {
  value: string
  onChange: (id: string) => void
  testIdPrefix: string
}

function fieldClasses() {
  return 'w-full rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm text-white outline-none transition placeholder:text-slate-500 focus:border-cyan-300/70 focus:ring-2 focus:ring-cyan-300/20'
}

/**
 * Select de CID pré-cadastrado + atalho para cadastrar um novo sem sair do
 * formulário — mesma ideia do cadastro rápido de paciente/especialidade/médico
 * que já existe na leitura de pedido médico, só que sempre visível aqui.
 */
export function CidCampo({ value, onChange, testIdPrefix }: CidCampoProps) {
  const cidsQuery = useCids()
  const criarCidRapido = useCriarCidRapido()
  const cids = cidsQuery.data ?? []

  const [abrirNovo, setAbrirNovo] = useState(false)
  const [novoCodigo, setNovoCodigo] = useState('')
  const [novaDescricao, setNovaDescricao] = useState('')
  const [erro, setErro] = useState<string | null>(null)

  const fechar = () => {
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
      onChange(String(cid.id))
      fechar()
    } catch (error) {
      setErro(getHttpErrorMessage(error, 'Não foi possível cadastrar o CID.'))
    }
  }

  return (
    <div className="space-y-2">
      <div className="flex gap-2">
        <div className="flex-1">
          <Select
            value={value}
            onChange={(event) => onChange(event.target.value)}
            disabled={cidsQuery.isLoading}
            data-testid={`${testIdPrefix}-select`}
          >
            <option value="" disabled>
              Selecione
            </option>
            {cids.map((cid) => (
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
              className={fieldClasses()}
              placeholder="F84.0"
              data-testid={`${testIdPrefix}-novo-codigo`}
            />
          </label>
          <label className="block space-y-1">
            <span className="text-xs uppercase tracking-[0.25em] text-slate-400">Descrição</span>
            <input
              value={novaDescricao}
              onChange={(event) => setNovaDescricao(event.target.value)}
              className={fieldClasses()}
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
            <Botao type="button" variante="secundario" tamanho="sm" onClick={fechar}>
              Cancelar
            </Botao>
          </div>
        </div>
      ) : null}
    </div>
  )
}
