<?php

namespace App\Exceptions;

use Exception;

/**
 * Error "esperado" del negocio (sin stock, cliente bloqueado, timbrado vencido...).
 * Su mensaje SÍ se le muestra al cajero. Cualquier otra excepción se registra en el log
 * y al usuario solo se le muestra un mensaje genérico con un código.
 */
class NegocioException extends Exception
{
}
