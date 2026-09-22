<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * O relato de um erro que aconteceu no navegador de alguém.
 *
 * `authorize()` devolve true porque a rota é pública de propósito: um erro na
 * tela de login também precisa chegar, e ali não há sessão. O que protege a
 * rota é o throttle, esta validação, e o fato de nada do corpo ser interpretado
 * — vai inteiro para o log estruturado.
 */
class RegistrarErroClienteRequest extends FormRequest
{
    /**
     * Pilha maior que isto não acrescenta nada e só engorda o log. O corte
     * acontece aqui, e não no cliente: o cliente já trunca, mas quem garante o
     * limite é quem grava.
     */
    public const LIMITE_STACK = 4000;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'message' => ['required', 'string', 'max:1000'],
            'stack' => ['nullable', 'string', 'max:'.self::LIMITE_STACK],
            'componentStack' => ['nullable', 'string', 'max:'.self::LIMITE_STACK],
            'url' => ['nullable', 'string', 'max:2000'],
            'userAgent' => ['nullable', 'string', 'max:500'],
            'occurredAt' => ['nullable', 'string', 'max:40'],
        ];
    }
}
