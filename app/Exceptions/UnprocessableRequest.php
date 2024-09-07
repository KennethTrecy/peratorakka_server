<?php

namespace App\Exceptions;

use App\Contracts\APIException;
use CodeIgniter\Exceptions\ExceptionInterface;
use CodeIgniter\Exceptions\HTTPExceptionInterface;
use RuntimeException;

class UnprocessableRequest extends RuntimeException implements ExceptionInterface, HTTPExceptionInterface, APIException
{
    use SerializableException;

    public function __construct($message)
    {
        parent::__construct($message, 422);
    }
}
