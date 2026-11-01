<?php

namespace App\Http\Resources;

use App\Models\ChamadaErp;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ChamadaErp
 */
class ChamadaErpResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'metodo' => $this->metodo,
            'caminho' => $this->caminho,
            'status_http' => $this->status_http,
            'duracao_ms' => $this->duracao_ms,
            'erro' => $this->erro,
            'em' => $this->created_at->toIso8601String(),
        ];
    }
}
