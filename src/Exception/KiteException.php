<?php

namespace KiteConnect\Exception;

use Exception;

/**
 * Base exeception for client exceptions.
 */
class KiteException extends Exception
{
    /**
     * Constructor for KiteException
     * @param mixed $message
     * @param int $code
     * @param Exception|null $previous
     * @return void
     */
    public function __construct($message, int $code = 0, Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    public function __toString()
    {
        return get_class($this) . " ({$this->code}) '{$this->message}'\n";
    }
}
