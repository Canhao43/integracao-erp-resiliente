<?php

namespace Tests\Feature;

use App\ErpSimulado\BeneficiarioErp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ErpSimuladoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Sem falha nem demora por padrão; cada teste liga o que precisa.
        config([
            'erp.simulador.falha' => 0,
            'erp.simulador.falha_depois_de_gravar' => 0,
            'erp.simulador.latencia_maxima_ms' => 0,
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function dados(): array
    {
        return [
            'nome' => 'Maria da Silva',
            'cpf' => fake('pt_BR')->cpf(false),
            'data_nascimento' => '1990-05-20',
        ];
    }

    public function test_cadastra_e_devolve_o_codigo(): void
    {
        $resposta = $this->postJson('/erp-simulado/beneficiarios', $this->dados());

        $resposta->assertCreated()->assertJsonPath('codigo', BeneficiarioErp::sole()->codigo);
        $this->assertMatchesRegularExpression('/^\d{6}$/', $resposta->json('codigo'));
    }

    public function test_nao_tem_chave_unica_o_mesmo_cpf_vira_dois_registros(): void
    {
        $dados = $this->dados();

        $this->postJson('/erp-simulado/beneficiarios', $dados)->assertCreated();
        $this->postJson('/erp-simulado/beneficiarios', $dados)->assertCreated();

        $this->assertSame(2, BeneficiarioErp::where('cpf', $dados['cpf'])->count());
    }

    public function test_busca_por_cpf(): void
    {
        $dados = $this->dados();
        $codigo = $this->postJson('/erp-simulado/beneficiarios', $dados)->json('codigo');

        $this->getJson('/erp-simulado/beneficiarios?cpf='.$dados['cpf'])
            ->assertOk()
            ->assertJsonCount(1, 'itens')
            ->assertJsonPath('itens.0.codigo', $codigo);
        $this->getJson('/erp-simulado/beneficiarios?cpf=00000000000')
            ->assertOk()
            ->assertJsonCount(0, 'itens');
    }

    public function test_recusa_cadastro_incompleto_com_422(): void
    {
        $this->postJson('/erp-simulado/beneficiarios', ['nome' => 'Sem CPF'])->assertUnprocessable();
    }

    public function test_falha_injetada_responde_503_sem_gravar(): void
    {
        config(['erp.simulador.falha' => 1]);

        $this->postJson('/erp-simulado/beneficiarios', $this->dados())->assertServiceUnavailable();

        $this->assertDatabaseCount('erp_simulado_beneficiarios', 0);
    }

    public function test_falha_depois_de_gravar_responde_500_mas_o_registro_fica(): void
    {
        config(['erp.simulador.falha_depois_de_gravar' => 1]);

        $this->postJson('/erp-simulado/beneficiarios', $this->dados())->assertInternalServerError();

        $this->assertDatabaseCount('erp_simulado_beneficiarios', 1);
    }
}
