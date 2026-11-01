<?php

namespace App\Models;

use App\Enums\StatusErp;
use Database\Factories\BeneficiarioFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['nome', 'cpf', 'data_nascimento', 'email', 'status_erp', 'codigo_erp', 'erro_erp', 'enviado_ao_erp_em'])]
class Beneficiario extends Model
{
    /** @use HasFactory<BeneficiarioFactory> */
    use HasFactory;

    /**
     * Igual ao default da coluna, para o model recém-criado já saber o status sem reler o banco.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status_erp' => 'pendente',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'data_nascimento' => 'date',
            'status_erp' => StatusErp::class,
            'enviado_ao_erp_em' => 'datetime',
        ];
    }
}
