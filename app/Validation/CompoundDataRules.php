<?php

namespace App\Validation;

use App\Libraries\FinancialEntryAtomInputExaminer;
use App\Libraries\ModifierAtomInputExaminer;
use App\Libraries\ItemConfigurationInputExaminer;
use App\Models\AccountModel;
use App\Models\ModifierModel;
use InvalidArgumentException;

class CompoundDataRules
{
    public function must_have_compound_data_key(
        $value,
        string $parameters,
        array $data,
        ?string &$error = null
    ): bool {
        helper("array");

        $parameters = explode(",", $parameters);

        return (
            count($parameters) === 1
            && is_array(dot_array_search($parameters[0], $data))
        );
    }

    public function must_have_compound_data_key_if_document_value_matches(
        $value,
        string $parameters,
        array $data,
        ?string &$error = null
    ): bool {
        helper("array");

        $parameters = explode(",", $parameters);

        if (count($parameters) !== 2) {
            throw new InvalidArgumentException(
                'A expected value and required document key (if condition matches) are needed'
                .' to check in resource.'
            );
        }

        return $value !== $parameters[0] || is_array(dot_array_search($parameters[1], $data));
    }

    // !: Validate data with `must_have_compound_data_key` first before putting this validator.
    public function has_valid_modifier_atom_group_info(
        $value,
        string $parameters,
        array $data,
        ?string &$error = null
    ): bool {
        $parameters = explode(",", $parameters);
        $modifier_atom_input_examiner = ModifierAtomInputExaminer::make($parameters[0], $data);

        return $modifier_atom_input_examiner->validateSchema();
    }

    // !: Validate data with `must_have_compound_data_key` first before putting this validator.
    public function has_valid_modifier_atom_group_cash_flow_activity(
        $value,
        string $parameters,
        array $data,
        ?string &$error = null
    ): bool {
        $parameters = explode(",", $parameters);
        $modifier_atom_input_examiner = ModifierAtomInputExaminer::make($parameters[0], $data);

        return $modifier_atom_input_examiner->validateCashFlowActivityAssociations($value);
    }

    // !: Validate data with `must_have_compound_data_key` first before putting this validator.
    public function has_valid_financial_entry_atom_group_info(
        $value,
        string $parameters,
        array $data,
        ?string &$error = null
    ): bool {
        $parameters = explode(",", $parameters);
        $financial_entry_atom_input_examiner = FinancialEntryAtomInputExaminer::make(
            $parameters[0],
            $data
        );

        return $financial_entry_atom_input_examiner->validateSchema();
    }

    // !: Validate data with `must_have_compound_data_key` first before putting this validator.
    public function has_valid_financial_entry_atom_group_values(
        $value,
        string $parameters,
        array $data,
        ?string &$error = null
    ): bool {
        $parameters = explode(",", $parameters);
        $financial_entry_atom_input_examiner = FinancialEntryAtomInputExaminer::make(
            $parameters[0],
            $data
        );

        return $financial_entry_atom_input_examiner->validateCurrencyValues($value);
    }

    // !: Validate data with `must_have_compound_data_key_if_document_value_matches` first before
    // !: putting this validator.
    public function has_valid_item_configuration_if_present(
        $value,
        string $parameters,
        array $data,
        ?string &$error = null
    ): bool {
        $parameters = explode(",", $parameters);
        if (is_array(dot_array_search($parameters[0], $data))) {
            $item_configuration_input_examiner = ItemConfigurationInputExaminer::make(
                $parameters[0],
                $data
            );

            $has_valid_schema = $item_configuration_input_examiner->validateSchema();

            if (!$has_valid_schema) {
                $error = "Malformed item configuration.";
                return false;
            }

            $has_owned_item_detail = $item_configuration_input_examiner->validateOwnership();

            if (!$has_owned_item_detail) {
                $error = "Item detail does not exist.";
                return false;
            }
        }

        return true;
    }
}
