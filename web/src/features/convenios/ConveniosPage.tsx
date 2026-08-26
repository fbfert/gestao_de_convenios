import { useQuery, useQueryClient } from '@tanstack/react-query'
import { useEffect, useMemo, useRef, useState, type FormEvent } from 'react'
import { Link, useMatch, useNavigate, useParams } from 'react-router-dom'
import { apiClient } from '../../api/client'
import { Select } from '../../components/ui/Select'
import { Tooltip } from '../../components/ui/Tooltip'
import { getHttpErrorMessage } from '../../lib/httpError'
import { useEspecialidades, useProfissionais } from '../../lib/queries/useReferenceData'
import { UNIMED_BLOCK_SIZES, formatBlocos, parseBlocos } from '../../lib/carteirinha'

type Convenio = {
  id: number
  nome: string
  descricao: string | null
  connector_type: string
  /** Tamanhos dos blocos da carteirinha. null = texto livre. */
  carteirinha_blocos?: number[] | null
  ativo: boolean
}

type ConvenioForm = {
  nome: string
  descricao: string
  connector_type: string
  /** Blocos como o operador digita: "4-4-6-2-1". Vazio = texto livre. */
  carteirinha: string
  ativo: boolean
}

const emptyForm: ConvenioForm = {
  nome: '',
  descricao: '',
  connector_type: 'manual',
  carteirinha: '',
  ativo: true,
}

function toForm(convenio: Convenio): ConvenioForm {
  return {
    nome: convenio.nome,
    descricao: convenio.descricao ?? '',
    connector_type: convenio.connector_type,
    carteirinha: formatBlocos(convenio.carteirinha_blocos),
    ativo: convenio.ativo,
  }
}

/**
 * Texto da dica do campo Conector. O aviso sobre API/Scraping nao e detalhe de
 * roadmap: o ConnectorResolver so implementa `manual`, entao um convenio nas
 * outras opcoes derruba a verificacao diaria de guias.
 */
function ExplicacaoConector() {
  return (
    <>
      <p className="font-semibold text-white">Como o sistema fala com a operadora</p>
      <dl className="mt-2 space-y-2">
        <div>
          <dt className="font-semibold text-cyan-100">Manual</dt>
          <dd>
            Sem integração. A verificação diária só registra que a guia precisa de conferência no
            portal da operadora. É o padrão e atende a maioria dos convênios.
          </dd>
        </div>
        <div>
          <dt className="font-semibold text-cyan-100">API</dt>
          <dd>
            Reservado para operadora com API oficial. Ainda não implementado — deixar um convênio
            nesta opção faz a verificação diária de guias falhar.
          </dd>
        </div>
        <div>
          <dt className="font-semibold text-cyan-100">Scraping</dt>
          <dd>
            Robô que navega no portal da operadora. Hoje existe só para a Unimed, e quem liga é a
            tela Configurações → Unimed, não este campo. Marcar aqui, sozinho, tem o mesmo efeito
            da opção API.
          </dd>
        </div>
      </dl>
    </>
  )
}

/** Formato da carteirinha do convenio, com o atalho da Unimed ao lado. */
function CampoCarteirinha({
  valor,
  onChange,
}: {
  valor: string
  onChange: (valor: string) => void
}) {
  return (
    <label className="space-y-1 md:col-span-2">
      <span className="text-meta text-slate-300">Formato da carteirinha</span>
      <div className="flex gap-2">
        <input
          value={valor}
          onChange={(event) => onChange(event.target.value)}
          placeholder="Em branco = texto livre"
          className={field}
          data-testid="convenio-carteirinha-blocos"
        />
        <button
          type="button"
          onClick={() => onChange(formatBlocos([...UNIMED_BLOCK_SIZES]))}
          className="shrink-0 rounded-xl border border-white/10 bg-white/5 px-3 text-meta font-semibold text-slate-200 transition hover:bg-white/10"
        >
          Unimed
        </button>
      </div>
      <span className="block text-meta text-slate-400">
        Tamanhos dos blocos separados por hifen, ex.: 4-4-6-2-1. Em branco, a carteirinha e
        digitada em campo unico, sem validacao de tamanho.
      </span>
    </label>
  )
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
const card = 'rounded-superficie border border-linha bg-fundo p-5 shadow-e1'

export function ConveniosPage() {
  const navigate = useNavigate()
  const isCreateRoute = useMatch('/convenios/novo') !== null
  const editRouteMatch = useMatch('/convenios/:id/editar')
  const editingId = editRouteMatch ? Number(editRouteMatch.params.id) : null
  const isEditRoute = editingId !== null && Number.isInteger(editingId)
  // Criar e editar acontecem em tela propria: a lista junto do formulario
  // deixava o cadastro espremido e o contexto ambiguo entre os dois.
  const isFormRoute = isCreateRoute || isEditRoute
  const q = useQuery({
    queryKey: ['convenios'],
    queryFn: async () => (await apiClient.get<{ data: Convenio[] }>('/convenios')).data.data,
  })
  const qc = useQueryClient()
  const [form, setForm] = useState<ConvenioForm>(emptyForm)
  const [formError, setFormError] = useState<string | null>(null)
  const [salvando, setSalvando] = useState(false)
  const carregadoRef = useRef<number | null>(null)

  const convenios = useMemo(() => q.data ?? [], [q.data])
  const convenioEmEdicao = useMemo(
    () => (isEditRoute ? convenios.find((convenio) => convenio.id === editingId) ?? null : null),
    [isEditRoute, editingId, convenios],
  )

  // Preenche o formulario quando a tela de edicao e aberta direto pela URL ou
  // recarregada — nesses casos o clique em Editar nunca aconteceu. O ref evita
  // que um refetch em background sobrescreva o que o operador ja digitou.
  useEffect(() => {
    if (!isEditRoute) {
      carregadoRef.current = null
      return
    }

    if (!convenioEmEdicao || carregadoRef.current === convenioEmEdicao.id) {
      return
    }

    carregadoRef.current = convenioEmEdicao.id
    setForm(toForm(convenioEmEdicao))
    setFormError(null)
  }, [isEditRoute, convenioEmEdicao])

  const abrirNovo = () => {
    setForm(emptyForm)
    setFormError(null)
    carregadoRef.current = null
    navigate('/convenios/novo')
  }

  const fechar = () => {
    setForm(emptyForm)
    setFormError(null)
    carregadoRef.current = null
    navigate('/convenios')
  }

  const salvar = async (event: FormEvent) => {
    event.preventDefault()
    setFormError(null)
    setSalvando(true)

    const blocos = parseBlocos(form.carteirinha)
    const payload = {
      nome: form.nome.trim(),
      descricao: form.descricao,
      connector_type: form.connector_type,
      // Lista vazia e o mesmo que "sem formato": o backend normaliza, mas
      // mandar null deixa a intencao explicita no payload.
      carteirinha_blocos: blocos.length > 0 ? blocos : null,
      ativo: form.ativo,
    }

    try {
      if (isEditRoute && editingId) {
        await apiClient.patch(`/convenios/${editingId}`, payload)
      } else {
        await apiClient.post('/convenios', payload)
      }

      qc.invalidateQueries({ queryKey: ['convenios'] })
      fechar()
    } catch (error) {
      setFormError(getHttpErrorMessage(error, 'Não foi possível salvar o convênio.'))
    } finally {
      setSalvando(false)
    }
  }

  if (isFormRoute) {
    // Edicao de um convenio que nao esta na lista carregada (id inexistente ou
    // inativo): sem isso a tela abriria um formulario vazio que salvaria por cima.
    if (isEditRoute && !convenioEmEdicao) {
      return (
        <div className="space-y-5">
          <h2 className="text-display font-semibold">Editar convênio</h2>
          <section className={card}>
            {q.isLoading ? (
              <p className="inline-flex min-h-6 items-center text-corpo text-slate-300">Carregando convênio…</p>
            ) : (
              <>
                <p className="inline-flex min-h-6 items-center text-corpo text-slate-300">
                  Convênio não encontrado entre os convênios ativos desta clínica.
                </p>
                <Link to="/convenios" className="mt-3 inline-block text-cyan-200">
                  ← Voltar para a lista
                </Link>
              </>
            )}
          </section>
        </div>
      )
    }

    return (
      <div className="space-y-5">
        <div>
          <p className="text-meta uppercase tracking-[.3em] text-cyan-300">Convênios</p>
          <h2 className="mt-2 text-display font-semibold">
            {isEditRoute ? 'Editar convênio' : 'Novo convênio'}
          </h2>
          <p className="mt-2 text-corpo text-slate-300">
            {isEditRoute
              ? 'Alterações valem para toda a operação deste convênio. Regras e valores continuam na tela de configuração.'
              : 'Depois de salvar, abra o convênio para configurar regras de autorização e valores.'}
          </p>
        </div>

        <section className={card}>
          <form onSubmit={salvar} data-testid="convenio-form">
            <div className="grid gap-3 md:grid-cols-3">
              <input
                required
                value={form.nome}
                onChange={(event) => setForm((atual) => ({ ...atual, nome: event.target.value }))}
                placeholder="Nome"
                className={field}
                data-testid="convenio-nome"
              />
              <textarea
                value={form.descricao}
                onChange={(event) =>
                  setForm((atual) => ({ ...atual, descricao: event.target.value }))
                }
                placeholder="Descrição"
                className={field}
              />
              <div className="space-y-1">
                <span className="flex items-center gap-2 text-meta text-slate-300">
                  Conector
                  <Tooltip rotulo="O que são Manual, API e Scraping?">
                    <ExplicacaoConector />
                  </Tooltip>
                </span>
                <Select
                  value={form.connector_type}
                  onChange={(event) =>
                    setForm((atual) => ({ ...atual, connector_type: event.target.value }))
                  }
                >
                  <option value="manual">Manual</option>
                  <option value="api">API</option>
                  <option value="scraping">Scraping</option>
                </Select>
              </div>
              <CampoCarteirinha
                valor={form.carteirinha}
                onChange={(carteirinha) => setForm((atual) => ({ ...atual, carteirinha }))}
              />
              <label className="flex flex-col gap-2 sm:flex-row sm:items-center">
                <input
                  type="checkbox"
                  checked={form.ativo}
                  onChange={(event) => setForm((atual) => ({ ...atual, ativo: event.target.checked }))}
                />
                Ativo
              </label>
              <button
                disabled={salvando}
                className="rounded-xl bg-cyan-400 p-2 font-semibold text-slate-950 disabled:opacity-60"
              >
                {salvando ? 'Salvando…' : 'Salvar'}
              </button>
              <button type="button" onClick={fechar} data-testid="convenio-fechar">
                Cancelar
              </button>
            </div>

            {formError ? <p className="mt-3 text-corpo text-rose-200">{formError}</p> : null}
          </form>
        </section>
      </div>
    )
  }

  return (
    <div className="space-y-5">
      <div className="flex justify-between">
        <div>
          <p className="text-meta uppercase tracking-[.3em] text-cyan-300">Convênios</p>
          <h2 className="mt-2 text-display font-semibold">Gestão de convênios</h2>
          <p className="mt-2 text-corpo text-slate-300">
            Abra um convênio para configurar regras de autorização e valores de pagamento.
          </p>
        </div>
        <button
          onClick={abrirNovo}
          className="rounded-xl bg-cyan-400 px-4 py-2 font-semibold text-slate-950"
          data-testid="convenio-novo"
        >
          Novo convênio
        </button>
      </div>

      {convenios.map((c) => (
        <div key={c.id} className={`${card} flex items-center justify-between`}>
          <Link to={`/convenios/${c.id}`}>
            {c.nome}
            {c.descricao ? <p className="text-meta text-slate-400">{c.descricao}</p> : null}
          </Link>
          <div className="flex gap-3">
            <span>{c.ativo ? 'Ativo' : 'Inativo'}</span>
            <Link
              to={`/convenios/${c.id}/editar`}
              className="text-cyan-200"
              data-testid={`convenio-editar-${c.id}`}
            >
              Editar
            </Link>
          </div>
        </div>
      ))}
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
          className="rounded-xl border border-cyan-400/40 px-3 py-1.5 text-corpo font-semibold text-cyan-100 transition hover:bg-cyan-400/10"
        >
          Ajuda
        </Link>
      </div>

      <h2 className="text-display font-semibold">Configuração do convênio</h2>

      <section className={card}>
        <h3 className="text-subtitulo font-semibold">Nova regra</h3>
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
        <h3 className="text-subtitulo font-semibold">Novo valor</h3>
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
        <div key={item.id} className={item.vigente_ate ? 'text-texto-suave line-through' : 'text-white'}>
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
