<?php

namespace App\Integracao;

use App\Models\Beneficiario;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class ClienteErp
{
    /**
     * Código do beneficiário no ERP, ou null se o CPF ainda não está lá.
     *
     * @throws ErpRecusouPedido|RequestException|ConnectionException
     */
    public function buscarPorCpf(string $cpf): ?string
    {
        $resposta = $this->http()->get('/beneficiarios', ['cpf' => $cpf]);

        $this->verificar($resposta);

        // Se o ERP já tiver duplicado esse CPF antes, adota o registro mais antigo.
        return $resposta->json('itens.0.codigo');
    }

    /**
     * @throws ErpRecusouPedido|RequestException|ConnectionException
     */
    public function cadastrar(Beneficiario $beneficiario): string
    {
        $resposta = $this->http()->post('/beneficiarios', [
            'nome' => $beneficiario->nome,
            'cpf' => $beneficiario->cpf,
            'data_nascimento' => $beneficiario->data_nascimento->toDateString(),
        ]);

        $this->verificar($resposta);

        return $resposta->json('codigo');
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
