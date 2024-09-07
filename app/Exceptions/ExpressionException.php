<?php

namespace App\Exceptions;

use App\Contracts\APIException;
use CodeIgniter\Exceptions\ExceptionInterface;
use CodeIgniter\Exceptions\HTTPExceptionInterface;
use Xylemical\Expressions\ExpressionException as BaseExpressionException;
use Xylemical\Expressions\Operator;

class ExpressionException extends BaseExpressionException implements ExceptionInterface, HTTPExceptionInterface, APIException
{
    use SerializableException;

    public function __construct(
        string $message = "",
        Operator $operator = null,
        array $values = []
    ) {
        parent::__construct($message, $operator, $values, 500);
    }
}
