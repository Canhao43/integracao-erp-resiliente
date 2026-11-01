<?php

namespace Database\Factories;

use App\Models\Beneficiario;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Beneficiario>
 */
class BeneficiarioFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // pt_BR fixo: é o locale que sabe gerar CPF (fictício, com dígitos verificadores válidos).
        $faker = fake('pt_BR');

        return [
            'nome' => $faker->name(),
            'cpf' => $faker->unique()->cpf(false),
            'data_nascimento' => $faker->dateTimeBetween('-80 years', '-1 year')->format('Y-m-d'),
            'email' => $faker->unique()->safeEmail(),
        ];
    }
}
