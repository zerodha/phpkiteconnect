<?php

namespace KiteConnect\Exception;

use Exception;

/**
 * An unclassified, general 500 error.
 */
class PermissionException extends KiteException
{
    /**
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
