<?php

namespace App\Repositories;

use App\Models\ConvenioCredencial;
use App\Support\ConvenioDriverCatalog;

/**
 * Ponto único de leitura da credencial de um convênio.
 *
 * Existe para que nenhum service volte a procurar credencial por conta própria.
 * Antes eram seis lugares consultando `UnimedRdaCredential` direto por
 * `tenant_id`, e foi por isso que o disjuntor conseguiu pausar a automação do
 * tenant inteiro em 14/09: quem pausava e quem lia eram o mesmo `where`, sem
 * ninguém dizer de qual convênio.
 *
 * Também é onde a decisão "esta credencial serve?" fica: `ativa()` devolve só o
 * que está pronto para uso, e é ele que os services chamam antes de montar
 * payload para o worker.
 */
class ConvenioCredencialRepository
{
    public function paraConvenio(int $tenantId, ?int $convenioId): ?ConvenioCredencial
    {
        if ($convenioId === null) {
            return null;
        }

        return ConvenioCredencial::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('convenio_id', $convenioId)
            ->first();
    }

    /**
     * A credencial utilizável do convênio, ou null.
     *
     * Null cobre três casos que, para quem chama, dão no mesmo: não existe, está
     * pausada, ou falta campo obrigatório. Quem precisa distinguir olha
     * `paraConvenio()`.
     */
    public function ativa(int $tenantId, ?int $convenioId): ?ConvenioCredencial
    {
        $credencial = $this->paraConvenio($tenantId, $convenioId);

        return $credencial?->pronta() ? $credencial : null;
    }

    /**
     * Grava a credencial do convênio, preservando os segredos não reenviados.
     *
     * Campo do tipo `password` que chega em branco mantém o valor gravado — é o
     * que permite ao operador corrigir a URL base sem redigitar a senha do
     * portal. Chave fora do catálogo do driver não é gravada; a request já a
     * recusa antes, e aqui a filtragem é a segunda tranca.
     *
     * @param  array<string, mixed>  $valores
     */
    public function salvar(int $tenantId, int $convenioId, string $driver, array $valores): ConvenioCredencial
    {
        $credencial = $this->paraConvenio($tenantId, $convenioId)
            ?? new ConvenioCredencial(['tenant_id' => $tenantId, 'convenio_id' => $convenioId]);

        // Driver diferente do gravado zera os campos antigos: as chaves de um
        // driver não valem para outro, e manter sobra daria credencial meio de
        // cada um.
        $anteriores = $credencial->driver === $driver ? ($credencial->credenciais ?? []) : [];
        $permitidas = ConvenioDriverCatalog::chaves($driver);
        $secretas = ConvenioDriverCatalog::chavesSecretas($driver);
        $novos = [];

        foreach ($permitidas as $chave) {
            $enviado = $valores[$chave] ?? null;

            if (blank($enviado) && in_array($chave, $secretas, true)) {
                $enviado = $anteriores[$chave] ?? null;
            }

            if (filled($enviado)) {
                $novos[$chave] = (string) $enviado;
            }
        }

        $credencial->forceFill([
            'tenant_id' => $tenantId,
            'convenio_id' => $convenioId,
            'driver' => $driver,
            'credenciais' => $novos,
        ]);

        if ($credencial->ativo === null) {
            $credencial->ativo = true;
        }

        $credencial->save();

        return $credencial;
    }
}
