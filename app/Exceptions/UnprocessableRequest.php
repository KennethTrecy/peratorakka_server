<?php

namespace App\Exceptions;

use RuntimeException;
use CodeIgniter\Exceptions\ExceptionInterface;
use CodeIgniter\Exceptions\HTTPExceptionInterface;

use App\Contracts\APIExecption;

class UnprocessableRequest
extends RuntimeException
implements ExceptionInterface, HTTPExceptionInterface
{
    use SerializableException;

    public function __construct($message) {
        parent::__construct($message, 422);
    }
}
