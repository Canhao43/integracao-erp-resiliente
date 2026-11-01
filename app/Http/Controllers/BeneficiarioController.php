<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBeneficiarioRequest;
use App\Http\Resources\BeneficiarioResource;
use App\Models\Beneficiario;
use Illuminate\Http\JsonResponse;

class BeneficiarioController extends Controller
{
    public function store(StoreBeneficiarioRequest $request): JsonResponse
    {
        $beneficiario = Beneficiario::create($request->validated());

        return BeneficiarioResource::make($beneficiario)
            ->response()
            ->setStatusCode(201)
            ->header('Location', route('beneficiarios.show', $beneficiario));
    }

    public function show(Beneficiario $beneficiario): BeneficiarioResource
    {
        return BeneficiarioResource::make($beneficiario);
    }
}
