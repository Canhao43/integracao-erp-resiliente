<?php

namespace App\ErpSimulado;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['hash', 'expira_em'])]
class TokenErp extends Model
{
    protected $table = 'erp_simulado_tokens';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expira_em' => 'datetime',
        ];
    }
}
