<?php

namespace App\Services\Alertas;

use App\Models\Alerta;
use App\Models\AlertaRegra;
use App\Scopes\TenantScope;
use App\Services\Alertas\NotificadorDeAlertas;
use Illuminate\Support\Facades\DB;

/**
 * Roda as regras ativas de um tenant: abre o que falta, atualiza o que mudou e
 * RESOLVE o que nao satisfaz mais a condicao.
 *
 * O fechamento automatico e requisito, e nao otimizacao: sem ele a central
 * acumula lixo em um mes e o time para de olhar — e central ignorada e pior que
 * central nenhuma, porque da a sensacao de que alguem esta vigiando.
 */
class AvaliadorDeAlertas
{
    public function __construct(
        private readonly ResolvedorDeRegras $resolvedor,
        private readonly NotificadorDeAlertas $notificador,
    ) {
    }

    /**
     * @return array{abertos: int, atualizados: int, resolvidos: int}
     */
    public function avaliarTenant(int $tenantId): array
    {
        $resumo = ['abertos' => 0, 'atualizados' => 0, 'resolvidos' => 0];

        $regras = AlertaRegra::query()
            ->withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tenantId)
            ->get();

        foreach ($regras as $regra) {
            $avaliador = $this->resolvedor->para($regra->chave);

            if (! $avaliador) {
                continue; // chave sem implementação registrada
            }

            // Regra desativada não gera nada — e resolve o que já estava aberto,
            // senão desligar a regra deixaria alertas órfãos para sempre.
            $desejados = $regra->ativo ? $avaliador->avaliar($tenantId, $regra) : [];

            $abertosAgora = $this->sincronizar($tenantId, $regra->chave, $desejados, $resumo);

            // Aviso imediato depois da transação de sincronização, e não dentro:
            // e-mail não pode segurar transação de banco, e uma falha de SMTP
            // não pode desfazer os alertas que acabaram de ser abertos.
            foreach ($abertosAgora as $alerta) {
                $this->notificador->notificarImediato($alerta, $regra);
            }
        }

        return $resumo;
    }

    /**
     * Compara o que DEVERIA estar aberto com o que ESTÁ, e acerta os dois lados.
     *
     * @return array<int, Alerta> os que acabaram de nascer, para o aviso imediato
     */
    private function sincronizar(int $tenantId, string $chave, array $desejados, array &$resumo): array
    {
        return DB::transaction(function () use ($tenantId, $chave, $desejados, &$resumo) {
            $nascidos = [];
            $abertos = Alerta::query()
                ->withoutGlobalScope(TenantScope::class)
                ->where('tenant_id', $tenantId)
                ->where('chave', $chave)
                ->aberto()
                ->get()
                ->keyBy(fn (Alerta $a) => $this->identidade($a->entidade, $a->entidade_id));

            $vistos = [];

            foreach ($desejados as $item) {
                $entidade = $item['entidade'] ?? null;
                $entidadeId = $item['entidade_id'] ?? null;
                $id = $this->identidade($entidade, $entidadeId);
                $vistos[$id] = true;

                $existente = $abertos->get($id);

                if ($existente) {
                    // Já está aberto: só atualiza o que pode ter mudado. Não
                    // mexe em `aberto_em` — é o "desde quando" que o operador usa.
                    $existente->fill([
                        'nivel' => $item['nivel'],
                        'titulo' => $item['titulo'],
                        'descricao' => $item['descricao'] ?? null,
                        'dados' => $item['dados'] ?? null,
                    ]);

                    if ($existente->isDirty()) {
                        $existente->save();
                        $resumo['atualizados']++;
                    }

                    continue;
                }

                $nascidos[] = Alerta::query()->create([
                    'tenant_id' => $tenantId,
                    'chave' => $chave,
                    'nivel' => $item['nivel'],
                    'titulo' => $item['titulo'],
                    'descricao' => $item['descricao'] ?? null,
                    'entidade' => $entidade,
                    'entidade_id' => $entidadeId,
                    'dados' => $item['dados'] ?? null,
                    'aberto_em' => now(),
                    'aberto_dedupe' => Alerta::DEDUPE_ABERTO,
                ]);
                $resumo['abertos']++;
            }

            foreach ($abertos as $id => $alerta) {
                if (isset($vistos[$id])) {
                    continue;
                }

                // `aberto_dedupe` volta a NULL: é o que libera a combinação para
                // um alerta futuro da mesma chave e entidade.
                $alerta->forceFill([
                    'resolvido_em' => now(),
                    'aberto_dedupe' => null,
                ])->save();
                $resumo['resolvidos']++;
            }

            return $nascidos;
        });
    }

    private function identidade(?string $entidade, ?int $entidadeId): string
    {
        return ($entidade ?? '-').'#'.($entidadeId ?? '-');
    }
}
