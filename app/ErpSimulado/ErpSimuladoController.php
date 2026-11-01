<?php

namespace App\ErpSimulado;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * O "outro lado" da integração: um ERP que não colabora. Não tem chave única
 * (o mesmo CPF cadastrado duas vezes vira dois registros), demora e falha de propósito —
 * inclusive depois de gravar, que é o caso em que o cliente não sabe se deu certo.
 * Exige token, e cada login novo revoga o token anterior.
 *
 * Não faz parte da aplicação: existe para a integração ter contra o que ser testada.
 */
class ErpSimuladoController
{
    public function token(Request $request): JsonResponse
    {
        $this->atrasar();

        if ($falha = $this->falhar('emissão de token')) {
            return $falha;
        }

        $segredo = config('erp.simulador.client_secret');
        $valido = $request->input('client_id') === config('erp.simulador.client_id')
            && is_string($segredo)
            && hash_equals($segredo, (string) $request->input('client_secret'));

        if (! $valido) {
            return response()->json(['erro' => 'Credenciais inválidas'], 401);
        }

        // Como em muito ERP: um login novo derruba os tokens anteriores.
        TokenErp::query()->delete();

        $token = Str::random(40);
        $validade = config('erp.simulador.token_validade');
        TokenErp::create(['hash' => hash('sha256', $token), 'expira_em' => now()->addSeconds($validade)]);

        return response()->json(['access_token' => $token, 'token_type' => 'Bearer', 'expires_in' => $validade]);
    }

    public function buscar(Request $request): JsonResponse
    {
        $this->atrasar();

        if ($falha = $this->falhar('consulta')) {
            return $falha;
        }

        $itens = BeneficiarioErp::where('cpf', $request->query('cpf'))
            ->orderBy('id')
            ->get()
            ->map(fn (BeneficiarioErp $registro) => ['codigo' => $registro->codigo, 'cpf' => $registro->cpf]);

        return response()->json(['itens' => $itens]);
    }

    public function cadastrar(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'nome' => ['required', 'string', 'max:150'],
            'cpf' => ['required', 'digits:11'],
            'data_nascimento' => ['required', 'date_format:Y-m-d'],
        ]);

        $this->atrasar();

        if ($falha = $this->falhar('cadastro')) {
            return $falha;
        }

        $registro = BeneficiarioErp::create($dados);

        if ($this->sortear(config('erp.simulador.falha_depois_de_gravar'))) {
            Log::warning('ERP simulado: falha injetada depois de gravar', ['codigo' => $registro->codigo]);

            return response()->json(['erro' => 'Erro interno'], 500);
        }

        return response()->json(['codigo' => $registro->codigo], 201);
    }

    private function atrasar(): void
    {
        $maximo = config('erp.simulador.latencia_maxima_ms');

        if ($maximo > 0) {
            usleep(random_int(0, $maximo) * 1000);
        }
    }

    private function falhar(string $operacao): ?JsonResponse
    {
        if (! $this->sortear(config('erp.simulador.falha'))) {
            return null;
        }

        Log::warning("ERP simulado: falha injetada na {$operacao}");

        return response()->json(['erro' => 'Serviço indisponível'], 503);
    }

    private function sortear(float $probabilidade): bool
    {
        return $probabilidade > 0 && random_int(1, 10_000) <= $probabilidade * 10_000;
    }
}
