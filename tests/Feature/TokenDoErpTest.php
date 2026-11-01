<?php

namespace Tests\Feature;

use App\Integracao\ClienteErp;
use App\Integracao\ErpNaoAutenticou;
use App\Models\Beneficiario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TokenDoErpTest extends TestCase
{
    use RefreshDatabase;

    private int $tokensEmitidos = 0;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'erp.url' => 'http://erp.teste',
            'erp.client_id' => 'integracao',
            'erp.client_secret' => 'segredo',
            'erp.token_margem' => 5,
        ]);
    }

    /**
     * ERP falso: emite um token novo a cada login; a consulta responde os status de
     * $consultas em sequência (200 quando a lista acaba).
     *
     * @param  array<int, int>  $consultas
     */
    private function erp(int $validade = 60, array $consultas = []): void
    {
        Http::fake([
            '*/token' => function () use ($validade) {
                $this->tokensEmitidos++;

                return Http::response(['access_token' => "token-{$this->tokensEmitidos}", 'expires_in' => $validade]);
            },
            '*/beneficiarios*' => function () use (&$consultas) {
                $status = array_shift($consultas) ?? 200;

                return Http::response($status === 200 ? ['itens' => []] : ['erro' => 'Token inválido'], $status);
            },
        ]);
    }

    private function consultar(): void
    {
        app(ClienteErp::class)->buscar(Beneficiario::factory()->create());
    }

    public function test_reaproveita_o_token_entre_chamadas(): void
    {
        $this->erp();

        $this->consultar();
        $this->consultar();
        $this->consultar();

        $this->assertSame(1, $this->tokensEmitidos);
        Http::assertSent(fn (Request $request) => str_contains($request->url(), '/beneficiarios')
            && $request->hasHeader('Authorization', 'Bearer token-1'));
    }

    public function test_renova_um_pouco_antes_de_vencer(): void
    {
        $this->erp(validade: 60);

        $this->consultar();
        $this->travel(54)->seconds();
        $this->consultar();
        $this->assertSame(1, $this->tokensEmitidos, 'Aos 54s o token ainda vale e está dentro da margem.');

        $this->travel(2)->seconds();
        $this->consultar();
        $this->assertSame(2, $this->tokensEmitidos, 'Aos 56s faltam menos de 5s para vencer: renova.');
    }

    public function test_token_recusado_renova_e_repete_a_chamada_uma_vez(): void
    {
        $this->erp(consultas: [401, 200]);

        $this->consultar();

        $this->assertSame(2, $this->tokensEmitidos);
        Http::assertSent(fn (Request $request) => str_contains($request->url(), '/beneficiarios')
            && $request->hasHeader('Authorization', 'Bearer token-2'));
    }

    public function test_recusado_mesmo_depois_de_renovar_e_erro_de_autenticacao(): void
    {
        $this->erp(consultas: [401, 401]);

        $this->expectException(ErpNaoAutenticou::class);

        $this->consultar();
    }

    public function test_credencial_recusada_no_login_e_erro_de_autenticacao(): void
    {
        Http::fake(['*/token' => Http::response(['erro' => 'Credenciais inválidas'], 401)]);

        $this->expectException(ErpNaoAutenticou::class);

        $this->consultar();
    }

    public function test_falha_passageira_no_login_volta_para_a_fila(): void
    {
        Http::fake(['*/token' => Http::response(['erro' => 'Serviço indisponível'], 503)]);

        $this->expectException(RequestException::class);

        $this->consultar();
    }

    public function test_homologacao_e_producao_nao_dividem_token(): void
    {
        $this->erp();

        $this->consultar();
        config(['erp.url' => 'http://erp-homologacao.teste']);
        $this->consultar();
        config(['erp.url' => 'http://erp.teste']);
        $this->consultar();

        $this->assertSame(2, $this->tokensEmitidos, 'Um token por ambiente, e cada um reaproveitado.');
        Http::assertSent(fn (Request $request) => str_starts_with($request->url(), 'http://erp-homologacao.teste/beneficiarios')
            && $request->hasHeader('Authorization', 'Bearer token-2'));
    }
}
