<?php

namespace KiteConnect\Exception;

use Exception;

/**
 * Represents all token and authentication related errors. Default code is 403.
 */
class TokenException extends KiteException
{
    /**
     *
     * @param mixed $message
     * @param int $code
     * @param Exception|null $previous
     * @return void
     */
    public function __construct($message, int $code = 403, Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
