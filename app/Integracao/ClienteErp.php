<?php

namespace App\Integracao;

use App\Models\Beneficiario;
use App\Models\ChamadaErp;
use Closure;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class ClienteErp
{
    /**
     * Código do beneficiário no ERP, ou null se o CPF ainda não está lá.
     *
     * @throws ErpRecusouPedido|ErpNaoAutenticou|RequestException|ConnectionException|LockTimeoutException
     */
    public function buscar(Beneficiario $beneficiario): ?string
    {
        $resposta = $this->chamar('GET', '/beneficiarios', ['cpf' => $beneficiario->cpf], $beneficiario);

        // Se o ERP já tiver duplicado esse CPF antes, adota o registro mais antigo.
        return $resposta->json('itens.0.codigo');
    }

    /**
     * @throws ErpRecusouPedido|ErpNaoAutenticou|RequestException|ConnectionException|LockTimeoutException
     */
    public function cadastrar(Beneficiario $beneficiario): string
    {
        $resposta = $this->chamar('POST', '/beneficiarios', [
            'nome' => $beneficiario->nome,
            'cpf' => $beneficiario->cpf,
            'data_nascimento' => $beneficiario->data_nascimento->toDateString(),
        ], $beneficiario);

        return $resposta->json('codigo');
    }

    /**
     * Chamada autenticada. Se o ERP recusar o token — venceu antes da hora, ou outro
     * login o revogou —, renova e tenta uma vez.
     *
     * @param  array<string, mixed>  $dados
     */
    private function chamar(string $metodo, string $caminho, array $dados, Beneficiario $beneficiario): Response
    {
        $enviar = function () use ($metodo, $caminho, $dados, $beneficiario) {
            // O token sai antes da medição: a emissão tem a sua própria linha na auditoria.
            $token = $this->token();

            return $this->auditar($metodo, $caminho, $beneficiario, fn () => $metodo === 'GET'
                ? $this->http()->withToken($token)->get($caminho, $dados)
                : $this->http()->withToken($token)->post($caminho, $dados));
        };

        $resposta = $enviar();

        if ($resposta->status() === 401) {
            Cache::forget($this->chaveDoToken());
            $resposta = $enviar();
        }

        if ($resposta->status() === 401) {
            throw new ErpNaoAutenticou('O ERP recusou o token mesmo depois de renová-lo.');
        }

        $this->verificar($resposta);

        return $resposta;
    }

    /**
     * Token em cache, renovado um pouco antes de vencer. A renovação passa por um lock:
     * com o ERP revogando o token anterior a cada login, dois processos renovando juntos
     * derrubariam um ao outro.
     */
    private function token(): string
    {
        $chave = $this->chaveDoToken();

        return Cache::get($chave) ?? Cache::lock("{$chave}:renovando", 10)->block(10, function () use ($chave) {
            // Outro processo pode ter renovado enquanto este esperava o lock.
            if ($token = Cache::get($chave)) {
                return $token;
            }

            $resposta = $this->auditar('POST', '/token', null, fn () => $this->http()->post('/token', [
                'client_id' => config('erp.client_id'),
                'client_secret' => config('erp.client_secret'),
            ]));

            if ($resposta->status() === 401) {
                throw new ErpNaoAutenticou('O ERP recusou as credenciais da integração.');
            }

            $resposta->throw();

            $validade = (int) $resposta->json('expires_in');
            Cache::put($chave, $resposta->json('access_token'), max(1, $validade - config('erp.token_margem')));

            return $resposta->json('access_token');
        });
    }

    /**
     * Um token por ERP e por credencial: homologação e produção nunca dividem o mesmo.
     */
    private function chaveDoToken(): string
    {
        return 'erp:token:'.sha1(config('erp.url').'|'.config('erp.client_id'));
    }

    /**
     * Registra a chamada só com metadados — sem corpo, sem query string, sem token:
     * nem o CPF nem a credencial vão parar na auditoria.
     *
     * @param  Closure(): Response  $chamada
     */
    private function auditar(string $metodo, string $caminho, ?Beneficiario $beneficiario, Closure $chamada): Response
    {
        $inicio = hrtime(true);

        try {
            $resposta = $chamada();
        } catch (ConnectionException $e) {
            $this->registrar($metodo, $caminho, $beneficiario, $inicio, null, Str::limit($e->getMessage(), 300));

            throw $e;
        }

        $this->registrar($metodo, $caminho, $beneficiario, $inicio, $resposta->status(), null);

        return $resposta;
    }

    private function registrar(string $metodo, string $caminho, ?Beneficiario $beneficiario, int $inicio, ?int $status, ?string $erro): void
    {
        ChamadaErp::create([
            'beneficiario_id' => $beneficiario?->id,
            'metodo' => $metodo,
            'caminho' => $caminho,
            'status_http' => $status,
            'duracao_ms' => intdiv(hrtime(true) - $inicio, 1_000_000),
            'erro' => $erro,
        ]);
    }

    private function http(): PendingRequest
    {
        return Http::baseUrl(config('erp.url'))
            ->acceptJson()
            ->connectTimeout(2)
            ->timeout(config('erp.timeout'));
    }

    /**
     * 4xx (menos 429) é recusa definitiva; 5xx e 429 sobem como RequestException,
     * e timeout já chega aqui como ConnectionException — esses dois a fila tenta de novo.
     */
    private function verificar(Response $resposta): void
    {
        if ($resposta->clientError() && $resposta->status() !== 429) {
            throw new ErpRecusouPedido("O ERP recusou o pedido ({$resposta->status()}): {$resposta->body()}");
        }

        $resposta->throw();
    }
}
