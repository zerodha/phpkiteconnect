<?php

namespace KiteConnect\Exception;

use Exception;

/**
 * Represents a network issue between Kite and the backend Order Management System (OMS).
 */
class NetworkException extends KiteException
{
    /**
     * @param mixed $message
     * @param int $code
     * @param Exception|null $previous
     * @return void
     */
    public function __construct($message, int $code = 503, Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
