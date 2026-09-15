import { useEffect, useMemo, useState, type FormEvent } from 'react'
import { Badge } from '../../components/ui/Badge'
import { Botao } from '../../components/ui/Botao'
import { useEspecialidades, useProfissionais } from '../../lib/queries/useReferenceData'
import {
  getHttpErrorMessage,
  segredoPreenchido,
  useConvenioCredenciais,
  useConvenioWorkerHealth,
  useEspecialidadeMapeamentos,
  useProfissionalMapeamentos,
  useReativarConvenio,
  useSalvarConvenioCredencial,
  useSalvarEspecialidadeMapeamento,
  useSalvarProfissionalMapeamento,
  valorDoCampo,
  type CampoDoDriver,
  type ConvenioComCredencial,
  type EspecialidadeMapeamentoForm,
  type ProfissionalMapeamentoForm,
} from './useConvenioCredenciais'

function inputClasses() {
  return 'w-full rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-corpo text-white outline-none transition placeholder:text-texto-suave focus:border-cyan-300/70 focus:ring-2 focus:ring-cyan-300/20'
}

const especialidadeVazia: EspecialidadeMapeamentoForm = {
  especialidade_id: '',
  codigo_procedimento: '',
  descricao_operadora: '',
  quantidade_padrao: '10',
  usa_descricao_generica: false,
  valor_generico: '',
  ativo: true,
}

const profissionalVazio: ProfissionalMapeamentoForm = {
  profissional_id: '',
  codigo_operadora: '',
  nome_operadora: '',
  ativo: true,
}

/**
 * Credenciais de automação, uma por convênio.
 *
 * Substitui a aba "Unimed RDA" de Configurações, que era única por tenant e
 * trazia o nome do fornecedor em tudo — controller, hook, rota, permissão e
 * aba. Aqui o convênio é escolhido no topo e o formulário é renderizado a
 * partir do catálogo que a API devolve, então um convênio novo entra sem tela
 * nova.
 *
 * Nada aqui liga automação. Quem decide se uma Solicitação ou Guia entra em
 * fluxo automatizado continua sendo o conector do convênio, que se troca no
 * cadastro de Convênios — cadastrar credencial do SC Saúde não pode acionar uma
 * automação que ainda não existe.
 */
export function ConfiguracoesConveniosPage() {
  const credenciaisQuery = useConvenioCredenciais()
  const salvar = useSalvarConvenioCredencial()
  const reativar = useReativarConvenio()

  const [convenioId, setConvenioId] = useState<number | null>(null)
  const [driver, setDriver] = useState('')
  const [valores, setValores] = useState<Record<string, string>>({})
  const [message, setMessage] = useState<string | null>(null)
  const [error, setError] = useState<string | null>(null)

  const workerHealth = useConvenioWorkerHealth(convenioId)

  const especialidadesQuery = useEspecialidades()
  const profissionaisQuery = useProfissionais({ incluir_inativos: true })
  const especialidadeMapeamentos = useEspecialidadeMapeamentos(convenioId)
  const profissionalMapeamentos = useProfissionalMapeamentos(convenioId)
  const salvarEspecialidade = useSalvarEspecialidadeMapeamento()
  const salvarProfissional = useSalvarProfissionalMapeamento()

  const [especialidadeForm, setEspecialidadeForm] =
    useState<EspecialidadeMapeamentoForm>(especialidadeVazia)
  const [profissionalForm, setProfissionalForm] =
    useState<ProfissionalMapeamentoForm>(profissionalVazio)
  const [editandoEspecialidadeId, setEditandoEspecialidadeId] = useState<number | undefined>()
  const [editandoProfissionalId, setEditandoProfissionalId] = useState<number | undefined>()

  const especialidades = useMemo(
    () => especialidadesQuery.data ?? [],
    [especialidadesQuery.data],
  )
  const profissionais = useMemo(() => profissionaisQuery.data ?? [], [profissionaisQuery.data])

  const convenios = useMemo(() => credenciaisQuery.data?.data ?? [], [credenciaisQuery.data])
  const drivers = useMemo(() => credenciaisQuery.data?.meta.drivers ?? [], [credenciaisQuery.data])

  const selecionado: ConvenioComCredencial | undefined = useMemo(
    () => convenios.find((item) => item.convenio.id === convenioId),
    [convenios, convenioId],
  )

  const catalogo = useMemo(
    () => drivers.find((item) => item.driver === driver) ?? selecionado?.catalogo,
    [drivers, driver, selecionado],
  )

  // Primeiro convênio da lista assim que ela chega, para a tela não abrir vazia.
  useEffect(() => {
    if (convenioId === null && convenios.length > 0) {
      setConvenioId(convenios[0].convenio.id)
    }
  }, [convenios, convenioId])

  // Ao trocar de convênio, o formulário passa a refletir a credencial dele.
  // Campo secreto nasce vazio de propósito: em branco preserva o gravado.
  useEffect(() => {
    if (!selecionado) {
      return
    }

    setDriver(selecionado.driver ?? '')
    // Os de-para são do convênio: trocar de convênio abandona qualquer edição
    // em curso, em vez de salvá-la no convênio errado.
    limparEspecialidadeForm()
    limparProfissionalForm()
    setValores(
      Object.fromEntries(
        (selecionado.catalogo.campos ?? [])
          .filter((campo) => campo.tipo !== 'password')
          .map((campo) => [campo.chave, valorDoCampo(selecionado.credencial?.campos, campo.chave)]),
      ),
    )
    setMessage(null)
    setError(null)
  }, [selecionado])

  const alterarValor = (chave: string, valor: string) => {
    setValores((atual) => ({ ...atual, [chave]: valor }))
  }

  const handleSubmit = async (event: FormEvent) => {
    event.preventDefault()
    setMessage(null)
    setError(null)

    if (convenioId === null) {
      return
    }

    try {
      await salvar.mutateAsync({
        convenioId,
        payload: {
          driver,
          credenciais: Object.fromEntries(
            Object.entries(valores).filter(([, valor]) => valor.trim() !== ''),
          ),
        },
      })
      setMessage('Credencial salva.')
    } catch (submitError) {
      setError(getHttpErrorMessage(submitError, 'Não foi possível salvar a credencial.'))
    }
  }

  const limparEspecialidadeForm = () => {
    setEditandoEspecialidadeId(undefined)
    setEspecialidadeForm(especialidadeVazia)
  }

  const limparProfissionalForm = () => {
    setEditandoProfissionalId(undefined)
    setProfissionalForm(profissionalVazio)
  }

  const handleEspecialidadeSubmit = async (event: FormEvent) => {
    event.preventDefault()
    setMessage(null)
    setError(null)

    if (convenioId === null) {
      return
    }

    try {
      await salvarEspecialidade.mutateAsync({
        convenioId,
        id: editandoEspecialidadeId,
        payload: especialidadeForm,
      })
      setMessage('De-para de especialidade salvo.')
      limparEspecialidadeForm()
    } catch (submitError) {
      setError(getHttpErrorMessage(submitError, 'Não foi possível salvar o de-para de especialidade.'))
    }
  }

  const handleProfissionalSubmit = async (event: FormEvent) => {
    event.preventDefault()
    setMessage(null)
    setError(null)

    if (convenioId === null) {
      return
    }

    try {
      await salvarProfissional.mutateAsync({
        convenioId,
        id: editandoProfissionalId,
        payload: profissionalForm,
      })
      setMessage('De-para de profissional salvo.')
      limparProfissionalForm()
    } catch (submitError) {
      setError(getHttpErrorMessage(submitError, 'Não foi possível salvar o de-para de profissional.'))
    }
  }

  const handleReativar = async () => {
    if (convenioId === null) {
      return
    }

    setMessage(null)
    setError(null)

    try {
      await reativar.mutateAsync(convenioId)
      setMessage('Automação reativada para este convênio.')
    } catch (reativarError) {
      setError(getHttpErrorMessage(reativarError, 'Não foi possível reativar a automação.'))
    }
  }

  if (credenciaisQuery.isLoading) {
    return (
      <p className="text-corpo text-slate-300" data-testid="convenios-credenciais-carregando">
        Carregando convênios…
      </p>
    )
  }

  return (
    <div className="space-y-6" data-testid="configuracoes-convenios-page">
      <section className="space-y-2">
        <p className="text-meta uppercase tracking-[0.3em] text-cyan-300/80">Configurações</p>
        <h2 className="text-display font-semibold text-white">Convênios e credenciais</h2>
        <p className="max-w-3xl text-corpo leading-6 text-slate-300">
          Credenciais que a automação usa para entrar no portal de cada convênio. Guardar uma
          credencial aqui não liga a automação: quem decide isso é o conector do convênio, no
          cadastro de Convênios.
        </p>
      </section>

      <div className="space-y-2">
        <label className="block text-meta uppercase tracking-[0.2em] text-slate-300" htmlFor="convenio">
          Convênio
        </label>
        <select
          id="convenio"
          className={inputClasses()}
          value={convenioId ?? ''}
          onChange={(event) => setConvenioId(event.target.value ? Number(event.target.value) : null)}
          data-testid="convenios-credenciais-seletor"
        >
          {convenios.map(({ convenio, credencial }) => (
            <option key={convenio.id} value={convenio.id}>
              {convenio.nome}
              {credencial?.pronta ? ' · credencial ativa' : ''}
            </option>
          ))}
        </select>
      </div>

      {selecionado ? (
        <form onSubmit={handleSubmit} className="space-y-6">
          <section className="space-y-4 rounded-2xl border border-white/10 bg-white/5 p-6">
            <div className="flex flex-wrap items-center justify-between gap-3">
              <h3 className="text-subtitulo font-semibold text-white">
                {selecionado.convenio.nome}
              </h3>
              <div className="flex items-center gap-2">
                <Badge tone={selecionado.convenio.connector_driver ? 'sucesso' : 'neutro'}>
                  {selecionado.convenio.connector_driver
                    ? 'Automação ligada'
                    : 'Fluxo manual'}
                </Badge>
                {selecionado.credencial ? (
                  <Badge tone={selecionado.credencial.pronta ? 'sucesso' : 'alerta'}>
                    {selecionado.credencial.pronta ? 'Credencial pronta' : 'Credencial incompleta'}
                  </Badge>
                ) : null}
              </div>
            </div>

            <div className="space-y-2">
              <label className="block text-meta uppercase tracking-[0.2em] text-slate-300" htmlFor="driver">
                Driver da credencial
              </label>
              <select
                id="driver"
                className={inputClasses()}
                value={driver}
                onChange={(event) => setDriver(event.target.value)}
                data-testid="convenios-credenciais-driver"
              >
                <option value="">Selecione…</option>
                {drivers.map((item) => (
                  <option key={item.driver ?? ''} value={item.driver ?? ''}>
                    {item.rotulo}
                  </option>
                ))}
              </select>
            </div>

            {/*
              Driver sem campos — o SC Saúde enquanto a autenticação não é
              definida — mostra o aviso e não renderiza formulário. Inventar
              login e senha faria o operador preencher achando que liga algo.
            */}
            {catalogo && !catalogo.implementado ? (
              <p
                className="rounded-2xl border border-amber-300/30 bg-amber-300/10 px-4 py-3 text-corpo text-amber-100"
                data-testid="convenios-credenciais-aviso-pendente"
              >
                {catalogo.aviso ?? 'A forma de autenticação deste convênio ainda não foi definida.'}
              </p>
            ) : null}

            {(catalogo?.campos ?? []).map((campo: CampoDoDriver) => (
              <div key={campo.chave} className="space-y-2">
                <label
                  className="block text-meta uppercase tracking-[0.2em] text-slate-300"
                  htmlFor={`campo-${campo.chave}`}
                >
                  {campo.rotulo}
                  {campo.obrigatorio ? ' *' : ''}
                </label>
                <input
                  id={`campo-${campo.chave}`}
                  className={inputClasses()}
                  type={campo.tipo === 'password' ? 'password' : 'text'}
                  value={valores[campo.chave] ?? ''}
                  onChange={(event) => alterarValor(campo.chave, event.target.value)}
                  placeholder={
                    campo.tipo === 'password' &&
                    segredoPreenchido(selecionado.credencial?.campos, campo.chave)
                      ? 'Já configurada — deixe em branco para manter'
                      : ''
                  }
                  data-testid={`convenios-credenciais-campo-${campo.chave}`}
                />
                {campo.dica ? <p className="text-meta text-slate-400">{campo.dica}</p> : null}
              </div>
            ))}
          </section>

          {selecionado.credencial?.automation_paused_at ? (
            <section
              className="space-y-3 rounded-2xl border border-rose-300/30 bg-rose-300/10 p-6"
              data-testid="convenios-credenciais-pausada"
            >
              <p className="text-corpo text-rose-100">
                A automação deste convênio está pausada
                {selecionado.credencial.automation_paused_reason
                  ? ` por ${selecionado.credencial.automation_paused_reason}`
                  : ''}
                . Os demais convênios seguem funcionando.
              </p>
              <Botao
                type="button"
                variante="secundario"
                onClick={handleReativar}
                carregando={reativar.isPending}
                data-testid="convenios-credenciais-reativar"
              >
                Reativar automação deste convênio
              </Botao>
            </section>
          ) : null}

          {message ? (
            <p className="text-corpo text-emerald-200" data-testid="convenios-credenciais-mensagem">
              {message}
            </p>
          ) : null}
          {error ? (
            <p className="text-corpo text-rose-200" data-testid="convenios-credenciais-erro">
              {error}
            </p>
          ) : null}

          <div className="flex flex-wrap items-center gap-3">
            <Botao type="submit" carregando={salvar.isPending} data-testid="convenios-credenciais-salvar">
              Salvar credencial
            </Botao>
            <Botao
              type="button"
              variante="secundario"
              onClick={() => workerHealth.refetch()}
              carregando={workerHealth.isFetching}
              data-testid="convenios-credenciais-health"
            >
              Testar worker
            </Botao>
            {workerHealth.data ? (
              <span className="text-corpo text-slate-300">
                {workerHealth.data.status === 'available'
                  ? 'Worker disponível.'
                  : workerHealth.data.status === 'not_applicable'
                    ? 'Este convênio não tem worker de automação.'
                    : 'Worker indisponível.'}
              </span>
            ) : null}
          </div>
        </form>
      ) : null}

      {/*
        De-para do convênio selecionado.

        O `convenio_id` sumiu do formulário: quem escolhe é o seletor do topo, e
        um de-para não existe fora de um convênio. Na aba antiga era mais um
        campo, que dava para deixar apontando para um convênio diferente do da
        credencial sendo editada.
      */}
      {selecionado ? (
        <section className="grid gap-6 xl:grid-cols-2" data-testid="convenios-credenciais-de-para">
          <div className="rounded-janela border border-linha bg-superficie-elevada p-6 shadow-e2">
            <h3 className="text-subtitulo font-semibold text-white">Especialidade x Convênio</h3>
            <p className="mt-1 text-meta text-slate-400">
              Código do procedimento que a automação usa ao gerar guia deste convênio.
            </p>

            <form onSubmit={handleEspecialidadeSubmit} className="mt-4 grid gap-3">
              <select
                value={especialidadeForm.especialidade_id}
                onChange={(event) =>
                  setEspecialidadeForm((atual) => ({
                    ...atual,
                    especialidade_id: event.target.value,
                  }))
                }
                className={inputClasses()}
                data-testid="de-para-especialidade"
              >
                <option value="">Especialidade…</option>
                {especialidades.map((especialidade) => (
                  <option key={especialidade.id} value={especialidade.id}>
                    {especialidade.nome}
                  </option>
                ))}
              </select>
              <input
                value={especialidadeForm.codigo_procedimento}
                onChange={(event) =>
                  setEspecialidadeForm((atual) => ({
                    ...atual,
                    codigo_procedimento: event.target.value,
                  }))
                }
                className={inputClasses()}
                placeholder="Código procedimento"
                data-testid="de-para-codigo-procedimento"
              />
              <input
                type="number"
                min="1"
                value={especialidadeForm.quantidade_padrao}
                onChange={(event) =>
                  setEspecialidadeForm((atual) => ({
                    ...atual,
                    quantidade_padrao: event.target.value,
                  }))
                }
                className={inputClasses()}
                data-testid="de-para-quantidade"
              />
              <label className="flex items-center gap-2 text-corpo text-slate-200">
                <input
                  type="checkbox"
                  checked={especialidadeForm.usa_descricao_generica}
                  onChange={(event) =>
                    setEspecialidadeForm((atual) => ({
                      ...atual,
                      usa_descricao_generica: event.target.checked,
                    }))
                  }
                  data-testid="de-para-usa-descricao-generica"
                />
                Item genérico (a operadora exige descrição manual do procedimento)
              </label>
              <input
                value={especialidadeForm.descricao_operadora}
                onChange={(event) =>
                  setEspecialidadeForm((atual) => ({
                    ...atual,
                    descricao_operadora: event.target.value,
                  }))
                }
                className={inputClasses()}
                placeholder="Descrição do procedimento (nunca o código)"
                data-testid="de-para-descricao-operadora"
              />
              <input
                value={especialidadeForm.valor_generico}
                onChange={(event) =>
                  setEspecialidadeForm((atual) => ({ ...atual, valor_generico: event.target.value }))
                }
                className={inputClasses()}
                placeholder="Descrição alternativa (opcional — sobrepõe a de cima)"
                data-testid="de-para-valor-generico"
              />
              <Botao
                type="submit"
                disabled={
                  !especialidadeForm.especialidade_id || !especialidadeForm.codigo_procedimento
                }
                carregando={salvarEspecialidade.isPending}
                data-testid="de-para-especialidade-salvar"
              >
                {editandoEspecialidadeId ? 'Salvar alteração' : 'Salvar'}
              </Botao>
              {editandoEspecialidadeId ? (
                <Botao
                  type="button"
                  variante="secundario"
                  onClick={limparEspecialidadeForm}
                  data-testid="de-para-especialidade-cancelar"
                >
                  Cancelar edição
                </Botao>
              ) : null}
            </form>

            <div className="mt-4 space-y-2">
              {(especialidadeMapeamentos.data ?? []).map((mapeamento) => (
                <button
                  key={mapeamento.id}
                  type="button"
                  onClick={() => {
                    setEditandoEspecialidadeId(mapeamento.id)
                    setEspecialidadeForm({
                      especialidade_id: String(mapeamento.especialidade_id),
                      codigo_procedimento: mapeamento.codigo_procedimento,
                      descricao_operadora: mapeamento.descricao_operadora ?? '',
                      quantidade_padrao: String(mapeamento.quantidade_padrao),
                      usa_descricao_generica: mapeamento.usa_descricao_generica,
                      valor_generico: mapeamento.valor_generico ?? '',
                      ativo: mapeamento.ativo,
                    })
                  }}
                  className="block w-full rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-left text-corpo text-slate-200"
                >
                  {mapeamento.especialidade?.nome ?? mapeamento.especialidade_id} ·{' '}
                  {mapeamento.codigo_procedimento}
                  {mapeamento.descricao_operadora || mapeamento.valor_generico
                    ? ` · ${mapeamento.valor_generico || mapeamento.descricao_operadora}`
                    : mapeamento.usa_descricao_generica
                      ? ' · ⚠ sem descrição cadastrada'
                      : ''}
                </button>
              ))}
              {(especialidadeMapeamentos.data ?? []).length === 0 ? (
                <p className="text-corpo text-slate-400">
                  Nenhum de-para de especialidade neste convênio.
                </p>
              ) : null}
            </div>
          </div>

          <div className="rounded-janela border border-linha bg-superficie-elevada p-6 shadow-e2">
            <h3 className="text-subtitulo font-semibold text-white">Profissional x Convênio</h3>
            <p className="mt-1 text-meta text-slate-400">
              Código do profissional na operadora deste convênio.
            </p>

            <form onSubmit={handleProfissionalSubmit} className="mt-4 grid gap-3">
              <select
                value={profissionalForm.profissional_id}
                onChange={(event) =>
                  setProfissionalForm((atual) => ({ ...atual, profissional_id: event.target.value }))
                }
                className={inputClasses()}
                data-testid="de-para-profissional"
              >
                <option value="">Profissional…</option>
                {profissionais.map((profissional) => (
                  <option key={profissional.id} value={profissional.id}>
                    {profissional.nome}
                  </option>
                ))}
              </select>
              <input
                value={profissionalForm.codigo_operadora}
                onChange={(event) =>
                  setProfissionalForm((atual) => ({
                    ...atual,
                    codigo_operadora: event.target.value,
                  }))
                }
                className={inputClasses()}
                placeholder="Código operadora"
                data-testid="de-para-codigo-operadora"
              />
              <input
                value={profissionalForm.nome_operadora}
                onChange={(event) =>
                  setProfissionalForm((atual) => ({ ...atual, nome_operadora: event.target.value }))
                }
                className={inputClasses()}
                placeholder="Nome na operadora (opcional)"
                data-testid="de-para-nome-operadora"
              />
              <Botao
                type="submit"
                disabled={!profissionalForm.profissional_id || !profissionalForm.codigo_operadora}
                carregando={salvarProfissional.isPending}
                data-testid="de-para-profissional-salvar"
              >
                {editandoProfissionalId ? 'Salvar alteração' : 'Salvar'}
              </Botao>
              {editandoProfissionalId ? (
                <Botao
                  type="button"
                  variante="secundario"
                  onClick={limparProfissionalForm}
                  data-testid="de-para-profissional-cancelar"
                >
                  Cancelar edição
                </Botao>
              ) : null}
            </form>

            <div className="mt-4 space-y-2">
              {(profissionalMapeamentos.data ?? []).map((mapeamento) => (
                <button
                  key={mapeamento.id}
                  type="button"
                  onClick={() => {
                    setEditandoProfissionalId(mapeamento.id)
                    setProfissionalForm({
                      profissional_id: String(mapeamento.profissional_id),
                      codigo_operadora: mapeamento.codigo_operadora,
                      nome_operadora: mapeamento.nome_operadora ?? '',
                      ativo: mapeamento.ativo,
                    })
                  }}
                  className="block w-full rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-left text-corpo text-slate-200"
                >
                  {mapeamento.profissional?.nome ?? mapeamento.profissional_id} ·{' '}
                  {mapeamento.codigo_operadora}
                </button>
              ))}
              {(profissionalMapeamentos.data ?? []).length === 0 ? (
                <p className="text-corpo text-slate-400">
                  Nenhum de-para de profissional neste convênio.
                </p>
              ) : null}
            </div>
          </div>
        </section>
      ) : null}
    </div>
  )
}
