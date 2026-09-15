import { useEffect, useMemo, useState, type FormEvent } from 'react'
import { Badge } from '../../components/ui/Badge'
import { Botao } from '../../components/ui/Botao'
import { DeParaDoConvenio } from './DeParaDoConvenio'
import {
  getHttpErrorMessage,
  segredoPreenchido,
  useConvenioCredenciais,
  useConvenioWorkerHealth,
  useReativarConvenio,
  useSalvarConvenioCredencial,
  valorDoCampo,
  type CampoDoDriver,
  type ConvenioComCredencial,
} from './useConvenioCredenciais'

function inputClasses() {
  return 'w-full rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-corpo text-white outline-none transition placeholder:text-texto-suave focus:border-cyan-300/70 focus:ring-2 focus:ring-cyan-300/20'
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
      {convenioId !== null ? (
        <DeParaDoConvenio convenioId={convenioId} onMensagem={setMessage} onErro={setError} />
      ) : null}
    </div>
  )
}
