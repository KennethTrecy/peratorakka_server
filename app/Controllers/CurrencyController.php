<?php

namespace App\Controllers;

use CodeIgniter\API\ResponseTrait;

use App\Controllers\BaseController;
use App\Models\CurrencyModel;

class CurrencyController extends BaseController
{
    use ResponseTrait;

    public function index()
    {
        $current_user = auth()->user();

        $currency_model = model(CurrencyModel::class);

        $response_document = [
            "currencies" => $currency_model->where("user_id", $current_user->id)->findAll()
        ];

        return response()->setJSON($response_document);
    }

    public function show(int $id)
    {
        $current_user = auth()->user();
        $currency_model = model(CurrencyModel::class);
        $currency_data = $currency_model->find($id);

        $is_success = !is_null($currency_data);

        if ($is_success) {
            $response_document = [
                "currency" => $currency_model->find($id)
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

            $currency_model = model(CurrencyModel::class);
            $currency_info = array_merge(
                [ "user_id" => $current_user->id ],
                $request_data
            );

            $is_success = $currency_model->insert($currency_info, false);
            if ($is_success) {
                $response_document = [
                    "currency" => array_merge(
                        [ "id" =>  $currency_model->getInsertID() ],
                        $currency_info
                    )
                ];

                return $controller->respondCreated()->setJSON($response_document);
            }

            return $controller->failServerError()->setJSON([
                "errors" => [
                    [
                        "message" => $request->getServer("CI_ENVIRONMENT") === "development"
                            ? "There is an error on inserting to the database server."
                            : "Please contact the developer because there is an error."
                    ]
                ]
            ]);
        });
    }

    private function processValidInputsOnly(callable $operation)
    {
        $validation = single_service("validation");
        $validation->setRule("currency", "currency info", [
            "required"
        ]);
        $validation->setRule("currency.code", "code", [
            "required",
            "alpha_numeric"
        ]);
        $validation->setRule("currency.name", "name", [
            "required",
            "alpha_numeric"
        ]);

        $request_document = $this->request->getJson(true);
        $is_success = $validation->run($request_document);

        if ($is_success) {
            return call_user_func($operation, $request_document["currency"]);
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
}
