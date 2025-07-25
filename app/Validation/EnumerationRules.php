<?php

namespace App\Validation;

use App\Libraries\ModifierAtomInputExaminer;
use App\Models\AccountModel;
use App\Models\ModifierModel;
use InvalidArgumentException;

class EnumerationRules
{
    // !: Validate data with `must_have_compound_data_key` first before putting this validator.
    public function may_allow_modifier_action(
        $value,
        string $parameters,
        array $data,
        ?string &$error = null
    ): bool {
        $parameters = explode(",", $parameters);
        $modifier_atom_input_examiner = ModifierAtomInputExaminer::make($parameters[0], $data);

        return $modifier_atom_input_examiner->validateAction($value);
    }
}
