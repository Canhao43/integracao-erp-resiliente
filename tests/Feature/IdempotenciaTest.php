<?php

namespace Tests\Feature;

use App\Models\ChaveIdempotencia;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class IdempotenciaTest extends TestCase
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
        ], $sobrescrever);
    }

    /**
     * @param  array<string, mixed>  $dados
     */
    private function cadastrar(array $dados, string $chave): TestResponse
    {
        return $this->postJson('/api/beneficiarios', $dados, ['Idempotency-Key' => $chave]);
    }

    public function test_sem_a_chave_responde_400_e_nao_cadastra(): void
    {
        $this->postJson('/api/beneficiarios', $this->payload())
            ->assertBadRequest()
            ->assertJsonStructure(['message']);

        $this->assertDatabaseCount('beneficiarios', 0);
    }

    public function test_chave_longa_demais_responde_400(): void
    {
        $this->cadastrar($this->payload(), str_repeat('a', 256))->assertBadRequest();
    }

    public function test_aceita_a_chave_entre_aspas_como_no_draft(): void
    {
        $chave = (string) Str::uuid();

        $this->cadastrar($this->payload(), "\"{$chave}\"")->assertCreated();

        $this->assertDatabaseHas('chaves_idempotencia', ['chave' => $chave]);
    }

    public function test_reenvio_com_a_mesma_chave_devolve_a_resposta_original_sem_cadastrar_de_novo(): void
    {
        $chave = (string) Str::uuid();
        $dados = $this->payload();

        $primeira = $this->cadastrar($dados, $chave)->assertCreated();
        $segunda = $this->cadastrar($dados, $chave)->assertCreated();

        $this->assertSame($primeira->getContent(), $segunda->getContent());
        $segunda->assertHeader('Location', $primeira->headers->get('Location'))
            ->assertHeader('Idempotent-Replayed', 'true');
        $this->assertDatabaseCount('beneficiarios', 1);
    }

    public function test_mesmo_corpo_com_os_campos_em_outra_ordem_conta_como_o_mesmo_pedido(): void
    {
        $chave = (string) Str::uuid();
        $dados = $this->payload();

        $this->cadastrar($dados, $chave)->assertCreated();

        $this->cadastrar(array_reverse($dados, true), $chave)
            ->assertCreated()
            ->assertHeader('Idempotent-Replayed', 'true');
        $this->assertDatabaseCount('beneficiarios', 1);
    }

    public function test_mesma_chave_com_outro_conteudo_responde_422(): void
    {
        $chave = (string) Str::uuid();

        $this->cadastrar($this->payload(), $chave)->assertCreated();

        $this->cadastrar($this->payload(['nome' => 'Outra Pessoa']), $chave)
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Esta Idempotency-Key já foi usada com outro conteúdo.');
        $this->assertDatabaseCount('beneficiarios', 1);
    }

    public function test_chave_de_pedido_ainda_em_processamento_responde_409(): void
    {
        $chave = (string) Str::uuid();
        $dados = $this->payload();

        // Primeiro faz um pedido de verdade para obter a impressão; depois o deixa "em andamento".
        $this->cadastrar($dados, $chave)->assertCreated();
        ChaveIdempotencia::where('chave', $chave)->update(['concluida_em' => null]);

        $this->cadastrar($dados, $chave)->assertConflict();
        $this->assertDatabaseCount('beneficiarios', 1);
    }

    public function test_erro_de_validacao_nao_prende_a_chave(): void
    {
        $chave = (string) Str::uuid();

        $this->cadastrar($this->payload(['cpf' => '11111111111']), $chave)->assertUnprocessable();
        $this->assertDatabaseCount('chaves_idempotencia', 0);

        // O cliente corrige o CPF e reenvia com a mesma chave.
        $this->cadastrar($this->payload(), $chave)->assertCreated();
        $this->assertDatabaseCount('beneficiarios', 1);
    }

    public function test_a_consulta_nao_exige_chave(): void
    {
        $id = $this->cadastrar($this->payload(), (string) Str::uuid())->json('data.id');

        $this->getJson("/api/beneficiarios/{$id}")->assertOk();
    }

    public function test_chaves_vencidas_sao_apagadas_e_as_validas_ficam(): void
    {
        $this->cadastrar($this->payload(), 'valida')->assertCreated();
        $this->cadastrar($this->payload(), 'vencida')->assertCreated();
        ChaveIdempotencia::where('chave', 'vencida')->update(['expira_em' => now()->subMinute()]);

        $this->artisan('model:prune', ['--model' => [ChaveIdempotencia::class]])->assertSuccessful();

        $this->assertDatabaseHas('chaves_idempotencia', ['chave' => 'valida']);
        $this->assertDatabaseMissing('chaves_idempotencia', ['chave' => 'vencida']);
    }
}
