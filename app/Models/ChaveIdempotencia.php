<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;

#[Fillable(['chave', 'impressao', 'status_http', 'corpo', 'cabecalhos', 'concluida_em', 'expira_em'])]
class ChaveIdempotencia extends Model
{
    use Prunable;

    /**
     * Por quanto tempo um pedido pode ser reenviado com a mesma chave.
     */
    public const VALIDADE_EM_HORAS = 24;

    protected $table = 'chaves_idempotencia';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cabecalhos' => 'array',
            'concluida_em' => 'datetime',
            'expira_em' => 'datetime',
        ];
    }

    public function concluida(): bool
    {
        return $this->concluida_em !== null;
    }

    /**
     * Chaves vencidas saem com o model:prune agendado em routes/console.php.
     *
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        return static::where('expira_em', '<', now());
    }
}
