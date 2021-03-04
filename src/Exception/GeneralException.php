<?php

namespace KiteConnect\Exception;

use Exception;

/**
 * An unclassified, general error. Default code is 500.
 */
class GeneralException extends KiteException
{
    /**
     *
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
