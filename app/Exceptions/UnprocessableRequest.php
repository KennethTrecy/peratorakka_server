<?php

namespace App\Exceptions;

use RuntimeException;
use CodeIgniter\Exceptions\ExceptionInterface;
use CodeIgniter\Exceptions\HTTPExceptionInterface;

use App\Contracts\APIException;

class UnprocessableRequest
extends RuntimeException
implements ExceptionInterface, HTTPExceptionInterface, APIException
{
    use SerializableException;

    public function __construct($message) {
        parent::__construct($message, 422);
    }
}
