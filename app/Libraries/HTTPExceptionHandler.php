<?php

namespace App\Libraries;

use App\Contracts\APIException;
use CodeIgniter\Debug\BaseExceptionHandler;
use CodeIgniter\Debug\ExceptionHandlerInterface;
use CodeIgniter\Exceptions\HTTPExceptionInterface;
use CodeIgniter\Filters\Cors;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Throwable;

class HTTPExceptionHandler extends BaseExceptionHandler implements ExceptionHandlerInterface
{
    public function handle(
        Throwable $exception,
        RequestInterface $request,
        ResponseInterface $response,
        int $statusCode,
        int $exitCode
    ): void {
        $filter = new Cors();

        $filter->after($request, $response);

        $response
            ->setStatusCode(
                $exception instanceof HTTPExceptionInterface
                    ? $exception->getCode()
                    : $statusCode
            )
            ->setJSON(
                $exception instanceof APIException
                ? $exception->serialize()
                : [
                    "errors" => [
                        [
                            "message" => $exception->getMessage()
                        ]
                    ]
                ]
            )
            ->send();

        exit($exitCode);
    }
}
