<?php

namespace Tests\Feature;

use App\Integracao\ClienteErp;
use App\Models\Beneficiario;
use App\Models\ChamadaErp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use LogicException;
use Tests\TestCase;

class AuditoriaErpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['erp.url' => 'http://erp.teste']);
        Http::fake([
            '*/token' => Http::response(['access_token' => 'token-secreto', 'expires_in' => 3600]),
            '*/beneficiarios*' => fn (Request $request) => $request->method() === 'GET'
                ? Http::response(['itens' => []])
                : Http::response(['codigo' => '000042'], 201),
        ]);
    }

    private function enviar(Beneficiario $beneficiario): void
    {
        $erp = app(ClienteErp::class);
        $erp->buscar($beneficiario);
        $erp->cadastrar($beneficiario);
    }

    public function test_registra_cada_chamada_com_metodo_caminho_status_e_duracao(): void
    {
        $beneficiario = Beneficiario::factory()->create();

        $this->enviar($beneficiario);

        $chamadas = ChamadaErp::orderBy('id')->get();
        $this->assertSame(
            ['POST /token 200', 'GET /beneficiarios 200', 'POST /beneficiarios 201'],
            $chamadas->map(fn (ChamadaErp $c) => "{$c->metodo} {$c->caminho} {$c->status_http}")->all(),
        );
        $this->assertSame([null, $beneficiario->id, $beneficiario->id], $chamadas->pluck('beneficiario_id')->all());
        $this->assertContainsOnly('int', $chamadas->pluck('duracao_ms')->all());
    }

    public function test_falha_de_conexao_fica_registrada_sem_status(): void
    {
        Http::fake([
            '*/token' => Http::response(['access_token' => 'token-secreto', 'expires_in' => 3600]),
            '*/beneficiarios*' => fn () => throw new ConnectionException('cURL error 28: Operation timed out'),
        ]);

        try {
            app(ClienteErp::class)->buscar(Beneficiario::factory()->create());
        } catch (ConnectionException) {
        }

        $chamada = ChamadaErp::where('caminho', '/beneficiarios')->sole();
        $this->assertNull($chamada->status_http);
        $this->assertStringContainsString('timed out', $chamada->erro);
    }

    public function test_nao_guarda_cpf_nem_token(): void
    {
        $beneficiario = Beneficiario::factory()->create();

        $this->enviar($beneficiario);

        $tudo = ChamadaErp::all()->toJson();
        $this->assertStringNotContainsString($beneficiario->cpf, $tudo);
        $this->assertStringNotContainsString('token-secreto', $tudo);
        $this->assertStringNotContainsString('cpf=', $tudo);
    }

    public function test_registro_nao_pode_ser_alterado(): void
    {
        $this->enviar(Beneficiario::factory()->create());

        $this->expectException(LogicException::class);

        ChamadaErp::first()->update(['status_http' => 500]);
    }

    public function test_registro_nao_pode_ser_apagado_um_a_um(): void
    {
        $this->enviar(Beneficiario::factory()->create());

        $this->expectException(LogicException::class);

        ChamadaErp::first()->delete();
    }

    public function test_retencao_apaga_so_o_que_passou_do_prazo(): void
    {
        config(['erp.auditoria_retencao_dias' => 90]);
        $linha = ['metodo' => 'GET', 'caminho' => '/beneficiarios', 'status_http' => 200, 'duracao_ms' => 10];
        DB::table('chamadas_erp')->insert([
            [...$linha, 'created_at' => now()->subDays(91)],
            [...$linha, 'created_at' => now()->subDays(89)],
        ]);

        $this->artisan('model:prune', ['--model' => [ChamadaErp::class]])->assertSuccessful();

        $this->assertSame(1, ChamadaErp::count());
        $this->assertTrue(ChamadaErp::sole()->created_at->gt(now()->subDays(90)));
    }

    public function test_endpoint_mostra_as_chamadas_do_beneficiario(): void
    {
        $beneficiario = Beneficiario::factory()->create();
        $this->enviar($beneficiario);

        $this->getJson("/api/beneficiarios/{$beneficiario->id}/chamadas-erp")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.caminho', '/beneficiarios')
            ->assertJsonPath('data.1.status_http', 201)
            ->assertJsonStructure(['data' => [['metodo', 'caminho', 'status_http', 'duracao_ms', 'erro', 'em']]]);
    }
}
