<?php

namespace App\Libraries;

use Throwable;

use CodeIgniter\Debug\BaseExceptionHandler;
use CodeIgniter\Debug\ExceptionHandlerInterface;
use CodeIgniter\Exceptions\HTTPExceptionInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

use App\Contracts\APIException;

class HTTPExceptionHandler extends BaseExceptionHandler implements ExceptionHandlerInterface
{
    public function handle(
        Throwable $exception,
        RequestInterface $request,
        ResponseInterface $response,
        int $statusCode,
        int $exitCode
    ): void {
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
