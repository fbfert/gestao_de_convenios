import { useQuery, useQueryClient } from '@tanstack/react-query'
import { useState, type FormEvent } from 'react'
import { Link, useMatch, useNavigate, useParams } from 'react-router-dom'
import { apiClient } from '../../api/client'
import { Select } from '../../components/ui/Select'
import { useEspecialidades, useProfissionais } from '../../lib/queries/useReferenceData'

type Convenio = {
  id: number
  nome: string
  descricao: string | null
  connector_type: string
  ativo: boolean
}

type Regra = {
  id: number
  tipo_terapia: string
  frequencia_lancamento: string
  qtd_autorizada_por_ciclo: number
  validade_senha_dias: number | null
  vigente_desde: string
  vigente_ate: string | null
}

type Valor = {
  id: number
  especialidade_id: number | null
  profissional_id: number | null
  valor: string
  vigente_desde: string
  vigente_ate: string | null
}

const field = 'w-full rounded-xl border border-white/10 bg-slate-950 px-3 py-2 text-white'
const card = 'rounded-2xl border border-white/10 bg-white/5 p-5'

export function ConveniosPage() {
  const navigate = useNavigate()
  const isCreateRoute = useMatch('/convenios/novo') !== null
  const q = useQuery({
    queryKey: ['convenios'],
    queryFn: async () => (await apiClient.get<{ data: Convenio[] }>('/convenios')).data.data,
  })
  const qc = useQueryClient()
  const [form, setForm] = useState<Convenio | null>(null)
  const [nome, setNome] = useState('')
  const [descricao, setDescricao] = useState('')
  const [tipo, setTipo] = useState('manual')
  const [ativo, setAtivo] = useState(true)

  const abrirNovo = () => {
    navigate('/convenios/novo')
    setForm({ id: 0, nome: '', descricao: '', connector_type: 'manual', ativo: true })
    setNome('')
    setDescricao('')
    setTipo('manual')
    setAtivo(true)
  }

  const salvar = (event: FormEvent) => {
    event.preventDefault()

    const payload = { nome, descricao, connector_type: tipo, ativo }
    const request = form?.id
      ? apiClient.patch(`/convenios/${form.id}`, payload)
      : apiClient.post('/convenios', payload)

    request.then(() => {
      qc.invalidateQueries({ queryKey: ['convenios'] })
      setForm(null)
      setNome('')
      if (isCreateRoute) {
        navigate('/convenios')
      }
    })
  }

  return (
    <div className="space-y-5">
      <div className="flex justify-between">
        <div>
          <p className="text-xs uppercase tracking-[.3em] text-cyan-300">Convênios</p>
          <h2 className="mt-2 text-3xl font-semibold">Gestão de convênios</h2>
          <p className="mt-2 text-sm text-slate-300">
            Abra um convênio para configurar regras de autorização e valores de pagamento.
          </p>
        </div>
        {!isCreateRoute ? (
          <button
            onClick={abrirNovo}
            className="rounded-xl bg-cyan-400 px-4 py-2 font-semibold text-slate-950"
          >
            Novo convênio
          </button>
        ) : null}
      </div>

      {isCreateRoute ? (
        <section className={card}>
          <form onSubmit={salvar}>
            <h3 className="font-semibold">{form?.id ? 'Editar convênio' : 'Novo convênio'}</h3>
            <div className="mt-3 grid gap-3 md:grid-cols-3">
              <input
                required
                value={nome}
                onChange={(event) => setNome(event.target.value)}
                placeholder="Nome"
                className={field}
              />
              <textarea
                value={descricao}
                onChange={(event) => setDescricao(event.target.value)}
                placeholder="Descrição"
                className={field}
              />
              <Select value={tipo} onChange={(event) => setTipo(event.target.value)}>
                <option value="manual">Manual</option>
                <option value="api">API</option>
                <option value="scraping">Scraping</option>
              </Select>
              <label className="flex items-center gap-2">
                <input
                  type="checkbox"
                  checked={ativo}
                  onChange={(event) => setAtivo(event.target.checked)}
                />
                Ativo
              </label>
              <button className="rounded-xl bg-cyan-400 p-2 font-semibold text-slate-950">
                Salvar
              </button>
              <button type="button" onClick={() => navigate('/convenios')}>
                Cancelar
              </button>
            </div>
          </form>
        </section>
      ) : null}

      {!isCreateRoute ? (
        <>
          {form ? (
            <form onSubmit={salvar} className={card}>
              <h3 className="font-semibold">{form.id ? 'Editar convênio' : 'Novo convênio'}</h3>
              <div className="mt-3 grid gap-3 md:grid-cols-3">
                <input
                  required
                  value={nome}
                  onChange={(event) => setNome(event.target.value)}
                  placeholder="Nome"
                  className={field}
                />
                <textarea
                  value={descricao}
                  onChange={(event) => setDescricao(event.target.value)}
                  placeholder="Descrição"
                  className={field}
                />
                <Select value={tipo} onChange={(event) => setTipo(event.target.value)}>
                  <option value="manual">Manual</option>
                  <option value="api">API</option>
                  <option value="scraping">Scraping</option>
                </Select>
                <label className="flex items-center gap-2">
                  <input
                    type="checkbox"
                    checked={ativo}
                    onChange={(event) => setAtivo(event.target.checked)}
                  />
                  Ativo
                </label>
                <button className="rounded-xl bg-cyan-400 p-2 font-semibold text-slate-950">
                  Salvar
                </button>
                <button type="button" onClick={() => setForm(null)}>
                  Cancelar
                </button>
              </div>
            </form>
          ) : null}

          {q.data?.map((c) => (
            <div key={c.id} className={`${card} flex items-center justify-between`}>
              <Link to={`/convenios/${c.id}`}>
                {c.nome}
                {c.descricao ? <p className="text-xs text-slate-400">{c.descricao}</p> : null}
              </Link>
              <div className="flex gap-3">
                <span>{c.ativo ? 'Ativo' : 'Inativo'}</span>
                <button
                  onClick={() => {
                    setForm(c)
                    setNome(c.nome)
                    setDescricao(c.descricao ?? '')
                    setTipo(c.connector_type)
                    setAtivo(c.ativo)
                  }}
                  className="text-cyan-200"
                >
                  Editar
                </button>
              </div>
            </div>
          ))}
        </>
      ) : null}
    </div>
  )
}

export function ConvenioDetalhePage() {
  const { id } = useParams()
  const qc = useQueryClient()
  const [tipo, setTipo] = useState('especializada')
  const [freq, setFreq] = useState('semanal')
  const [especialidade, setEspecialidade] = useState('')
  const [profissional, setProfissional] = useState('')
  const k = ['convenio', id]

  const regras = useQuery({
    queryKey: [...k, 'regras'],
    queryFn: async () => (await apiClient.get<{ data: Regra[] }>(`/convenios/${id}/regras`)).data.data,
  })
  const valores = useQuery({
    queryKey: [...k, 'valores'],
    queryFn: async () => (await apiClient.get<{ data: Valor[] }>(`/convenios/${id}/valores`)).data.data,
  })
  const esps = useEspecialidades()
  const profs = useProfissionais({ especialidade_id: especialidade })

  const post = (url: string, payload: object, key: unknown[]) =>
    apiClient.post(url, payload).then(() => qc.invalidateQueries({ queryKey: key }))

  const enc = (url: string, key: unknown[]) => {
    if (confirm('Encerrar esta vigência? Isso afeta cálculos futuros.')) {
      apiClient.patch(url).then(() => qc.invalidateQueries({ queryKey: key }))
    }
  }

  const regra = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    const formData = new FormData(event.currentTarget)
    post(
      `/convenios/${id}/regras`,
      {
        tipo_terapia: tipo,
        frequencia_lancamento: freq,
        qtd_autorizada_por_ciclo: Number(formData.get('qtd')),
        validade_senha_dias: formData.get('senha') ? Number(formData.get('senha')) : null,
        vigente_desde: formData.get('desde'),
      },
      [...k, 'regras'],
    )
    event.currentTarget.reset()
  }

  const valor = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    const formData = new FormData(event.currentTarget)
    post(
      `/convenios/${id}/valores`,
      {
        especialidade_id: especialidade ? Number(especialidade) : null,
        profissional_id: profissional ? Number(profissional) : null,
        valor: formData.get('valor'),
        vigente_desde: formData.get('desde'),
      },
      [...k, 'valores'],
    )
    event.currentTarget.reset()
  }

  return (
    <div className="space-y-6">
      <div className="flex items-center gap-3">
        <Link to="/convenios" className="text-cyan-200">
          ← Convênios
        </Link>
        <Link
          to="/convenios/ajuda"
          className="rounded-xl border border-cyan-400/40 px-3 py-1.5 text-sm font-semibold text-cyan-100 transition hover:bg-cyan-400/10"
        >
          Ajuda
        </Link>
      </div>

      <h2 className="text-3xl font-semibold">Configuração do convênio</h2>

      <section className={card}>
        <h3 className="text-lg font-semibold">Nova regra</h3>
        <form onSubmit={regra} className="mt-3 grid gap-3 md:grid-cols-3">
          <Select value={tipo} onChange={(event) => setTipo(event.target.value)}>
            <option value="especializada">Especializada</option>
            <option value="convencional">Convencional</option>
            <option value="outro">Outro</option>
          </Select>
          <Select value={freq} onChange={(event) => setFreq(event.target.value)}>
            <option value="diaria">Diária</option>
            <option value="semanal">Semanal</option>
            <option value="mensal">Mensal</option>
          </Select>
          <input name="qtd" required type="number" min="1" placeholder="Quantidade" className={field} />
          <input name="senha" type="number" min="1" placeholder="Validade senha (dias)" className={field} />
          <input name="desde" required type="date" className={field} />
          <button className="rounded-xl bg-cyan-400 p-2 font-semibold text-slate-950">
            Salvar regra
          </button>
        </form>
        <Historico
          items={regras.data ?? []}
          render={(r) => `${r.tipo_terapia} · ${r.frequencia_lancamento} · ${r.qtd_autorizada_por_ciclo}`}
          encerrar={(r) => enc(`/convenios/${id}/regras/${r.id}/encerrar`, [...k, 'regras'])}
        />
      </section>

      <section className={card}>
        <h3 className="text-lg font-semibold">Novo valor</h3>
        <form onSubmit={valor} className="mt-3 grid gap-3 md:grid-cols-3">
          <Select
            value={especialidade}
            onChange={(event) => {
              setEspecialidade(event.target.value)
              setProfissional('')
            }}
          >
            <option value="">Valor geral</option>
            {(esps.data ?? []).map((x) => (
              <option key={x.id} value={x.id}>
                {x.nome}
              </option>
            ))}
          </Select>
          <Select
            value={profissional}
            onChange={(event) => setProfissional(event.target.value)}
            disabled={!especialidade}
          >
            <option value="">Todos profissionais</option>
            {(profs.data ?? []).map((x) => (
              <option key={x.id} value={x.id}>
                {x.nome}
              </option>
            ))}
          </Select>
          <input name="valor" required type="number" step=".01" min="0" placeholder="Valor" className={field} />
          <input name="desde" required type="date" className={field} />
          <button className="rounded-xl bg-cyan-400 p-2 font-semibold text-slate-950">
            Salvar valor
          </button>
        </form>
        <Historico
          items={valores.data ?? []}
          render={(v) => `R$ ${v.valor}`}
          encerrar={(v) => enc(`/convenios/${id}/valores/${v.id}/encerrar`, [...k, 'valores'])}
        />
      </section>
    </div>
  )
}

function Historico<T extends { id: number; vigente_desde: string; vigente_ate: string | null }>({
  items,
  render,
  encerrar,
}: {
  items: T[]
  render: (item: T) => string
  encerrar: (item: T) => void
}) {
  return (
    <div className="mt-4 space-y-2">
      {items.map((item) => (
        <div key={item.id} className={item.vigente_ate ? 'text-slate-500 line-through' : 'text-white'}>
          {render(item)} · {item.vigente_desde}
          {item.vigente_ate ? (
            ` até ${item.vigente_ate}`
          ) : (
            <button type="button" onClick={() => encerrar(item)} className="ml-3 text-rose-200">
              Encerrar
            </button>
          )}
        </div>
      ))}
    </div>
  )
}
