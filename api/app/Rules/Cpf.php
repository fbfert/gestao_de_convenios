<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * CPF com dígitos verificadores conferidos.
 *
 * O campo é opcional no cadastro, e é justamente por isso que a conferência
 * existe: CPF errado é pior que CPF em branco — vira busca que não acha,
 * duplicata que não bate e dado que vaza para a operadora.
 *
 * Recebe o valor já sem máscara (ver `SanitizaCpf`).
 */
class Cpf implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $digitos = preg_replace('/\D+/', '', (string) $value);

        if ($digitos === '') {
            return;
        }

        if (strlen($digitos) !== 11 || preg_match('/^(\d)\1{10}$/', $digitos)) {
            $fail('O CPF informado não é válido.');

            return;
        }

        foreach ([9, 10] as $posicao) {
            $soma = 0;

            for ($i = 0; $i < $posicao; $i++) {
                $soma += (int) $digitos[$i] * (($posicao + 1) - $i);
            }

            $resto = $soma % 11;
            $verificador = $resto < 2 ? 0 : 11 - $resto;

            if ((int) $digitos[$posicao] !== $verificador) {
                $fail('O CPF informado não é válido.');

                return;
            }
        }
    }
}
