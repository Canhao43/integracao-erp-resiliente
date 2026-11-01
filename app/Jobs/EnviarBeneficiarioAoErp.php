<?php

namespace App\Jobs;

use App\Enums\StatusErp;
use App\Integracao\ClienteErp;
use App\Integracao\ErpRecusouPedido;
use App\Models\Beneficiario;
use DateTimeInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Str;
use Throwable;

class EnviarBeneficiarioAoErp implements ShouldQueue
{
    use Queueable;

    /**
     * Falhas (timeout, 5xx) toleradas antes de desistir. O prazo total fica em retryUntil().
     */
    public int $maxExceptions = 10;

    public int $timeout = 30;

    public function __construct(public Beneficiario $beneficiario)
    {
        $this->onQueue('erp');
    }

    public function retryUntil(): DateTimeInterface
    {
        return now()->addDay();
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return config('erp.backoff');
    }

    /**
     * Um envio por vez, mesmo com mais de um worker. Duas execuções do mesmo cadastro em
     * paralelo consultariam juntas, não achariam nada e cadastrariam duas vezes. Uma trava
     * por CPF bastaria para isso; ela é global para também não mandar escrita concorrente
     * a um ERP que não aguenta. O lock expira sozinho se o worker morrer.
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [(new WithoutOverlapping('erp'))->releaseAfter(2)->expireAfter(120)];
    }

    public function handle(ClienteErp $erp): void
    {
        if ($this->beneficiario->status_erp === StatusErp::Enviado) {
            return;
        }

        try {
            // O ERP não tem chave única: se uma tentativa anterior gravou e caiu antes de
            // responder, o registro já está lá. Consultar antes evita criar de novo.
            $codigo = $erp->buscar($this->beneficiario) ?? $erp->cadastrar($this->beneficiario);
        } catch (ErpRecusouPedido $e) {
            $this->marcarFalha($e);
            $this->fail($e);

            return;
        } catch (Throwable $e) {
            // Passageiro: guarda o motivo e devolve para a fila tentar de novo.
            $this->beneficiario->update(['erro_erp' => Str::limit($e->getMessage(), 500)]);

            throw $e;
        }

        $this->beneficiario->update([
            'status_erp' => StatusErp::Enviado,
            'codigo_erp' => $codigo,
            'erro_erp' => null,
            'enviado_ao_erp_em' => now(),
        ]);
    }

    /**
     * Chamado pela fila quando as tentativas ou o prazo acabam.
     */
    public function failed(?Throwable $e): void
    {
        $this->marcarFalha($e);
    }

    private function marcarFalha(?Throwable $e): void
    {
        $this->beneficiario->update([
            'status_erp' => StatusErp::Falhou,
            'erro_erp' => Str::limit((string) $e?->getMessage(), 500),
        ]);
    }
}
