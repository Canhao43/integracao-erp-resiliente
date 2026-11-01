<?php

namespace App\Models;

use Database\Factories\BeneficiarioFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['nome', 'cpf', 'data_nascimento', 'email'])]
class Beneficiario extends Model
{
    /** @use HasFactory<BeneficiarioFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'data_nascimento' => 'date',
        ];
    }
}
