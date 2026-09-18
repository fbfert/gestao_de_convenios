import { useState } from 'react'
import { Navigate } from 'react-router-dom'
import { Tabs } from 'radix-ui'
import { AvisoErro } from '../../components/ui/AvisoErro'
import { getHttpErrorMessage } from '../../lib/httpError'
import { usePode } from '../../lib/permissoes'
import { baixarTabela, useRelatorio } from './api'
import { FiltrosRelatorio } from './FiltrosRelatorio'
import { useFiltrosNaUrl } from './filtros'
import { KpiTile } from './KpiTile'
import { TabelaRelatorio } from './TabelaRelatorio'
import { AbaAutomacoes } from './abas/AbaAutomacoes'
import { AbaFinanceiro } from './abas/AbaFinanceiro'
import { AbaOperacao } from './abas/AbaOperacao'
import { AbaUso } from './abas/AbaUso'
import { ABAS, PERMISSAO_DA_ABA, ROTULO_DA_ABA, type Aba, type Relatorio } from './tipos'

/**
 * A tela de relatórios: quatro abas sobre os mesmos filtros.
 *
 * Só as abas permitidas são montadas. Esconder aba é conveniência, não
 * segurança — quem barra de fato é o `permission:` da rota da API —, mas
 * oferecer uma aba que só devolve 403 é pior do que não oferecer.
 *
 * Sem nenhuma das quatro permissões, redireciona ao painel em vez de mostrar
 * uma tela vazia: quem chegou aqui por link não tem o que fazer nesta página.
 */
export function RelatoriosPage() {
  const pode = usePode()
  const permitidas = ABAS.filter((aba) => pode(PERMISSAO_DA_ABA[aba]))

  const { filtros, aplicar, aplicarPreset } = useFiltrosNaUrl()

  // A aba também vive na URL: o link compartilhado abre na mesma aba, e não na
  // primeira. Aba pedida sem permissão cai na primeira permitida.
  const pedida = filtros.aba as Aba | ''
  const abaAtual = permitidas.includes(pedida as Aba) ? (pedida as Aba) : permitidas[0]

  const consulta = useRelatorio(abaAtual ?? null, filtros)

  const [erro, setErro] = useState<string | null>(null)
  const [exportando, setExportando] = useState(false)

  if (permitidas.length === 0) {
    return <Navigate to="/dashboard" replace />
  }

  const relatorio = consulta.data

  const exportar = async (tabela: string, formato: 'csv' | 'xlsx') => {
    if (!abaAtual) return

    setExportando(true)
    setErro(null)

    try {
      await baixarTabela(abaAtual, tabela, formato, filtros)
    } catch (falha) {
      setErro(getHttpErrorMessage(falha, 'Não foi possível gerar o arquivo.'))
    } finally {
      setExportando(false)
    }
  }

  return (
    <div className="space-y-6" data-testid="relatorios-page">
      <header className="space-y-1">
        <h2 className="text-display font-semibold text-texto">Relatórios</h2>
        <p className="max-w-3xl text-corpo text-texto-suave">
          Os números da clínica por período, com comparação. O endereço desta página guarda o
          recorte — pode ser copiado e mandado para alguém.
        </p>
      </header>

      <FiltrosRelatorio
        filtros={filtros}
        aplicar={aplicar}
        aplicarPreset={aplicarPreset}
        filtrosAplicados={relatorio?.filtros_aplicados ?? {}}
        carregando={consulta.isFetching}
      />

      <AvisoErro mensagem={erro} onFechar={() => setErro(null)} />

      {consulta.isError ? (
        <AvisoErro
          mensagem={getHttpErrorMessage(consulta.error, 'Não foi possível carregar o relatório.')}
          onFechar={() => consulta.refetch()}
          testId="erro-relatorio"
        />
      ) : null}

      <Tabs.Root
        value={abaAtual}
        onValueChange={(valor) => aplicar({ aba: valor as Aba })}
        data-testid="relatorios-abas"
      >
        <Tabs.List className="flex flex-wrap gap-1 border-b border-linha" aria-label="Abas do relatório">
          {permitidas.map((aba) => (
            <Tabs.Trigger
              key={aba}
              value={aba}
              data-testid={`aba-${aba}`}
              className="-mb-px border-b-2 border-transparent px-4 py-2 text-corpo text-texto-suave transition data-[state=active]:border-acento data-[state=active]:text-texto"
            >
              {ROTULO_DA_ABA[aba]}
            </Tabs.Trigger>
          ))}
        </Tabs.List>

        {permitidas.map((aba) => (
          <Tabs.Content key={aba} value={aba} className="space-y-6 pt-6">
            <Kpis relatorio={relatorio} carregando={consulta.isPending} />

            <Graficos aba={aba} relatorio={relatorio} carregando={consulta.isPending} />

            {(relatorio?.tabelas ?? []).map((tabela) => (
              <TabelaRelatorio
                key={tabela.key}
                tabela={tabela}
                exportando={exportando}
                onExportar={(formato) => exportar(tabela.key, formato)}
              />
            ))}
          </Tabs.Content>
        ))}
      </Tabs.Root>

      {relatorio ? <Rodape geradoEm={relatorio.gerado_em} cache={relatorio.cache} /> : null}
    </div>
  )
}

/**
 * Os gráficos da aba aberta.
 *
 * Um componente por aba, escolhido aqui: cada aba conta uma história diferente
 * e o arranjo dos gráficos é parte dela. O que é comum — KPI, tabela, filtro —
 * a página monta uma vez para as quatro.
 */
function Graficos({
  aba,
  relatorio,
  carregando,
}: {
  aba: Aba
  relatorio?: Relatorio
  carregando: boolean
}) {
  const props = { relatorio, carregando }

  switch (aba) {
    case 'operacao':
      return <AbaOperacao {...props} />
    case 'financeiro':
      return <AbaFinanceiro {...props} />
    case 'automacoes':
      return <AbaAutomacoes {...props} />
    case 'uso':
      return <AbaUso {...props} />
  }
}

function Kpis({
  relatorio,
  carregando,
}: {
  relatorio?: Relatorio
  carregando: boolean
}) {
  if (carregando) {
    return (
      <div className="grid grid-cols-2 gap-3 lg:grid-cols-4" data-testid="kpis-carregando">
        {Array.from({ length: 8 }, (_, i) => (
          <div key={i} className="h-24 animate-pulse rounded-janela bg-neutro-desativado" />
        ))}
      </div>
    )
  }

  const kpis = relatorio?.kpis ?? []

  if (kpis.length === 0) {
    return null
  }

  return (
    // Duas colunas no estreito, quatro a partir do desktop: quatro números lado
    // a lado num celular ficam ilegíveis, e um por linha vira rolagem infinita.
    <div className="grid grid-cols-2 gap-3 lg:grid-cols-4" data-testid="kpis">
      {kpis.map((kpi) => (
        <KpiTile key={kpi.key} kpi={kpi} />
      ))}
    </div>
  )
}

function Rodape({ geradoEm, cache }: { geradoEm: string; cache: boolean }) {
  const quando = new Date(geradoEm).toLocaleString('pt-BR', {
    day: '2-digit',
    month: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
  })

  return (
    <p className="text-meta text-texto-suave" data-testid="relatorio-gerado-em">
      Calculado em {quando}
      {cache ? ' · resultado reaproveitado dos últimos 5 minutos' : ''}
    </p>
  )
}
