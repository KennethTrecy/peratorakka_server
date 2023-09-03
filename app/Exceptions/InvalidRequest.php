<?php

namespace App\Exceptions;

use RuntimeException;

use CodeIgniter\Exceptions\ExceptionInterface;
use CodeIgniter\Exceptions\HTTPExceptionInterface;
use CodeIgniter\Validation\Validation;

use App\Contracts\APIException;

class InvalidRequest
extends RuntimeException
implements ExceptionInterface, HTTPExceptionInterface, APIException
{
    private Validation $ran_validation;

    public function __construct(Validation $ran_validation) {
        parent::__construct("The submitted data is not valid.", 400);

        $this->ran_validation = $ran_validation;
    }

    public function serialize(): array {
        $raw_errors = $this->ran_validation->getErrors();
        $formalized_errors = [];
        foreach ($raw_errors as $field => $message) {
            array_push($formalized_errors, [
                "field" => $field,
                "message" => $message
            ]);
        }

        return [
            "errors" => $formalized_errors
        ];
    }
}
