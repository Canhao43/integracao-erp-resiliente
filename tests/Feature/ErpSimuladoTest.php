<?php

namespace Tests\Feature;

use App\ErpSimulado\BeneficiarioErp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
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
            'erp.simulador.client_id' => 'integracao',
            'erp.simulador.client_secret' => 'segredo',
            'erp.simulador.token_validade' => 60,
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

    private function login(string $segredo = 'segredo'): TestResponse
    {
        return $this->postJson('/erp-simulado/token', ['client_id' => 'integracao', 'client_secret' => $segredo]);
    }

    /**
     * @param  array<string, string>  $dados
     */
    private function cadastrar(array $dados, ?string $token = null): TestResponse
    {
        return $this->postJson('/erp-simulado/beneficiarios', $dados, ['Authorization' => 'Bearer '.($token ?? $this->login()->json('access_token'))]);
    }

    public function test_cadastra_e_devolve_o_codigo(): void
    {
        $resposta = $this->cadastrar($this->dados());

        $resposta->assertCreated()->assertJsonPath('codigo', BeneficiarioErp::sole()->codigo);
        $this->assertMatchesRegularExpression('/^\d{6}$/', $resposta->json('codigo'));
    }

    public function test_nao_tem_chave_unica_o_mesmo_cpf_vira_dois_registros(): void
    {
        $dados = $this->dados();
        $token = $this->login()->json('access_token');

        $this->cadastrar($dados, $token)->assertCreated();
        $this->cadastrar($dados, $token)->assertCreated();

        $this->assertSame(2, BeneficiarioErp::where('cpf', $dados['cpf'])->count());
    }

    public function test_busca_por_cpf(): void
    {
        $dados = $this->dados();
        $token = $this->login()->json('access_token');
        $codigo = $this->cadastrar($dados, $token)->json('codigo');

        $this->getJson('/erp-simulado/beneficiarios?cpf='.$dados['cpf'], ['Authorization' => "Bearer {$token}"])
            ->assertOk()
            ->assertJsonCount(1, 'itens')
            ->assertJsonPath('itens.0.codigo', $codigo);
        $this->getJson('/erp-simulado/beneficiarios?cpf=00000000000', ['Authorization' => "Bearer {$token}"])
            ->assertOk()
            ->assertJsonCount(0, 'itens');
    }

    public function test_recusa_cadastro_incompleto_com_422(): void
    {
        $this->cadastrar(['nome' => 'Sem CPF'])->assertUnprocessable();
    }

    public function test_falha_injetada_responde_503_sem_gravar(): void
    {
        $token = $this->login()->json('access_token');
        config(['erp.simulador.falha' => 1]);

        $this->cadastrar($this->dados(), $token)->assertServiceUnavailable();

        $this->assertDatabaseCount('erp_simulado_beneficiarios', 0);
    }

    public function test_falha_depois_de_gravar_responde_500_mas_o_registro_fica(): void
    {
        config(['erp.simulador.falha_depois_de_gravar' => 1]);

        $this->cadastrar($this->dados())->assertInternalServerError();

        $this->assertDatabaseCount('erp_simulado_beneficiarios', 1);
    }

    public function test_login_devolve_token_com_validade(): void
    {
        $this->login()
            ->assertOk()
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonPath('expires_in', 60)
            ->assertJsonStructure(['access_token']);
    }

    public function test_credencial_errada_responde_401(): void
    {
        $this->login('segredo-errado')->assertUnauthorized();
    }

    public function test_sem_token_responde_401(): void
    {
        $this->postJson('/erp-simulado/beneficiarios', $this->dados())->assertUnauthorized();
        $this->getJson('/erp-simulado/beneficiarios?cpf=00000000000')->assertUnauthorized();

        $this->assertDatabaseCount('erp_simulado_beneficiarios', 0);
    }

    public function test_token_vencido_responde_401(): void
    {
        $token = $this->login()->json('access_token');

        $this->travel(61)->seconds();

        $this->cadastrar($this->dados(), $token)->assertUnauthorized();
    }

    public function test_login_novo_revoga_o_token_anterior(): void
    {
        $antigo = $this->login()->json('access_token');
        $novo = $this->login()->json('access_token');

        $this->cadastrar($this->dados(), $antigo)->assertUnauthorized();
        $this->cadastrar($this->dados(), $novo)->assertCreated();
    }
}
