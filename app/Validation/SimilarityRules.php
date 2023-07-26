<?php

namespace App\Validation;

use App\Models\AccountModel;
use App\Models\CurrencyModel;
use App\Models\ModifierModel;

class SimilarityRules {
    public function must_be_same_amount_if_opposite_account_is_on_same_currency(
        $debit_value,
        string $parameters,
        array $data,
        ?string &$error = null
    ): bool {
        helper("array");

        $parameters = explode(",", $parameters);

        if (
            count($parameters) < 2
            || is_null(dot_array_search($parameters[0], $data))
            || is_null(dot_array_search($parameters[1], $data))
        ) {
            $error = '"{0}" needs a key to modifier ID and key to credit value'
                .'to check the required similarity for {field}.';
            return false;
        }

        $credit_value = dot_array_search($parameters[1], $data);
        if ($credit_value === $debit_value) return true;

        $modifier_id = dot_array_search($parameters[0], $data);
        $modifier = model(ModifierModel::class)->find($modifier_id);
        $accounts = model(AccountModel::class)
            ->whereIn("id", [ $modifier->account_id, $modifier->opposite_account_id ])
            ->find();

        // If the accounts are not in the same currency, allow the debit and credit amount to be
        // different.
        return $account[0]->currency_id !== $account[1]->currency_id;
    }
}
