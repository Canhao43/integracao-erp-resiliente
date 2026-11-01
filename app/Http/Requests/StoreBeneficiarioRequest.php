<?php

namespace App\Http\Requests;

use App\Rules\Cpf;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBeneficiarioRequest extends FormRequest
{
    /**
     * Aceita o CPF com ou sem máscara, mas valida e grava só os dígitos —
     * senão "123.456.789-09" e "12345678909" virariam dois cadastros.
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('cpf'))) {
            $this->merge(['cpf' => preg_replace('/\D/', '', $this->input('cpf'))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'nome' => ['required', 'string', 'max:150'],
            'cpf' => ['bail', 'required', 'string', new Cpf, Rule::unique('beneficiarios', 'cpf')],
            'data_nascimento' => ['required', 'date_format:Y-m-d', 'before:today'],
            'email' => ['nullable', 'email', 'max:150'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'required' => 'O campo :attribute é obrigatório.',
            'string' => 'O campo :attribute deve ser texto.',
            'max' => 'O campo :attribute deve ter no máximo :max caracteres.',
            'cpf.unique' => 'Já existe um beneficiário com este CPF.',
            'data_nascimento.date_format' => 'A data de nascimento deve estar no formato AAAA-MM-DD.',
            'data_nascimento.before' => 'A data de nascimento deve ser anterior a hoje.',
            'email.email' => 'O e-mail informado não é válido.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'data_nascimento' => 'data de nascimento',
        ];
    }
}
