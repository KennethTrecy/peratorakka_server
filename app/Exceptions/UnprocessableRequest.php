<?php

namespace App\Exceptions;

use RuntimeException;
use CodeIgniter\Exceptions\ExceptionInterface;
use CodeIgniter\Exceptions\HTTPExceptionInterface;

class UnprocessableRequest
extends RuntimeException
implements ExceptionInterface, HTTPExceptionInterface
{
    public function __construct($message) {
        parent::__construct($message, 422);
    }
}
