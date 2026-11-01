<?php

namespace Tests\Feature;

use App\Models\Beneficiario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CadastroDeBeneficiarioTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $sobrescrever
     * @return array<string, mixed>
     */
    private function payload(array $sobrescrever = []): array
    {
        return array_merge([
            'nome' => 'Maria da Silva',
            'cpf' => fake('pt_BR')->cpf(false),
            'data_nascimento' => '1990-05-20',
            'email' => 'maria@example.com',
        ], $sobrescrever);
    }

    public function test_cadastra_e_responde_201_com_location(): void
    {
        $dados = $this->payload();

        $resposta = $this->postJson('/api/beneficiarios', $dados);

        $beneficiario = Beneficiario::sole();
        $resposta->assertCreated()
            ->assertHeader('Location', route('beneficiarios.show', $beneficiario))
            ->assertJsonPath('data.id', $beneficiario->id)
            ->assertJsonPath('data.nome', 'Maria da Silva')
            ->assertJsonPath('data.data_nascimento', '1990-05-20');
        $this->assertSame($dados['cpf'], $beneficiario->cpf);
    }

    public function test_aceita_cpf_com_mascara_e_grava_so_os_digitos(): void
    {
        $mascarado = fake('pt_BR')->cpf(true);

        $this->postJson('/api/beneficiarios', $this->payload(['cpf' => $mascarado]))->assertCreated();

        $this->assertDatabaseHas('beneficiarios', ['cpf' => preg_replace('/\D/', '', $mascarado)]);
    }

    public function test_nao_devolve_o_cpf_completo_na_resposta(): void
    {
        $cpf = fake('pt_BR')->cpf(false);

        $resposta = $this->postJson('/api/beneficiarios', $this->payload(['cpf' => $cpf]));

        $resposta->assertCreated()
            ->assertJsonPath('data.cpf', '***.'.substr($cpf, 3, 3).'.'.substr($cpf, 6, 3).'-**');
        $this->assertStringNotContainsString($cpf, $resposta->getContent());
    }

    public function test_rejeita_cpf_com_digito_verificador_invalido(): void
    {
        $cpf = fake('pt_BR')->cpf(false);
        $invalido = substr($cpf, 0, 10).(((int) $cpf[10] + 1) % 10);

        $this->postJson('/api/beneficiarios', $this->payload(['cpf' => $invalido]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['cpf' => 'O CPF informado não é válido.']);

        $this->assertDatabaseCount('beneficiarios', 0);
    }

    public function test_rejeita_cpf_ja_cadastrado(): void
    {
        $existente = Beneficiario::factory()->create();

        $this->postJson('/api/beneficiarios', $this->payload(['cpf' => $existente->cpf]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['cpf' => 'Já existe um beneficiário com este CPF.']);

        $this->assertDatabaseCount('beneficiarios', 1);
    }

    public function test_exige_os_campos_obrigatorios(): void
    {
        $this->postJson('/api/beneficiarios', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['nome', 'cpf', 'data_nascimento'])
            ->assertJsonMissingValidationErrors('email');
    }

    public function test_rejeita_data_de_nascimento_futura(): void
    {
        $amanha = now()->addDay()->toDateString();

        $this->postJson('/api/beneficiarios', $this->payload(['data_nascimento' => $amanha]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('data_nascimento');
    }

    public function test_consulta_por_id(): void
    {
        $beneficiario = Beneficiario::factory()->create();

        $this->getJson(route('beneficiarios.show', $beneficiario))
            ->assertOk()
            ->assertJsonPath('data.id', $beneficiario->id)
            ->assertJsonPath('data.nome', $beneficiario->nome);
    }

    public function test_consulta_de_id_inexistente_responde_404_em_json(): void
    {
        $this->getJson('/api/beneficiarios/999')
            ->assertNotFound()
            ->assertJsonStructure(['message']);
    }
}
