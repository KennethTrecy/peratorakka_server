<?php

namespace App\Filters;

use CodeIgniter\API\ResponseTrait;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

use App\Contracts\OwnedResource;
use App\Exceptions\MissingResource;
use App\Exceptions\ServerFailure;

class EnsureOwnership implements FilterInterface
{
    use ResponseTrait;

    private $response;

    public function __construct() {
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
        helper([ "auth" ]);

        if (
            $arguments === null
            || !is_array($arguments)
            || count($arguments) < 2
            || !(model($arguments[0]) instanceof OwnedResource)
            || !is_string($arguments[1])
            || !in_array($arguments[1], [
                SEARCH_NORMALLY,
                SEARCH_WITH_DELETED,
                SEARCH_ONLY_DELETED
            ])
        ) {
            throw new ServerFailure(
                "An owned resource model and search mode allows to check ownership."
            );
        }

        $model = model($arguments[0]);
        $URI = $request->getUri();
        $id = $URI->getSegment(
            isset($arguments[2]) && is_numeric($arguments[2])
            ? intval($arguments[2])
            : $URI->getTotalSegments()
        );
        $current_user = auth()->user();
        $search_mode = $arguments[1];
        if (!$model->isOwnedBy($current_user, $search_mode, intval($id))) {
            throw new MissingResource();
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
