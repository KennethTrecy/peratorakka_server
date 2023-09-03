<?php

namespace App\Libraries;

use Throwable;

use CodeIgniter\Debug\BaseExceptionHandler;
use CodeIgniter\Debug\ExceptionHandlerInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Fluent\Cors\Filters\CorsFilter;

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
        $filter = new CorsFilter();
        $response = $filter->after($request, $response);
        $response
            ->setStatusCode($statusCode)
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
