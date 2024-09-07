<?php

namespace App\Filters;

use App\Contracts\OwnedResource;
use CodeIgniter\API\ResponseTrait;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Shield\Filters\ChainAuth as BaseAuth;

class ChainAuth extends BaseAuth implements FilterInterface
{
    use ResponseTrait;

    private $response;

    public function __construct()
    {
        $this->response = response();
    }

    /**
     * Do whatever processing this filter needs to do.
     * By default it should not return anything during
     * normal execution. However, when an abnormal state
     * is found, it should return an instance of
     * CodeIgniter\HTTP\Response. If it does, script
     * execution will end and that Response will be
     * sent back to the client, allowing for error pages,
     * redirects, etc.
     *
     * @param RequestInterface $request
     * @param array|null       $arguments
     *
     * @return mixed
     */
    public function before(RequestInterface $request, $arguments = null)
    {
        helper([ "auth", "setting", "session" ]);

        $session = session();
        $original_response = parent::before($request, $arguments);

        if ($original_response) {
            $new_response = $original_response->removeHeader("Location");

            $raw_error = $session->getFlashdata("error");
            if (is_null($raw_error)) {
                $new_response = $new_response->setStatusCode(200);
            } else {
                $formalized_errors = [
                    [
                        "message" => $raw_error
                    ]
                ];

                $new_response = $new_response
                    ->setStatusCode(401)
                    ->setJSON([
                        "errors" => $formalized_errors
                    ]);
            }

            return $new_response;
        }

        return;
    }

    /**
     * Allows After filters to inspect and modify the response
     * object as needed. This method does not allow any way
     * to stop execution of other after filters, short of
     * throwing an Exception or Error.
     *
     * @param RequestInterface  $request
     * @param ResponseInterface $response
     * @param array|null        $arguments
     *
     * @return mixed
     */
    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null): void
    {
        //
    }
}
