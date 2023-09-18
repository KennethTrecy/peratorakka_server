<?php

namespace App\Validation;

use App\Models\AccountModel;
use App\Models\ModifierModel;

class EnumerationRules {
    public function may_allow_exchange_action(
        $value,
        string $parameters,
        array $data,
        ?string &$error = null
    ): bool {
        helper("array");

        $parameters = explode(",", $parameters);

        if (
            count($parameters) < 2
            || is_null(dot_array_search($parameters[0], $data))
        ) {
            $error = '"{0}" needs a key to modifier ID to check the validity for {field}.';
            return false;
        }

        if ($value === EXCHANGE_MODIFIER_ACTION) {
            $modifier_id = dot_array_search($parameters[0], $data);

            return $this->mustUseDualCurrency($modifier_id);
        }

        return true;
    }

    private function mustUseDualCurrency(int $modifier_id): bool {
        $modifier = model(ModifierModel::class)->find($modifier_id);
        $accounts = model(AccountModel::class)
            ->whereIn("id", [ $modifier->debit_account_id, $modifier->credit_account_id ])
            ->find();

        // If the accounts are in the same currency, prevent exchange modifier action.
        return $accounts[0]->currency_id !== $accounts[1]->currency_id;
    }
}
