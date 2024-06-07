<?php

namespace App\Validation;

use InvalidArgumentException;

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
            || is_null(dot_array_search($parameters[1], $data))
        ) {
            throw new InvalidArgumentException(
                '"may_allow_exchange_action" needs keys to account IDs from both sides'
                .' to check the validity for field.'
            );
        }

        if ($value === EXCHANGE_MODIFIER_ACTION) {
            $debit_account_id = dot_array_search($parameters[0], $data);
            $credit_account_id = dot_array_search($parameters[1], $data);

            return $this->mustBeDifferentCurrencies($debit_account_id, $credit_account_id);
        }

        return true;
    }

    private function mustBeDifferentCurrencies(
        int $debit_account_id,
        int $credit_account_id
    ): bool {
        $accounts = model(AccountModel::class)
            ->whereIn("id", [ $debit_account_id, $credit_account_id ])
            ->find();

        // If the accounts are in the same currency, prevent exchange modifier action.
        return $accounts[0]->currency_id !== $accounts[1]->currency_id;
    }
}
