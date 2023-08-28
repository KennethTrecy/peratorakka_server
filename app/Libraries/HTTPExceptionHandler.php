<?php

namespace App\Libraries;

use Throwable;

use CodeIgniter\Debug\BaseExceptionHandler;
use CodeIgniter\Debug\ExceptionHandlerInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

use App\Contracts\APIExecption;

class HTTPExceptionHandler extends BaseExceptionHandler implements ExceptionHandlerInterface
{
    public function handle(
        Throwable $exception,
        RequestInterface $request,
        ResponseInterface $response,
        int $statusCode,
        int $exitCode
    ): void {
        $this->setResponseFormat("json")->respond(
            $exception instanceof APIExecption
            ? $exception->serialize()
            : [
                "errors" => [
                    [
                        "message" => $exception->getMessage()
                    ]
                ]
            ]
        );

        exit($exitCode);
    }
}
