<?php

namespace App\Http\Resources;

use App\Models\Beneficiario;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Beneficiario
 */
class BeneficiarioResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nome' => $this->nome,
            // LGPD, minimização: quem cadastrou já tem o CPF, então a resposta não precisa
            // devolvê-lo inteiro. Mesmo padrão de máscara usado pelo governo federal.
            'cpf' => '***.'.substr($this->cpf, 3, 3).'.'.substr($this->cpf, 6, 3).'-**',
            'data_nascimento' => $this->data_nascimento->toDateString(),
            'email' => $this->email,
            'criado_em' => $this->created_at->toIso8601String(),
            'erp' => [
                'status' => $this->status_erp->value,
                'codigo' => $this->codigo_erp,
            ],
        ];
    }
}
