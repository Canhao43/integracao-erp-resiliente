<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class Cpf implements ValidationRule
{
    /**
     * Espera só dígitos: a máscara é removida antes, no FormRequest.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! self::valido((string) $value)) {
            $fail('O CPF informado não é válido.');
        }
    }

    /**
     * Confere os dois dígitos verificadores (módulo 11).
     */
    public static function valido(string $cpf): bool
    {
        // 000.000.000-00, 111.111.111-11 etc. passam no cálculo, mas não existem.
        if (! preg_match('/^\d{11}$/', $cpf) || preg_match('/^(\d)\1{10}$/', $cpf)) {
            return false;
        }

        foreach ([9, 10] as $posicao) {
            $soma = 0;
            for ($i = 0; $i < $posicao; $i++) {
                $soma += (int) $cpf[$i] * ($posicao + 1 - $i);
            }

            if ((int) $cpf[$posicao] !== $soma * 10 % 11 % 10) {
                return false;
            }
        }

        return true;
    }
}
