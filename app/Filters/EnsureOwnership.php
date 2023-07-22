<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

use App\Contracts\OwnedResource;

class EnsureOwnership implements FilterInterface
{
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
        if (
            $arguments === null
            || !is_array($arguments)
            || count($arguments) < 1
            || model($arguments[0]) instanceof OwnedResource
        ) {
            return response()->failServerError([
                "errors" => [
                    [
                        "message" => $request->getServer("CI_ENVIRONMENT") === "development"
                            ? "A owned resource model and segment index allows to check ownership."
                            : "Please contact the developer because there is an error."
                    ]
                ]
            ]);
        }

        $model = model($arguments[0]);
        $URI = $request->getUri();
        $id = $URI->getSegment($URI->getTotalSegments());
        $current_user = auth()->user();
        if (!$model->isOwnedBy($current_user)) {
            return response()->failNotFound([
                "errors" => [
                    [
                        "message" => "The request resource is not found."
                    ]
                ]
            ]);
        }
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
    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        //
    }
}
