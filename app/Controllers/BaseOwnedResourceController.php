<?php

namespace App\Controllers;

use CodeIgniter\API\ResponseTrait;
use CodeIgniter\Validation\Validation;

use App\Contracts\OwnedResource;
use App\Controllers\BaseController;

abstract class BaseOwnedResourceController extends BaseController
{
    use ResponseTrait;

    abstract protected static function getIndividualName(): string;
    abstract protected static function getCollectiveName(): string;
    abstract protected static function getModelName(): string;

    abstract protected static function makeValidation(): Validation;

    public static function getInfo(): OwnedResourceInfo {
        return new OwnedResourceInfo(
            static::getCollectiveName(),
            static::getModelName()
        );
    }

    protected static function prepareRequestData(array $raw_request_data): array {
        return $raw_request_data;
    }

    protected static function getModel(): OwnedResource {
        return model(static::getModelName());
    }

    public function index()
    {
        $current_user = auth()->user();

        $model = static::getModel();

        $response_document = [
            static::getCollectiveName() => $model
                ->limitSearchToUser($model, $current_user)
                ->findAll()
        ];

        return response()->setJSON($response_document);
    }

    public function show(int $id)
    {
        $current_user = auth()->user();
        $model = static::getModel();
        $data = $model->find($id);

        $is_success = !is_null($data);

        if ($is_success) {
            $response_document = [
                static::getIndividualName() => $model->find($id)
            ];

            return response()->setJSON($response_document);
        } else {
            return $this->failNotFound()->setJSON([
                "errors" => [
                    [
                        "message" => "The requested resource was not found."
                    ]
                ]
            ]);
        }
    }

    public function create()
    {
        $controller = $this;
        return $this->processValidInputsOnly(function($request_data) use ($controller) {
            $current_user = auth()->user();

            $model = static::getModel();
            $info = static::prepareRequestData($request_data);

            $is_success = $model->insert($info, false);
            if ($is_success) {
                $response_document = [
                    static::getIndividualName() => array_merge(
                        [ "id" =>  $model->getInsertID() ],
                        $info
                    )
                ];

                return $controller->respondCreated()->setJSON($response_document);
            }

            return $controller->makeServerError(
                "There is an error on inserting to the database server."
            );
        });
    }

    public function update(int $id)
    {
        $controller = $this;
        return $this->processValidInputsOnly(function($request_data) use ($controller, $id) {
            $current_user = auth()->user();

            $model = static::getModel();
            $info = static::prepareRequestData($request_data);

            $is_success = $model->update($id, $info);
            if ($is_success) {
                return $controller->respondNoContent();
            }

            return $controller->makeServerError(
                "There is an error on updating to the database server."
            );
        });
    }

    public function delete(int $id)
    {
        $model = static::getModel();

        $is_success = $model->delete($id);
        if ($is_success) {
            return $this->respondNoContent();
        }

        return $this->makeServerError(
            "There is an error on deleting to the database server."
        );
    }

    public function restore(int $id)
    {
        $model = static::getModel();

        $is_success = $model->update($id, [ "deleted_at" => null ]);
        if ($is_success) {
            return $this->respondNoContent();
        }

        return $this->makeServerError(
            "There is an error on restoring to the database server."
        );
    }

    public function forceDelete(int $id)
    {
        $model = static::getModel();

        $is_success = $model->delete($id, true);
        if ($is_success) {
            return $this->respondNoContent();
        }

        return $this->makeServerError(
            "There is an error on force deleting to the database server."
        );
    }

    private function processValidInputsOnly(callable $operation)
    {
        $validation = static::makeValidation();

        $request_document = $this->request->getJson(true);
        $is_success = $validation->run($request_document);

        if ($is_success) {
            return call_user_func($operation, $request_document[static::getIndividualName()]);
        }

        $raw_errors = $validation->getErrors();
        $formalized_errors = [];
        foreach ($raw_errors as $field => $message) {
            array_push($formalized_errors, [
                "field" => $field,
                "message" => $message
            ]);
        }

        return $this->failValidationError()->setJSON([
            "errors" => $formalized_errors
        ]);
    }

    private function makeServerError(string $development_message) {
        return $this->failServerError()->setJSON([
            "errors" => [
                [
                    "message" => $this->request->getServer("CI_ENVIRONMENT") === "development"
                        ? $development_message
                        : "Please contact the developer because there is an error."
                ]
            ]
        ]);
    }
}
