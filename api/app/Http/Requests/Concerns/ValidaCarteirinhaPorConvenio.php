<?php

namespace App\Http\Requests\Concerns;

use App\Models\Convenio;
use Illuminate\Validation\Validator;

/**
 * Valida a carteirinha contra o formato declarado no convênio.
 *
 * A regra vem de `convenios.carteirinha_blocos`, não do `connector_driver`:
 * formato de carteirinha é característica do convênio, enquanto o driver é o
 * interruptor da automação (ver migration 2026_08_12_200000). Convênio sem
 * formato declarado continua aceitando texto livre.
 *
 * Compartilhado por Store e Update de paciente — a regra precisa ser idêntica
 * nos dois, senão um cadastro válido deixaria de poder ser salvo na edição.
 */
trait ValidaCarteirinhaPorConvenio
{
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $convenio = $this->convenioDaCarteirinha();
            $blocos = $convenio?->blocosCarteirinha();

            if ($blocos === null) {
                return;
            }

            $digitos = preg_replace('/\D+/', '', (string) $this->input('carteirinha'));
            $esperado = array_sum($blocos);

            if (strlen($digitos) !== $esperado) {
                $validator->errors()->add('carteirinha', sprintf(
                    'A carteirinha %s deve conter %d dígitos nos blocos %s.',
                    $convenio->nome,
                    $esperado,
                    implode('+', $blocos),
                ));
            }
        });
    }

    protected function prepareForValidation(): void
    {
        // A tela envia um bloco por campo; o banco guarda a string corrida.
        if (is_array($this->input('carteirinha_blocos'))) {
            $this->merge([
                'carteirinha' => implode('', $this->input('carteirinha_blocos')),
            ]);

            return;
        }

        // Nome antigo do campo, mantido para não quebrar um cliente que ainda
        // envie `carteirinha_unimed`.
        if (is_array($this->input('carteirinha_unimed'))) {
            $this->merge([
                'carteirinha' => implode('', $this->input('carteirinha_unimed')),
            ]);
        }
    }

    private function convenioDaCarteirinha(): ?Convenio
    {
        $convenioId = $this->integer('convenio_id');

        if (! $convenioId || ! $this->user()?->tenant_id) {
            return null;
        }

        return Convenio::query()
            ->where('tenant_id', $this->user()->tenant_id)
            ->whereKey($convenioId)
            ->first();
    }
}
