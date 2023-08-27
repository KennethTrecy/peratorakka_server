<?php

namespace App\Exceptions;

use RuntimeException;
use CodeIgniter\Exceptions\ExceptionInterface;
use CodeIgniter\Exceptions\HTTPExceptionInterface;

class ServerFailure
extends RuntimeException
implements ExceptionInterface, HTTPExceptionInterface
{
    public function __construct($development_message) {
        parent::__construct(
            request()->getServer("CI_ENVIRONMENT") === "development"
                ? $development_message
                : "Please contact the developer because there is an error.",
            500
        );
    }
}
