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

        $data = [
            "currencies" => $currency_model->where("user_id", $current_user->id)->findAll()
        ];

        return respond()->json($data);
    }
}
