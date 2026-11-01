<?php

namespace App\ErpSimulado;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['nome', 'cpf', 'data_nascimento'])]
class BeneficiarioErp extends Model
{
    protected $table = 'erp_simulado_beneficiarios';

    /**
     * Código no formato de ERP: o id com zeros à esquerda.
     */
    protected function codigo(): Attribute
    {
        return Attribute::get(fn () => str_pad((string) $this->id, 6, '0', STR_PAD_LEFT));
    }
}
