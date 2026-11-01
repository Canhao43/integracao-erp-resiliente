<?php

namespace Tests\Unit;

use App\Rules\Cpf;
use Faker\Factory as Faker;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CpfTest extends TestCase
{
    public function test_aceita_cpfs_com_digitos_verificadores_corretos(): void
    {
        $faker = Faker::create('pt_BR');

        for ($i = 0; $i < 50; $i++) {
            $cpf = $faker->cpf(false);
            $this->assertTrue(Cpf::valido($cpf), "Deveria aceitar o CPF gerado {$cpf}");
        }
    }

    public function test_rejeita_qualquer_alteracao_nos_digitos_verificadores(): void
    {
        $cpf = Faker::create('pt_BR')->cpf(false);

        foreach ([9, 10] as $posicao) {
            for ($deslocamento = 1; $deslocamento <= 9; $deslocamento++) {
                $alterado = $cpf;
                $alterado[$posicao] = (string) (((int) $cpf[$posicao] + $deslocamento) % 10);
                $this->assertFalse(Cpf::valido($alterado), "Deveria rejeitar {$alterado}");
            }
        }
    }

    #[DataProvider('cpfsMalformados')]
    public function test_rejeita_cpf_malformado(string $cpf): void
    {
        $this->assertFalse(Cpf::valido($cpf));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function cpfsMalformados(): array
    {
        return [
            'curto' => ['1234567890'],
            'longo' => ['123456789012'],
            'com letra' => ['1234567890a'],
            'com máscara (ela sai antes, no FormRequest)' => ['123.456.789-09'],
            'vazio' => [''],
            'todos zero' => ['00000000000'],
            'todos iguais' => ['99999999999'],
        ];
    }
}
