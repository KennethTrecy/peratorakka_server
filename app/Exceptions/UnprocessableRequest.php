<?php

namespace App\Exceptions;

use RuntimeException;
use CodeIgniter\HTTP\Exceptions\ExceptionInterface;
use CodeIgniter\HTTP\Exceptions\HTTPExceptionInterface;

class UnprocessableRequest
extends RuntimeException
implements ExceptionInterface, HTTPExceptionInterface
{
    public function __construct($message) {
        parent::__construct($message, 422);
    }
}
