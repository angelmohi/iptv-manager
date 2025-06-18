<?php

namespace App\Exceptions;

use Exception;

class TokenException extends Exception
{
    /**
     * Create a new TokenException instance.
     *
     * @param  string          $message   The error message
     * @param  int             $code      The exception code (default 0)
     * @param  Exception|null  $previous  The previous exception for chaining
     */
    public function __construct(string $message = "", int $code = 0, Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
