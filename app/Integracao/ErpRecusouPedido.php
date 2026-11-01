<?php

namespace App\Integracao;

use RuntimeException;

/**
 * O ERP respondeu 4xx: o problema está no pedido, e tentar de novo não muda a resposta.
 */
class ErpRecusouPedido extends RuntimeException {}
