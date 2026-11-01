<?php

namespace Tests\Feature;

use App\Enums\StatusErp;
use App\ErpSimulado\BeneficiarioErp;
use App\Integracao\ClienteErp;
use App\Jobs\EnviarBeneficiarioAoErp;
use App\Models\Beneficiario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Pipeline\Pipeline;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;
use Throwable;

class EnvioAoErpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['erp.url' => 'http://erp.teste']);
    }

    /**
     * Simula o ERP: a consulta por CPF devolve $existentes; o cadastro responde $cadastro.
     *
     * @param  array<int, array<string, string>>  $existentes
     */
    private function erp(array $existentes = [], mixed $cadastro = null): void
    {
        Http::fake([
            'erp.teste/beneficiarios*' => fn (Request $request) => $request->method() === 'GET'
                ? Http::response(['itens' => $existentes])
                : ($cadastro ?? Http::response(['codigo' => '000042'], 201)),
        ]);
    }

    private function enviar(Beneficiario $beneficiario): EnviarBeneficiarioAoErp
    {
        $job = (new EnviarBeneficiarioAoErp($beneficiario))->withFakeQueueInteractions();
        $job->handle(app(ClienteErp::class));

        return $job;
    }

    /**
     * Falha passageira: o job tem que lançar a exceção para a fila tentar de novo.
     *
     * @param  class-string<Throwable>  $excecao
     */
    private function enviarEsperandoNovaTentativa(Beneficiario $beneficiario, string $excecao): void
    {
        try {
            $this->enviar($beneficiario);
        } catch (Throwable $e) {
            $this->assertInstanceOf($excecao, $e);

            return;
        }

        $this->fail("Deveria ter lançado {$excecao} para a fila tentar de novo.");
    }

    public function test_cadastra_no_erp_e_guarda_o_codigo(): void
    {
        $this->erp();
        $beneficiario = Beneficiario::factory()->create();

        $this->enviar($beneficiario)->assertNotFailed();

        $beneficiario->refresh();
        $this->assertSame(StatusErp::Enviado, $beneficiario->status_erp);
        $this->assertSame('000042', $beneficiario->codigo_erp);
        $this->assertNotNull($beneficiario->enviado_ao_erp_em);
        Http::assertSent(fn (Request $request) => $request->method() === 'POST'
            && $request['cpf'] === $beneficiario->cpf
            && $request['data_nascimento'] === $beneficiario->data_nascimento->toDateString());
    }

    public function test_se_o_erp_ja_tem_o_cpf_adota_o_codigo_sem_cadastrar_de_novo(): void
    {
        $beneficiario = Beneficiario::factory()->create();
        $this->erp(existentes: [['codigo' => '000007', 'cpf' => $beneficiario->cpf]]);

        $this->enviar($beneficiario);

        $this->assertSame('000007', $beneficiario->refresh()->codigo_erp);
        Http::assertNotSent(fn (Request $request) => $request->method() === 'POST');
    }

    public function test_erro_5xx_volta_para_a_fila_e_guarda_o_motivo(): void
    {
        $this->erp(cadastro: Http::response(['erro' => 'Serviço indisponível'], 503));
        $beneficiario = Beneficiario::factory()->create();

        $this->enviarEsperandoNovaTentativa($beneficiario, RequestException::class);

        $beneficiario->refresh();
        $this->assertSame(StatusErp::Pendente, $beneficiario->status_erp);
        $this->assertStringContainsString('503', $beneficiario->erro_erp);
    }

    public function test_timeout_volta_para_a_fila(): void
    {
        Http::fake(fn () => throw new ConnectionException('cURL error 28: Operation timed out'));
        $beneficiario = Beneficiario::factory()->create();

        $this->enviarEsperandoNovaTentativa($beneficiario, ConnectionException::class);

        $this->assertSame(StatusErp::Pendente, $beneficiario->refresh()->status_erp);
    }

    public function test_429_e_passageiro_e_volta_para_a_fila(): void
    {
        $this->erp(cadastro: Http::response(['erro' => 'Muitas requisições'], 429));

        $this->enviarEsperandoNovaTentativa(Beneficiario::factory()->create(), RequestException::class);
    }

    public function test_recusa_4xx_falha_de_vez_sem_nova_tentativa(): void
    {
        $this->erp(cadastro: Http::response(['message' => 'CPF inválido'], 422));
        $beneficiario = Beneficiario::factory()->create();

        $this->enviar($beneficiario)->assertFailed();

        $beneficiario->refresh();
        $this->assertSame(StatusErp::Falhou, $beneficiario->status_erp);
        $this->assertStringContainsString('422', $beneficiario->erro_erp);
    }

    public function test_beneficiario_ja_enviado_nao_e_reenviado(): void
    {
        $this->erp();
        $beneficiario = Beneficiario::factory()->create(['status_erp' => StatusErp::Enviado, 'codigo_erp' => '000001']);

        $this->enviar($beneficiario);

        Http::assertNothingSent();
    }

    public function test_quando_as_tentativas_acabam_marca_como_falhou(): void
    {
        $beneficiario = Beneficiario::factory()->create();

        (new EnviarBeneficiarioAoErp($beneficiario))->failed(new RuntimeException('Prazo de tentativas esgotado'));

        $beneficiario->refresh();
        $this->assertSame(StatusErp::Falhou, $beneficiario->status_erp);
        $this->assertSame('Prazo de tentativas esgotado', $beneficiario->erro_erp);
    }

    public function test_com_outro_envio_em_andamento_volta_para_a_fila_sem_chamar_o_erp(): void
    {
        $this->erp();
        $job = (new EnviarBeneficiarioAoErp(Beneficiario::factory()->create()))->withFakeQueueInteractions();
        Cache::lock((new WithoutOverlapping('erp'))->getLockKey($job), 60)->get();

        app(Pipeline::class)
            ->send($job)
            ->through($job->middleware())
            ->then(fn (EnviarBeneficiarioAoErp $job) => $job->handle(app(ClienteErp::class)));

        $job->assertReleased(delay: 2);
        Http::assertNothingSent();
    }

    public function test_erp_que_grava_e_cai_antes_de_responder_nao_ganha_duplicata_na_nova_tentativa(): void
    {
        // Aqui o job fala com o simulador de verdade, só que dentro do processo do teste.
        config(['erp.url' => 'http://erp.teste/erp-simulado', 'erp.simulador.latencia_maxima_ms' => 0, 'erp.simulador.falha' => 0]);
        Http::fake(function (Request $request) {
            $resposta = $this->call($request->method(), $request->url(), $request->method() === 'GET' ? [] : $request->data(),
                server: ['HTTP_ACCEPT' => 'application/json']);

            return Http::response($resposta->getContent(), $resposta->getStatusCode());
        });
        $beneficiario = Beneficiario::factory()->create();

        // 1ª tentativa: o ERP grava e responde 500. O cliente não sabe se deu certo.
        config(['erp.simulador.falha_depois_de_gravar' => 1]);
        $this->enviarEsperandoNovaTentativa($beneficiario, RequestException::class);
        $this->assertSame(1, BeneficiarioErp::count());

        // 2ª tentativa: o ERP está bem de novo.
        config(['erp.simulador.falha_depois_de_gravar' => 0]);
        $this->enviar($beneficiario->refresh());

        $this->assertSame(1, BeneficiarioErp::count(), 'O ERP não pode ter ganhado um segundo registro.');
        $this->assertSame(BeneficiarioErp::sole()->codigo, $beneficiario->refresh()->codigo_erp);
        $this->assertSame(StatusErp::Enviado, $beneficiario->status_erp);
    }
}
