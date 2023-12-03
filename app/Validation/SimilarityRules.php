<?php

namespace App\Validation;

use App\Models\AccountModel;
use App\Models\FinancialEntryModel;
use App\Models\ModifierModel;

class SimilarityRules {
    public function must_be_same_for_modifier(
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
                .' to check the required similarity for {field}.';
            return false;
        }

        $credit_value = dot_array_search($parameters[1], $data);
        if ($credit_value === $debit_value) return true;

        $modifier_id = dot_array_search($parameters[0], $data);

        return $this->mayAllowForDualCurrency($modifier_id);
    }

    public function must_be_same_for_financial_entry(
        $debit_value,
        string $parameters,
        array $data,
        ?string &$error = null
    ): bool {
        helper("array");

        $parameters = explode(",", $parameters);

        if (
            count($parameters) < 2
            || is_null(dot_array_search($parameters[1], $data))
        ) {
            $error = '"{0}" needs a key to financial entry ID and key to credit value'
                .' to check the required similarity for {field}.';
            return false;
        }

        $credit_value = dot_array_search($parameters[1], $data);
        if ($credit_value === $debit_value) return true;

        $financial_entry_id = intval($parameters[0]);
        $financial_entry = model(FinancialEntryModel::class)->find($financial_entry_id);
        $modifier_id = $financial_entry->modifier_id;

        return $this->mayAllowForDualCurrency($modifier_id);
    }

    public function must_be_same_as_password_of_current_user(
        $given_password,
        ?string &$error = null
    ): bool {
        $current_user = auth()->user();
        $password_service = service("passwords");

        return $password_service->verify($given_password, $current_user->password_hash);
    }

    private function mayAllowForDualCurrency(int $modifier_id): bool {
        $modifier = model(ModifierModel::class)->find($modifier_id);
        $accounts = model(AccountModel::class)
            ->whereIn("id", [ $modifier->debit_account_id, $modifier->credit_account_id ])
            ->find();

        // If the accounts are not in the same currency, allow the debit and credit amount to be
        // different.
        return $accounts[0]->currency_id !== $accounts[1]->currency_id;
    }
}
