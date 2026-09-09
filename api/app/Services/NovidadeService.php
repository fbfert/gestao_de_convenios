<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

/**
 * Novidades sao ARQUIVOS, e nao linhas de tabela.
 *
 * `resources/novidades/AAAA-MM-DD-slug.md`, com frontmatter. O volume real e de
 * poucas por mes, escritas por quem faz o deploy, no mesmo commit da mudanca que
 * anunciam — uma tabela exigiria tela de admin, permissao e migracao, custo alto
 * para um problema que ainda nao existe. Quando publicar sem deploy virar dor
 * real, ai vira CRUD.
 */
class NovidadeService
{
    private const CHAVE_CACHE = 'novidades.listagem';

    /** Tipos aceitos no frontmatter. */
    private const TIPOS = ['manual', 'melhoria', 'correcao', 'aviso'];

    /**
     * @return array<int, array{slug: string, titulo: string, tipo: string, data: string, corpo: string}>
     */
    public function todas(): array
    {
        // Cacheado porque so muda em deploy: ler e parsear um diretorio a cada
        // abertura de dashboard seria I/O por requisicao a toa.
        return Cache::remember(self::CHAVE_CACHE, now()->addHour(), function () {
            $diretorio = resource_path('novidades');

            if (! is_dir($diretorio)) {
                return [];
            }

            $novidades = [];

            foreach (glob($diretorio.'/*.md') ?: [] as $caminho) {
                $novidade = $this->ler($caminho);

                if ($novidade !== null) {
                    $novidades[] = $novidade;
                }
            }

            // Da mais recente para a mais antiga; desempate pelo slug para a
            // ordem nao variar entre sistemas de arquivos.
            usort($novidades, fn ($a, $b) => [$b['data'], $b['slug']] <=> [$a['data'], $a['slug']]);

            return $novidades;
        });
    }

    /** @return array<int, array<string, mixed>> */
    public function ultimas(int $limite): array
    {
        return array_slice($this->todas(), 0, max(1, $limite));
    }

    public function esquecerCache(): void
    {
        Cache::forget(self::CHAVE_CACHE);
    }

    /**
     * Arquivo invalido e IGNORADO, e nao explode a listagem.
     *
     * Um frontmatter mal escrito num arquivo nao pode derrubar o dashboard
     * inteiro — o custo de errar aqui e alto demais para o beneficio de ser
     * rigoroso.
     */
    private function ler(string $caminho): ?array
    {
        $bruto = file_get_contents($caminho);

        if ($bruto === false || ! preg_match('/^---\R(.*?)\R---\R?(.*)$/s', $bruto, $partes)) {
            return null;
        }

        $meta = [];

        foreach (preg_split('/\R/', $partes[1]) as $linha) {
            if (! str_contains($linha, ':')) {
                continue;
            }

            [$chave, $valor] = explode(':', $linha, 2);
            $meta[trim($chave)] = trim($valor, " \t\"'");
        }

        $titulo = $meta['titulo'] ?? null;
        $tipo = $meta['tipo'] ?? null;
        $data = $meta['data'] ?? null;

        if (! $titulo || ! in_array($tipo, self::TIPOS, true) || ! $data) {
            return null;
        }

        return [
            'slug' => basename($caminho, '.md'),
            'titulo' => $titulo,
            'tipo' => $tipo,
            'data' => $data,
            'corpo' => trim($partes[2]),
        ];
    }
}
