<?php

namespace App\Validation;

use App\Libraries\ModifierAtomInputExaminer;
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

    // !: Validate data with `must_have_compound_data_key` first before putting this validator.
    public function has_valid_atom_group_info(
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
    public function has_valid_atom_group_cash_flow_activity(
        $value,
        string $parameters,
        array $data,
        ?string &$error = null
    ): bool {
        $parameters = explode(",", $parameters);
        $modifier_atom_input_examiner = ModifierAtomInputExaminer::make($parameters[0], $data);

        return $modifier_atom_input_examiner->validateCashFlowActivityAssociations($value);
    }
}
