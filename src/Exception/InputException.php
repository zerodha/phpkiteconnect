<?php

namespace KiteConnect\Exception;

use Exception;

/**
 * Represents user input errors such as missing and invalid	parameters.
 */
class InputException extends KiteException
{
    /**
     * @param mixed $message
     * @param int $code
     * @param Exception|null $previous
     * @return void
     */
    public function __construct($message, int $code = 500, Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
