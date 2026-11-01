<?php

namespace Tests\Feature;

use App\Jobs\EnviarBeneficiarioAoErp;
use App\Models\Beneficiario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class CadastroDeBeneficiarioTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // O envio ao ERP tem testes próprios; aqui só interessa a API.
        Queue::fake();
    }

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

    /**
     * Cada chamada é um pedido novo, com a sua própria Idempotency-Key.
     *
     * @param  array<string, mixed>  $dados
     */
    private function cadastrar(array $dados): TestResponse
    {
        return $this->postJson('/api/beneficiarios', $dados, ['Idempotency-Key' => (string) Str::uuid()]);
    }

    public function test_cadastra_e_responde_201_com_location(): void
    {
        $dados = $this->payload();

        $resposta = $this->cadastrar($dados);

        $beneficiario = Beneficiario::sole();
        $resposta->assertCreated()
            ->assertHeader('Location', route('beneficiarios.show', $beneficiario))
            ->assertJsonPath('data.id', $beneficiario->id)
            ->assertJsonPath('data.nome', 'Maria da Silva')
            ->assertJsonPath('data.data_nascimento', '1990-05-20');
        $this->assertSame($dados['cpf'], $beneficiario->cpf);
    }

    public function test_cadastro_agenda_o_envio_ao_erp_e_responde_como_pendente(): void
    {
        $resposta = $this->cadastrar($this->payload());

        $resposta->assertCreated()
            ->assertJsonPath('data.erp.status', 'pendente')
            ->assertJsonPath('data.erp.codigo', null);
        Queue::assertPushedOn('erp', EnviarBeneficiarioAoErp::class,
            fn (EnviarBeneficiarioAoErp $job) => $job->beneficiario->is(Beneficiario::sole()));
    }

    public function test_cadastro_recusado_nao_agenda_envio_ao_erp(): void
    {
        $this->cadastrar($this->payload(['cpf' => '11111111111']))->assertUnprocessable();

        Queue::assertNothingPushed();
    }

    public function test_aceita_cpf_com_mascara_e_grava_so_os_digitos(): void
    {
        $mascarado = fake('pt_BR')->cpf(true);

        $this->cadastrar($this->payload(['cpf' => $mascarado]))->assertCreated();

        $this->assertDatabaseHas('beneficiarios', ['cpf' => preg_replace('/\D/', '', $mascarado)]);
    }

    public function test_nao_devolve_o_cpf_completo_na_resposta(): void
    {
        $cpf = fake('pt_BR')->cpf(false);

        $resposta = $this->cadastrar($this->payload(['cpf' => $cpf]));

        $resposta->assertCreated()
            ->assertJsonPath('data.cpf', '***.'.substr($cpf, 3, 3).'.'.substr($cpf, 6, 3).'-**');
        $this->assertStringNotContainsString($cpf, $resposta->getContent());
    }

    public function test_rejeita_cpf_com_digito_verificador_invalido(): void
    {
        $cpf = fake('pt_BR')->cpf(false);
        $invalido = substr($cpf, 0, 10).(((int) $cpf[10] + 1) % 10);

        $this->cadastrar($this->payload(['cpf' => $invalido]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['cpf' => 'O CPF informado não é válido.']);

        $this->assertDatabaseCount('beneficiarios', 0);
    }

    public function test_rejeita_cpf_ja_cadastrado(): void
    {
        $existente = Beneficiario::factory()->create();

        $this->cadastrar($this->payload(['cpf' => $existente->cpf]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['cpf' => 'Já existe um beneficiário com este CPF.']);

        $this->assertDatabaseCount('beneficiarios', 1);
    }

    public function test_cpf_gravado_por_outro_pedido_depois_da_validacao_responde_422_e_nao_500(): void
    {
        $dados = $this->payload();

        // Simula a corrida: outro pedido grava o mesmo CPF entre a validação e o INSERT deste.
        Beneficiario::creating(function () use ($dados) {
            DB::table('beneficiarios')->insert([
                'nome' => 'Outro pedido',
                'cpf' => $dados['cpf'],
                'data_nascimento' => '1980-01-01',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        $this->cadastrar($dados)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['cpf' => 'Já existe um beneficiário com este CPF.']);

        $this->assertDatabaseCount('beneficiarios', 1);
    }

    public function test_exige_os_campos_obrigatorios(): void
    {
        $this->cadastrar([])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['nome', 'cpf', 'data_nascimento'])
            ->assertJsonMissingValidationErrors('email');
    }

    public function test_rejeita_data_de_nascimento_futura(): void
    {
        $amanha = now()->addDay()->toDateString();

        $this->cadastrar($this->payload(['data_nascimento' => $amanha]))
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
