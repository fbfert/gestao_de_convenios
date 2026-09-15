<?php

namespace App\Http\Requests;

use App\Repositories\ConvenioCredencialRepository;
use App\Support\ConvenioDriverCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Valida a credencial contra o catálogo do driver escolhido.
 *
 * As regras não são escritas à mão: saem do `ConvenioDriverCatalog`, a mesma
 * fonte que o front usa para renderizar o formulário. Assim um driver novo não
 * pode entrar com campo que a API aceita e a tela não mostra — ou o contrário.
 */
class UpdateConvenioCredencialRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'driver' => ['required', 'string', Rule::in(ConvenioDriverCatalog::drivers())],
            'credenciais' => ['present', 'array'],
            'ativo' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $driver = $this->input('driver');

            if (! ConvenioDriverCatalog::existe($driver)) {
                return;
            }

            $enviadas = array_keys((array) $this->input('credenciais', []));
            $permitidas = ConvenioDriverCatalog::chaves($driver);

            /*
             * Driver sem campos — o `scsaude` enquanto a autenticação não é
             * definida — só aceita credencial vazia. Sem isto, o operador
             * poderia gravar o que quisesse num campo que nenhuma automação lê.
             */
            foreach (array_diff($enviadas, $permitidas) as $chave) {
                $validator->errors()->add(
                    "credenciais.{$chave}",
                    "O campo {$chave} não pertence ao driver ".ConvenioDriverCatalog::rotulo($driver).'.',
                );
            }

            $secretas = ConvenioDriverCatalog::chavesSecretas($driver);

            foreach (ConvenioDriverCatalog::chavesObrigatorias($driver) as $chave) {
                if (filled($this->input("credenciais.{$chave}"))) {
                    continue;
                }

                /*
                 * Campo secreto em branco preserva o que já está gravado, então
                 * só é exigido quando ainda não há valor — é o que permite
                 * corrigir a URL base sem redigitar a senha do portal.
                 */
                if (in_array($chave, $secretas, true) && $this->segredoJaGravado($chave)) {
                    continue;
                }

                $validator->errors()->add("credenciais.{$chave}", "O campo {$chave} é obrigatório.");
            }
        });
    }

    /**
     * O convênio vem do binding, que já chega isolado por tenant desde 14/09
     * (ver `BelongsToTenant::resolveRouteBinding`) — daí não haver checagem de
     * tenant repetida aqui.
     */
    private function segredoJaGravado(string $chave): bool
    {
        $convenio = $this->route('convenio');

        if (! $convenio) {
            return false;
        }

        $credencial = app(ConvenioCredencialRepository::class)
            ->paraConvenio((int) $this->user()->tenant_id, (int) $convenio->id);

        return $credencial !== null
            && $credencial->driver === $this->input('driver')
            && $credencial->campo($chave) !== null;
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        $rotulos = [];

        foreach (ConvenioDriverCatalog::campos($this->input('driver')) as $campo) {
            $rotulos["credenciais.{$campo['chave']}"] = $campo['rotulo'];
        }

        return $rotulos;
    }
}
