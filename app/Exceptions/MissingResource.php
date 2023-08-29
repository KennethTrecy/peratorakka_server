<?php

namespace App\Exceptions;

use RuntimeException;
use CodeIgniter\Exceptions\ExceptionInterface;
use CodeIgniter\Exceptions\HTTPExceptionInterface;

use App\Contracts\APIExecption;

class MissingResource
extends RuntimeException
implements ExceptionInterface, HTTPExceptionInterface, APIExecption
{
    use SerializableException;

    public function __construct() {
        parent::__construct("The requested resource was not found.", 404);
    }
}
