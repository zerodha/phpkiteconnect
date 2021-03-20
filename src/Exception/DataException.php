<?php

namespace KiteConnect\Exception;

use Exception;

/**
 * Represents a bad response from the backend Order Management System (OMS).
 */
class DataException extends KiteException
{
    /**
     * @param mixed $message
     * @param int $code
     * @param Exception|null $previous
     * @return void
     */
    public function __construct($message, int $code = 502, Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
