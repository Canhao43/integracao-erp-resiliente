<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBeneficiarioRequest;
use App\Http\Resources\BeneficiarioResource;
use App\Http\Resources\ChamadaErpResource;
use App\Jobs\EnviarBeneficiarioAoErp;
use App\Models\Beneficiario;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

class BeneficiarioController extends Controller
{
    public function store(StoreBeneficiarioRequest $request): JsonResponse
    {
        try {
            $beneficiario = Beneficiario::create($request->validated());
        } catch (UniqueConstraintViolationException) {
            // Dois pedidos com o mesmo CPF passaram juntos pela validação; o índice único barrou o segundo.
            throw ValidationException::withMessages(['cpf' => StoreBeneficiarioRequest::CPF_DUPLICADO]);
        }

        // O cadastro local responde na hora; o envio ao ERP segue pela fila, no ritmo do ERP.
        EnviarBeneficiarioAoErp::dispatch($beneficiario);

        return BeneficiarioResource::make($beneficiario)
            ->response()
            ->setStatusCode(201)
            ->header('Location', route('beneficiarios.show', $beneficiario));
    }

    public function show(Beneficiario $beneficiario): BeneficiarioResource
    {
        return BeneficiarioResource::make($beneficiario);
    }

    /**
     * O histórico de chamadas ao ERP deste cadastro: é por aqui que o suporte vê por que não chegou.
     */
    public function chamadasErp(Beneficiario $beneficiario): AnonymousResourceCollection
    {
        return ChamadaErpResource::collection($beneficiario->chamadasErp()->orderBy('id')->get());
    }
}
