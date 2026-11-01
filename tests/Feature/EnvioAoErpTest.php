<?php

namespace Tests\Feature;

use App\Enums\StatusErp;
use App\ErpSimulado\BeneficiarioErp;
use App\Integracao\ClienteErp;
use App\Integracao\ErpNaoAutenticou;
use App\Jobs\EnviarBeneficiarioAoErp;
use App\Models\Beneficiario;
use App\Models\ChamadaErp;
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
            'erp.teste/token' => Http::response(['access_token' => 'token-de-teste', 'expires_in' => 3600]),
            'erp.teste/beneficiarios*' => fn (Request $request) => $request->method() === 'GET'
                ? Http::response(['itens' => $existentes])
                : ($cadastro ?? Http::response(['codigo' => '000042'], 201)),
        ]);
    }

    private function cadastroNoErp(Request $request): bool
    {
        return $request->method() === 'POST' && str_ends_with($request->url(), '/beneficiarios');
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
        Http::assertSent(fn (Request $request) => $this->cadastroNoErp($request)
            && $request['cpf'] === $beneficiario->cpf
            && $request['data_nascimento'] === $beneficiario->data_nascimento->toDateString());
    }

    public function test_se_o_erp_ja_tem_o_cpf_adota_o_codigo_sem_cadastrar_de_novo(): void
    {
        $beneficiario = Beneficiario::factory()->create();
        $this->erp(existentes: [['codigo' => '000007', 'cpf' => $beneficiario->cpf]]);

        $this->enviar($beneficiario);

        $this->assertSame('000007', $beneficiario->refresh()->codigo_erp);
        Http::assertNotSent(fn (Request $request) => $this->cadastroNoErp($request));
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

    public function test_credencial_recusada_e_problema_de_configuracao_e_volta_para_a_fila(): void
    {
        Http::fake(['erp.teste/token' => Http::response(['erro' => 'Credenciais inválidas'], 401)]);
        $beneficiario = Beneficiario::factory()->create();

        $this->enviarEsperandoNovaTentativa($beneficiario, ErpNaoAutenticou::class);

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

    /**
     * O job fala com o simulador de verdade, só que dentro do processo do teste.
     */
    private function simuladorNoProcesso(): void
    {
        config([
            'erp.url' => 'http://erp.teste/erp-simulado',
            'erp.client_id' => 'integracao',
            'erp.client_secret' => 'segredo',
            'erp.simulador.client_id' => 'integracao',
            'erp.simulador.client_secret' => 'segredo',
            'erp.simulador.latencia_maxima_ms' => 0,
            'erp.simulador.falha' => 0,
            'erp.simulador.falha_depois_de_gravar' => 0,
        ]);

        Http::fake(function (Request $request) {
            $resposta = $this->call($request->method(), $request->url(), $request->method() === 'GET' ? [] : $request->data(),
                server: ['HTTP_ACCEPT' => 'application/json', 'HTTP_AUTHORIZATION' => $request->header('Authorization')[0] ?? '']);

            return Http::response($resposta->getContent(), $resposta->getStatusCode());
        });
    }

    public function test_token_revogado_por_outro_login_e_renovado_sem_perder_o_envio(): void
    {
        $this->simuladorNoProcesso();
        $this->enviar($primeiro = Beneficiario::factory()->create());

        // Outro sistema faz login com a mesma credencial: o ERP derruba o nosso token.
        $this->postJson('/erp-simulado/token', ['client_id' => 'integracao', 'client_secret' => 'segredo'])->assertOk();

        $this->enviar($segundo = Beneficiario::factory()->create());

        $this->assertSame(StatusErp::Enviado, $primeiro->refresh()->status_erp);
        $this->assertSame(StatusErp::Enviado, $segundo->refresh()->status_erp);
        // A auditoria conta a história: recusado, renovou, seguiu.
        $this->assertSame(
            ['GET /beneficiarios 401', 'POST /token 200', 'GET /beneficiarios 200', 'POST /beneficiarios 201'],
            $this->linhaDoTempo($segundo),
        );
    }

    /**
     * As chamadas ao ERP desde a primeira deste beneficiário, incluindo a renovação do token.
     *
     * @return array<int, string>
     */
    private function linhaDoTempo(Beneficiario $beneficiario): array
    {
        $primeira = $beneficiario->chamadasErp()->min('id');

        return ChamadaErp::where('id', '>=', $primeira)->orderBy('id')->get()
            ->map(fn (ChamadaErp $c) => "{$c->metodo} {$c->caminho} {$c->status_http}")
            ->all();
    }

    public function test_erp_que_grava_e_cai_antes_de_responder_nao_ganha_duplicata_na_nova_tentativa(): void
    {
        $this->simuladorNoProcesso();
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
