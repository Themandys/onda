<?php

namespace App\Exceptions;

use RuntimeException;

class MusicStreamingException extends RuntimeException
{
    /**
     * @param  array<string, string>  $attempts  Error por instancia probada.
     */
    public function __construct(string $message, public readonly array $attempts = [])
    {
        parent::__construct($message);
    }
}
