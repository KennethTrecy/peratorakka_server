<?php

namespace App\Exceptions;

use Xylemical\Expressions\ExpressionException as BaseExpressionException;
use Xylemical\Expressions\Operator;
use CodeIgniter\Exceptions\ExceptionInterface;
use CodeIgniter\Exceptions\HTTPExceptionInterface;

use App\Contracts\APIException;

class ExpressionException
extends BaseExpressionException
implements ExceptionInterface, HTTPExceptionInterface, APIException
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
