<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable(['beneficiario_id', 'metodo', 'caminho', 'status_http', 'duracao_ms', 'erro'])]
class ChamadaErp extends Model
{
    use MassPrunable;

    /**
     * Registro de auditoria não muda depois de gravado: só tem data de criação.
     */
    public const UPDATED_AT = null;

    protected $table = 'chamadas_erp';

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('A auditoria de chamadas ao ERP não pode ser alterada.'));
        static::deleting(fn () => throw new LogicException('A auditoria de chamadas ao ERP só sai pela retenção.'));
    }

    /**
     * @return BelongsTo<Beneficiario, $this>
     */
    public function beneficiario(): BelongsTo
    {
        return $this->belongsTo(Beneficiario::class);
    }

    /**
     * Retenção: apaga em lote, por query, sem passar pelo bloqueio de exclusão do model.
     *
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        return static::where('created_at', '<', now()->subDays(config('erp.auditoria_retencao_dias')));
    }
}
