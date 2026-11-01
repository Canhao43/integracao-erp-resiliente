<?php

namespace App\Enums;

enum StatusErp: string
{
    case Pendente = 'pendente';
    case Enviado = 'enviado';
    case Falhou = 'falhou';
}
