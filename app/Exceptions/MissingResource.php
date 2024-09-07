<?php

namespace App\Exceptions;

use App\Contracts\APIException;
use CodeIgniter\Exceptions\ExceptionInterface;
use CodeIgniter\Exceptions\HTTPExceptionInterface;
use RuntimeException;

class MissingResource extends RuntimeException implements ExceptionInterface, HTTPExceptionInterface, APIException
{
    use SerializableException;

    public function __construct()
    {
        parent::__construct("The requested resource was not found.", 404);
    }
}
