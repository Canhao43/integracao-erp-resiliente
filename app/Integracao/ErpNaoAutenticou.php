<?php

namespace App\Integracao;

use RuntimeException;

/**
 * O ERP recusou a nossa credencial. É problema de configuração da integração, não do
 * cadastro: o envio continua pendente e é tentado de novo, em vez de marcar como falhou.
 */
class ErpNaoAutenticou extends RuntimeException {}
