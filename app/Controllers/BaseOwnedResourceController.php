<?php

namespace App\Controllers;

use CodeIgniter\API\ResponseTrait;
use CodeIgniter\Exceptions\HTTPExceptionInterface;
use CodeIgniter\Validation\Validation;

use App\Contracts\OwnedResource;
use App\Controllers\BaseController;
use App\Entities\BaseResourceEntity;
use App\Exceptions\InvalidRequest;
use App\Exceptions\MissingResource;
use App\Exceptions\UnauthorizedRequest;
use App\Exceptions\ServerFailure;
use Config\Database;

abstract class BaseOwnedResourceController extends BaseController
{
    use ResponseTrait;

    abstract protected static function getIndividualName(): string;
    abstract protected static function getCollectiveName(): string;
    abstract protected static function getModelName(): string;

    abstract protected static function makeCreateValidation(): Validation;
    abstract protected static function makeUpdateValidation(int $id): Validation;

    public static function getInfo(): OwnedResourceInfo {
        return new OwnedResourceInfo(
            static::getCollectiveName(),
            static::getModelName()
        );
    }

    protected static function prepareRequestData(array $raw_data): array {
        return $raw_data;
    }

    protected static function getModel(): OwnedResource {
        return model(static::getModelName());
    }

    protected static function getEntity(): BaseResourceEntity {
        $entityName = static::getModel()->returnType;
        return new $entityName();
    }

    protected static function mustTransactForCreation(): bool {
        return false;
    }

    protected static function enrichResponseDocument(array $initial_document): array {
        return $initial_document;
    }

    protected static function processCreatedDocument(array $initial_document): array {
        return $initial_document;
    }

    private static function enrichAndOrganizeResponseDocument(array $initial_document): array {
        $enriched_document = static::enrichResponseDocument($initial_document);
        ksort($enriched_document);
        return $enriched_document;
    }

    private static function processAndOrganizeCreatedDocument(array $initial_document): array {
        $processed_document = static::processCreatedDocument($initial_document);
        ksort($processed_document);
        return $processed_document;
    }

    public function index()
    {
        $current_user = auth()->user();
        $request = $this->request;

        $model = static::getModel();
        $scoped_model = $model->limitSearchToUser($model, $current_user);

        $filter = $request->getVar("filter") ?? [];
        $scoped_model = $model->filterList($scoped_model, $filter);

        $sort = $request->getVar("sort") ?? [];
        $scoped_model = $model->sortList($scoped_model, $sort);

        $page = $request->getVar("page") ?? [];
        $scoped_model = $model->paginateList($scoped_model, $page);

        $overall_filtered_count = model(static::getModelName(), false);
        $overall_filtered_count = $overall_filtered_count->limitSearchToUser(
            $overall_filtered_count,
            $current_user
        );
        $overall_filtered_count = $overall_filtered_count->filterList(
            $overall_filtered_count,
            $filter
        );
        $overall_filtered_count = $overall_filtered_count->countAllResults();

        $response_document = [
            "meta" => [
                "overall_filtered_count" => $overall_filtered_count
            ],
            static::getCollectiveName() => $scoped_model->findAll()
        ];
        $response_document = static::enrichAndOrganizeResponseDocument($response_document);

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
            $response_document = static::enrichAndOrganizeResponseDocument($response_document);

            return response()->setJSON($response_document);
        } else {
            throw new MissingResource();
        }
    }

    public function create()
    {
        $controller = $this;
        $validation = $this->makeCreateValidation();
        return $this
            ->useValidInputsOnly(
                $validation,
                function($request_data) use ($controller) {
                    $current_user = auth()->user();

                    $model = static::getModel();
                    $info = static::prepareRequestData($request_data);
                    $entity = static::getEntity()->fill($info);
                    $database = static::mustTransactForCreation()
                        ? Database::connect()
                        : null;
                    if (static::mustTransactForCreation()) {
                        $database->transBegin();
                    }

                    try {
                        $is_success = $model->save($entity);
                        if ($is_success) {
                            $response_document = [
                                static::getIndividualName() => array_merge(
                                    [ "id" =>  $model->getInsertID() ],
                                    $info
                                )
                            ];
                            $response_document = static::processAndOrganizeCreatedDocument(
                                $response_document
                            );

                            if (static::mustTransactForCreation()) $database->transCommit();

                            return $controller->respondCreated()->setJSON($response_document);
                        }

                        if (static::mustTransactForCreation()) $database->transRollback();

                        throw new ServerFailure(
                            "There is an error on inserting to the database server."
                        );
                    } catch (HTTPExceptionInterface $exception) {
                        if (static::mustTransactForCreation()) $database->transRollback();

                        throw $exception;
                    }
                }
            );
    }

    public function update(int $id)
    {
        $controller = $this;
        $validation = $this->makeUpdateValidation($id);
        return $this
            ->useValidInputsOnly(
                $validation,
                function($request_data) use ($controller, $id) {
                    $current_user = auth()->user();

                    $model = static::getModel();
                    $info = array_merge(
                        static::prepareRequestData($request_data),
                        [ "id" => $id ]
                    );
                    $entity = static::getEntity()->fill($info);

                    $is_success = $model->save($entity);
                    if ($is_success) {
                        return $controller->respondNoContent();
                    }

                    throw new ServerFailure(
                        "There is an error on updating to the database server."
                    );
                }
            );
    }

    public function delete(int $id)
    {
        $model = static::getModel();

        $is_success = $model->delete($id);
        if ($is_success) {
            return $this->respondNoContent();
        }

        throw new ServerFailure(
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

        throw new ServerFailure(
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

        throw new ServerFailure(
            "There is an error on force deleting to the database server."
        );
    }

    protected function useValidInputsOnly(Validation $validation, callable $operation)
    {
        $request_document = $this->request->getJson(true);
        $is_success = $validation->run($request_document);

        if ($is_success) {
            return call_user_func($operation, $request_document[static::getIndividualName()]);
        }

        throw new InvalidRequest($validation);
    }
}
