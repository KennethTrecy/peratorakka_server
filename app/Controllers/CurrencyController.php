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
                        "message" => "The request resource was not found."
                    ]
                ]
            ]);
        }
    }

    public function create()
    {
        $current_user = auth()->user();
        $validation = single_service("validation");
        $validation->setRule("currency", "code", [
            "required",
            "array"
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
            $currency_model = model(CurrencyModel::class);

            $response_document = [
                "currencies" => $currency_model->insert(
                    array_merge(
                        [
                            "user_id" => $current_user->id
                        ],
                        $request_document
                    )
                )
            ];

            return $this->respondCreated()->setJSON($response_document);
        } else {
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
}
