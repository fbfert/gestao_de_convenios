<?php

namespace App\Http\Requests;

use App\Support\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUnimedSettingsRequest extends FormRequest
{
    /**
     * Hosts em que o worker pode digitar a credencial do portal.
     *
     * A senha é write-only nesta API — grava-se, nunca se lê de volta. Mas o
     * `base_url` decide para onde o Playwright navega ANTES de preencher login
     * e senha, então um host arbitrário aqui faz o worker digitar a senha da
     * clínica num formulário hospedado por terceiro: a fronteira write-only é
     * contornada sem nunca ler o valor. Daí allowlist, e não denylist.
     *
     * O worker recusa de novo, por conta própria (`loginUrlFromCredential`).
     * Validar nos dois lados é deliberado: esta regra protege o dado na
     * entrada, a do worker protege quem já estiver gravado no banco.
     */
    private const HOSTS_PERMITIDOS = ['rda.unimedsc.com.br', 'unimedsc.com.br'];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = $this->user()?->tenant_id ?? TenantContext::get();

        return [
            'convenio_id' => [
                'nullable',
                'integer',
                Rule::exists('convenios', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
            'credential' => ['required', 'array'],
            'credential.login' => ['required', 'string', 'max:255'],
            'credential.password' => ['nullable', 'string', 'max:2000'],
            'credential.base_url' => [
                'nullable',
                'url',
                'max:255',
                function (string $atributo, mixed $valor, callable $falhar) {
                    $url = parse_url((string) $valor);
                    $host = strtolower($url['host'] ?? '');
                    $esquema = strtolower($url['scheme'] ?? '');

                    if ($esquema !== 'https') {
                        $falhar('O endereço do portal precisa usar https.');

                        return;
                    }

                    $permitido = collect(self::HOSTS_PERMITIDOS)
                        ->contains(fn (string $p) => $host === $p || str_ends_with($host, '.'.$p));

                    if (! $permitido) {
                        $falhar('O endereço do portal precisa ser da Unimed.');
                    }
                },
            ],
            'credential.nome_contratado' => ['nullable', 'string', 'max:255'],
            'credential.ativo' => ['required', 'boolean'],
        ];
    }
}
