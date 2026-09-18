import { useQuery } from '@tanstack/react-query'
import { apiClient } from '../../api/client'
import { Select } from '../../components/ui/Select'
import { useConvenios, useEspecialidades, useProfissionais } from '../../lib/queries/useReferenceData'
import { useAuthStore } from '../../stores/authStore'
import { formatarDia } from './formato'
import { PRESETS, ROTULO_DO_PRESET, type FiltrosRelatorio as Filtros, type Preset } from './filtros'

/**
 * A barra de filtros: vale para as quatro abas.
 *
 * Os filtros são compartilhados de propósito. Trocar de aba mantendo o período
 * é o gesto natural de quem investiga um número ("a negação subiu — o que a
 * automação fez nesse mês?"), e refiltrar a cada aba quebraria a investigação.
 */
export function FiltrosRelatorio({
  filtros,
  aplicar,
  aplicarPreset,
  filtrosAplicados,
  carregando,
}: {
  filtros: Filtros
  aplicar: (parciais: Partial<Filtros>) => void
  aplicarPreset: (preset: Preset) => void
  /** O que a aba aberta REALMENTE usou — a API devolve isso na resposta. */
  filtrosAplicados: Record<string, number>
  carregando: boolean
}) {
  const superAdmin = useAuthStore((estado) => estado.user?.super_admin === true)

  const convenios = useConvenios()
  const especialidades = useEspecialidades()
  const profissionais = useProfissionais()
  const clinicas = useClinicas(superAdmin)

  const ignorado = (chave: string) =>
    Boolean(filtros[chave as keyof Filtros]) && filtrosAplicados[chave] === undefined

  return (
    <section
      className="space-y-4 rounded-janela border border-linha bg-superficie p-4 shadow-e1"
      data-testid="filtros-relatorio"
    >
      <div className="flex flex-wrap items-center gap-2">
        {PRESETS.map((preset) => (
          <button
            key={preset}
            type="button"
            onClick={() => aplicarPreset(preset)}
            aria-pressed={filtros.preset === preset}
            data-testid={`preset-${preset}`}
            className={`rounded-pilula border px-3 py-1.5 text-rotulo transition ${
              filtros.preset === preset
                ? 'border-acento bg-acento text-sobre-acento'
                : 'border-linha bg-superficie text-texto-suave hover:bg-fundo'
            }`}
          >
            {ROTULO_DO_PRESET[preset]}
          </button>
        ))}

        <span className="ml-auto text-meta text-texto-suave" data-testid="periodo-escolhido">
          {formatarDia(filtros.de)} a {formatarDia(filtros.ate)}
          {carregando ? ' · atualizando…' : ''}
        </span>
      </div>

      {filtros.preset === 'personalizado' ? (
        <div className="flex flex-wrap items-end gap-3">
          <Campo rotulo="De">
            <input
              type="date"
              value={filtros.de}
              onChange={(evento) => aplicar({ de: evento.target.value })}
              data-testid="filtro-de"
              className="h-10 rounded-campo border border-borda-campo bg-superficie px-3 text-corpo text-texto"
            />
          </Campo>
          <Campo rotulo="Até">
            <input
              type="date"
              value={filtros.ate}
              onChange={(evento) => aplicar({ ate: evento.target.value })}
              data-testid="filtro-ate"
              className="h-10 rounded-campo border border-borda-campo bg-superficie px-3 text-corpo text-texto"
            />
          </Campo>
        </div>
      ) : null}

      <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <Campo rotulo="Convênio" avisoIgnorado={ignorado('convenio_id')}>
          <Select
            value={filtros.convenio_id}
            onChange={(evento) => aplicar({ convenio_id: evento.target.value })}
            data-testid="filtro-convenio"
          >
            <option value="">Todos</option>
            {(convenios.data ?? []).map((convenio) => (
              <option key={convenio.id} value={String(convenio.id)}>
                {convenio.nome}
              </option>
            ))}
          </Select>
        </Campo>

        <Campo rotulo="Especialidade" avisoIgnorado={ignorado('especialidade_id')}>
          <Select
            value={filtros.especialidade_id}
            onChange={(evento) => aplicar({ especialidade_id: evento.target.value })}
            data-testid="filtro-especialidade"
          >
            <option value="">Todas</option>
            {(especialidades.data ?? []).map((especialidade) => (
              <option key={especialidade.id} value={String(especialidade.id)}>
                {especialidade.nome}
              </option>
            ))}
          </Select>
        </Campo>

        <Campo rotulo="Profissional" avisoIgnorado={ignorado('profissional_id')}>
          <Select
            value={filtros.profissional_id}
            onChange={(evento) => aplicar({ profissional_id: evento.target.value })}
            data-testid="filtro-profissional"
          >
            <option value="">Todos</option>
            {(profissionais.data ?? []).map((profissional) => (
              <option key={profissional.id} value={String(profissional.id)}>
                {profissional.nome}
              </option>
            ))}
          </Select>
        </Campo>

        {superAdmin ? (
          <Campo rotulo="Clínica">
            <Select
              value={filtros.tenant_id}
              onChange={(evento) => aplicar({ tenant_id: evento.target.value })}
              data-testid="filtro-clinica"
            >
              {/* Vazio = a própria clínica. "todos" soma tudo — a API só aceita
                  esses dois de quem tem `super_admin`, e devolve 403 aos demais
                  mesmo que o parâmetro venha na URL. */}
              <option value="">Minha clínica</option>
              <option value="todos">Todas as clínicas</option>
              {(clinicas.data ?? []).map((clinica) => (
                <option key={clinica.id} value={String(clinica.id)}>
                  {clinica.nome}
                </option>
              ))}
            </Select>
          </Campo>
        ) : null}
      </div>

      <label className="flex w-fit items-center gap-2 text-corpo text-texto">
        <input
          type="checkbox"
          checked={filtros.comparar === '1'}
          onChange={(evento) => aplicar({ comparar: evento.target.checked ? '1' : '' })}
          data-testid="filtro-comparar"
          className="size-4 accent-acento"
        />
        Comparar com o período anterior
      </label>
    </section>
  )
}

function Campo({
  rotulo,
  avisoIgnorado = false,
  children,
}: {
  rotulo: string
  /**
   * O filtro foi escolhido mas a aba não o usa.
   *
   * Dizer isso é o ponto: sem o aviso, quem filtra por profissional na aba de
   * Automações lê o número como se estivesse recortado, e ele não está — a
   * execução não tem profissional. A API informa quais filtros de fato aplicou.
   */
  avisoIgnorado?: boolean
  children: React.ReactNode
}) {
  return (
    <div className="space-y-1">
      <p className="text-rotulo text-texto-suave">{rotulo}</p>
      {children}
      {avisoIgnorado ? (
        <p className="text-meta text-alerta-texto">Não se aplica a esta aba</p>
      ) : null}
    </div>
  )
}

type ClinicaRef = { id: number; nome: string }

/** A lista de clínicas só existe para super admin — a rota é `super-admin` na API. */
function useClinicas(habilitado: boolean) {
  return useQuery({
    queryKey: ['tenants', 'referencia'],
    enabled: habilitado,
    staleTime: 5 * 60 * 1000,
    queryFn: async () => (await apiClient.get<{ data: ClinicaRef[] }>('/tenants')).data.data,
  })
}
