<?php

namespace App\Http\Middleware;

use App\Models\ChaveIdempotencia;
use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Idempotency-Key conforme o draft da IETF (draft-ietf-httpapi-idempotency-key-header):
 * sem chave → 400; chave reusada com outro corpo → 422; chave de pedido em andamento → 409.
 * Mesma chave e mesmo corpo depois de concluído → devolve a resposta original, sem executar de novo.
 */
class GarantirIdempotencia
{
    public function handle(Request $request, Closure $next): Response
    {
        // O draft define o valor como string entre aspas; aceitamos com e sem.
        $chave = trim((string) $request->header('Idempotency-Key'), ' "');

        if ($chave === '' || strlen($chave) > 255) {
            return response()->json([
                'message' => 'Envie o header Idempotency-Key com um identificador único do pedido (até 255 caracteres).',
            ], 400);
        }

        $impressao = $this->impressao($request);

        try {
            // A reserva é gravada antes de executar e fora de transação: é ela que faz
            // um segundo pedido com a mesma chave enxergar que o primeiro está em andamento.
            $reserva = ChaveIdempotencia::create([
                'chave' => $chave,
                'impressao' => $impressao,
                'expira_em' => now()->addHours(ChaveIdempotencia::VALIDADE_EM_HORAS),
            ]);
        } catch (UniqueConstraintViolationException) {
            return $this->responderChaveExistente($chave, $impressao);
        }

        $resposta = $next($request);

        if ($resposta->isSuccessful()) {
            $reserva->update([
                'status_http' => $resposta->getStatusCode(),
                'corpo' => $resposta->getContent(),
                'cabecalhos' => array_filter(['Location' => $resposta->headers->get('Location')]),
                'concluida_em' => now(),
            ]);
        } else {
            // Erro não fica guardado: o cliente corrige o pedido e pode reenviar com a mesma chave.
            $reserva->delete();
        }

        return $resposta;
    }

    private function responderChaveExistente(string $chave, string $impressao): Response
    {
        $existente = ChaveIdempotencia::where('chave', $chave)->first();

        if ($existente !== null && ! hash_equals($existente->impressao, $impressao)) {
            return response()->json([
                'message' => 'Esta Idempotency-Key já foi usada com outro conteúdo.',
            ], 422);
        }

        // null: o outro pedido falhou e liberou a chave entre o insert e esta leitura — reenviar resolve.
        if ($existente === null || ! $existente->concluida()) {
            return response()->json([
                'message' => 'Um pedido com esta Idempotency-Key ainda está em processamento. Tente de novo em instantes.',
            ], 409);
        }

        return response($existente->corpo, $existente->status_http, [
            ...$existente->cabecalhos,
            'Content-Type' => 'application/json',
            'Idempotent-Replayed' => 'true',
        ]);
    }

    /**
     * Mesmo pedido = mesmo método, caminho e corpo — com os campos do JSON ordenados,
     * para que a ordem dos campos não faça dois pedidos iguais parecerem diferentes.
     */
    private function impressao(Request $request): string
    {
        return hash('sha256', $request->method().' '.$request->path().' '.json_encode($this->ordenar($request->all())));
    }

    /**
     * @param  array<array-key, mixed>  $dados
     * @return array<array-key, mixed>
     */
    private function ordenar(array $dados): array
    {
        if (! array_is_list($dados)) {
            ksort($dados);
        }

        return array_map(fn ($valor) => is_array($valor) ? $this->ordenar($valor) : $valor, $dados);
    }
}
