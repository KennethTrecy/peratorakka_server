<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Models\CurrencyModel;

class CurrencyController extends BaseController
{
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

        $request_document = $request->getJson(true);
        $is_success = $validation->run($request->getJson(true));

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
                )->findAll()
            ];

            return response()->setJSON($response_document);
        } else {
            $raw_errors = $validation->getErrors();
            $formalized_errors = [];
            foreach ($raw_errors as $field => $message) {
                array_push($formalized_errors, [
                    "field" => $field,
                    "message" => $message
                ]);
            }

            return response()->failValidationError()->setJSON([
                "errors" => $formalized_errors
            ]);
        }
    }
}
