<?php

namespace App\Services\ClinicaSync;

use App\Models\ClinicaPushPendencia;
use App\Models\Especialidade;
use App\Models\Profissional;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/**
 * Sincroniza profissionais entre gescon e clinica — via mão dupla (design
 * confirmado em 20/08/2026, ver docs/clinica-sync.md).
 *
 * Anti-loop: toda escrita que ESTA classe faz grava `sincronizado_em` igual a
 * `updated_at` (mesmo instante). Um registro só é elegível pra PUSH quando
 * `updated_at > sincronizado_em` — ou seja, mudou por uma mão HUMANA depois da
 * última sync. Isso evita round-trip infinito sem precisar de flag extra.
 */
class ProfissionalSyncService
{
    /** Mesmo corte de PedidoMedicoAiService/CarteirinhaAiService/PacienteSyncService: abaixo disso não propomos vínculo. */
    private const CONFIANCA_MINIMA = 90;

    private array $cboPorCodigo = [];

    public function __construct(
        private readonly ClinicaApiClient $api,
        private readonly int $tenantId,
    ) {}

    public function executar(): array
    {
        $this->carregarCbos();

        $remotos = $this->api->listarProfissionais();
        $pull = $this->pull($remotos);
        $push = $this->push($remotos);

        return ['pull' => $pull, 'push' => $push];
    }

    private function carregarCbos(): void
    {
        foreach ($this->api->listarCbos() as $cbo) {
            $this->cboPorCodigo[$cbo['codigo']] = $cbo['id'];
        }
    }

    /** @param  list<array<string, mixed>>  $remotos */
    private function pull(array $remotos): array
    {
        $resumo = ['criados' => 0, 'atualizados' => 0, 'ignorados' => 0, 'pendentes' => []];

        foreach ($remotos as $remoto) {
            $remotoAtualizadoEm = CarbonImmutable::parse($remoto['updated_at']);

            $local = Profissional::where('tenant_id', $this->tenantId)
                ->where('clinica_id', $remoto['id'])
                ->first();

            if ($local === null) {
                // Ainda sem vínculo: tenta casar por nome entre os não-vinculados antes
                // de criar duplicado (não há CPF em profissional pra casar com certeza).
                $candidatos = Profissional::where('tenant_id', $this->tenantId)
                    ->whereNull('clinica_id')
                    ->whereRaw('LOWER(nome) = ?', [Str::lower($remoto['nome'])])
                    ->get();

                if ($candidatos->count() > 1) {
                    $resumo['pendentes'][] = "Profissional '{$remoto['nome']}' (clinica_id={$remoto['id']}): ambíguo, ".
                        "{$candidatos->count()} profissionais locais com o mesmo nome não vinculados.";

                    continue;
                }

                $local = $candidatos->first();
            }

            if ($local !== null) {
                // Já sincronizado com essa MESMA versão do clinica — reescrever de novo
                // seria ruído puro (e audit log novo a cada 5 min pra sempre).
                if ($local->sincronizado_em !== null && $local->sincronizado_em->eq($remotoAtualizadoEm)) {
                    $resumo['ignorados']++;

                    continue;
                }

                // Só sobrescreve se o lado do clinica mudou DEPOIS da nossa última cópia
                // local — se um humano mexeu no gescon nesse meio tempo, aquela edição
                // vence e sai no push.
                if ($local->updated_at !== null && $local->updated_at->gt($remotoAtualizadoEm)) {
                    $resumo['ignorados']++;

                    continue;
                }

                $dados = $this->paraGescon($remoto, $resumo['pendentes']);
                if ($dados === null) {
                    continue;
                }

                $local->timestamps = false;
                $local->forceFill([
                    ...$dados,
                    'clinica_id' => $remoto['id'],
                    'updated_at' => $remotoAtualizadoEm,
                    'sincronizado_em' => $remotoAtualizadoEm,
                ])->save();

                $resumo['atualizados']++;

                continue;
            }

            $dados = $this->paraGescon($remoto, $resumo['pendentes']);
            if ($dados === null) {
                continue;
            }

            $novo = new Profissional([...$dados, 'tenant_id' => $this->tenantId]);
            $novo->clinica_id = $remoto['id'];
            $novo->timestamps = false;
            $novo->updated_at = $remotoAtualizadoEm;
            $novo->sincronizado_em = $remotoAtualizadoEm;
            $novo->created_at = $remotoAtualizadoEm;
            $novo->save();

            $resumo['criados']++;
        }

        return $resumo;
    }

    /**
     * Mapeia o payload do clinica pros campos do gescon. Retorna null (e registra a
     * pendência) quando a especialidade principal não tem CBO mapeado (Profissional
     * do gescon exige especialidade_id — não dá pra criar sem ela).
     */
    private function paraGescon(array $remoto, array &$pendentes): ?array
    {
        $primeiraEspecialidadeNome = $remoto['especialidades'][0]['nome'] ?? null;

        if ($primeiraEspecialidadeNome === null) {
            $pendentes[] = "Profissional '{$remoto['nome']}' (clinica_id={$remoto['id']}): sem especialidade no clinica, não é possível criar/atualizar no gescon (especialidade é obrigatória).";

            return null;
        }

        $especialidade = Especialidade::firstOrCreate(
            ['tenant_id' => $this->tenantId, 'nome' => $primeiraEspecialidadeNome],
            ['ativo' => true],
        );

        $registro = trim(($remoto['conselho'] ?? '').' '.($remoto['num_conselho'] ?? ''));

        return [
            'nome' => $remoto['nome'],
            'especialidade_id' => $especialidade->id,
            'conselho_registro' => $registro !== '' ? $registro : null,
            'ativo' => (bool) $remoto['ativo'],
        ];
    }

    /** @param  list<array<string, mixed>>  $remotos */
    private function push(array $remotos): array
    {
        $resumo = ['criados' => 0, 'atualizados' => 0, 'pendentes' => []];

        $pendentesDePush = Profissional::where('tenant_id', $this->tenantId)
            ->where(function ($q) {
                $q->whereNull('sincronizado_em')->orWhereColumn('updated_at', '>', 'sincronizado_em');
            })
            ->get();

        $jaVinculados = Profissional::where('tenant_id', $this->tenantId)->whereNotNull('clinica_id')->pluck('clinica_id')->all();

        foreach ($pendentesDePush as $profissional) {
            $agora = CarbonImmutable::now();

            if ($profissional->clinica_id === null
                && $this->interceptarCriacaoSemMatchExato($profissional, $remotos, $jaVinculados, $resumo)) {
                continue;
            }

            $payload = $this->paraClinica($profissional, $resumo['pendentes']);

            try {
                if ($profissional->clinica_id === null) {
                    $criado = $this->api->criarProfissional($payload);
                    $profissional->clinica_id = $criado['id'] ?? $criado['data']['id'];
                    $resumo['criados']++;
                } else {
                    // If-Match = nossa última cópia do updated_at do clinica (sincronizado_em,
                    // gravado igual ao remoto na última sync). Se o clinica mudou nesse meio
                    // tempo, o servidor devolve 409 — recuamos, o próximo pull traz a versão
                    // nova e o humano decide o que prevalece.
                    $token = ($profissional->sincronizado_em ?? $agora)->toJSON();
                    $this->api->atualizarProfissional($profissional->clinica_id, $payload, $token);
                    $resumo['atualizados']++;
                }

                $profissional->timestamps = false;
                $profissional->forceFill(['sincronizado_em' => $agora, 'updated_at' => $agora])->save();
            } catch (\Throwable $e) {
                if (ClinicaApiClient::eConflito($e)) {
                    $resumo['pendentes'][] = "Profissional '{$profissional->nome}' (id={$profissional->id}): conflito de edição concorrente no clinica — será tentado de novo na próxima sync.";

                    continue;
                }

                $resumo['pendentes'][] = "Profissional '{$profissional->nome}' (id={$profissional->id}): falha ao enviar pro clinica — ".$e->getMessage();
            }
        }

        return $resumo;
    }

    /**
     * Antes de criar um profissional novo no clinica, verifica se não existe
     * lá alguém com nome parecido ainda não vinculado a nenhum profissional
     * local — evita duplicar do lado de lá. Achando candidato, abre
     * ClinicaPushPendencia em vez de criar; humano confirma (vincula) ou
     * rejeita (segue e cria normalmente na próxima rodada).
     *
     * @param  list<array<string, mixed>>  $remotos
     * @param  array<int, int>  $jaVinculados
     */
    private function interceptarCriacaoSemMatchExato(Profissional $profissional, array $remotos, array $jaVinculados, array &$resumo): bool
    {
        $pendencia = ClinicaPushPendencia::where('tenant_id', $this->tenantId)
            ->where('tipo', 'profissional')
            ->where('local_id', $profissional->id)
            ->first();

        if ($pendencia !== null && $pendencia->status === 'pendente') {
            $resumo['pendentes'][] = "Profissional '{$profissional->nome}' (id={$profissional->id}): aguardando confirmação manual de vínculo em Configurações > Sincronização Clínica > Pendências de envio.";

            return true;
        }

        if ($pendencia !== null) {
            return false; // rejeitado antes — segue e cria normalmente
        }

        $needle = mb_strtolower(trim($profissional->nome));
        $candidatos = collect($remotos)
            ->reject(fn (array $r) => in_array((int) $r['id'], $jaVinculados, true))
            ->map(function (array $r) use ($needle) {
                similar_text($needle, mb_strtolower($r['nome']), $percent);

                return ['clinica_id' => (int) $r['id'], 'nome' => $r['nome'], 'similaridade' => round($percent, 1)];
            })
            ->filter(fn (array $item) => $item['similaridade'] >= self::CONFIANCA_MINIMA)
            ->sortByDesc('similaridade')
            ->take(5)
            ->values()
            ->all();

        if ($candidatos === []) {
            return false;
        }

        ClinicaPushPendencia::create([
            'tenant_id' => $this->tenantId,
            'tipo' => 'profissional',
            'local_id' => $profissional->id,
            'candidatos_json' => $candidatos,
            'status' => 'pendente',
        ]);

        $resumo['pendentes'][] = "Profissional '{$profissional->nome}' (id={$profissional->id}): ".count($candidatos)." candidato(s) parecido(s) já cadastrado(s) no clinica — revisar em Configurações > Sincronização Clínica > Pendências de envio.";

        return true;
    }

    private function paraClinica(Profissional $profissional, array &$pendentes): array
    {
        $payload = [
            'nome' => $profissional->nome,
            'ativo' => $profissional->ativo,
        ];

        $nomeEspecialidade = $profissional->especialidade?->nome;
        $codigoCbo = $nomeEspecialidade !== null ? EspecialidadeCboMapa::codigoCboDe($nomeEspecialidade) : null;
        $cbosId = $codigoCbo !== null ? ($this->cboPorCodigo[$codigoCbo] ?? null) : null;

        if ($cbosId !== null) {
            $payload['cbos_id'] = $cbosId;
        } elseif ($nomeEspecialidade !== null) {
            $pendentes[] = "Profissional '{$profissional->nome}': especialidade '{$nomeEspecialidade}' sem CBO mapeado (ver EspecialidadeCboMapa) — criado sem cbos_id no clinica.";
        }

        // 'especialidades' (as do clinica, distintas do CBO) NÃO entra: omitido, o
        // update do clinica preserva o que já estiver lá (array_key_exists check no
        // controller) — mandar [] apagaria vínculos feitos manualmente na tela.

        // conselho/num_conselho NÃO entram: o clinica exige uf_conselho junto (trio),
        // e o gescon não guarda o estado do registro — ficaria 422. Quem completar o
        // cadastro no clinica preenche os três lá.
        return $payload;
    }
}
