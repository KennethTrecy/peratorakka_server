<?php

namespace App\Validation;

use App\Libraries\NumericalToolConfiguration;

class NumericalToolRules
{
    public function valid_numerical_tool_configuration(
        $value,
        ?string &$error = null
    ): bool {
        $configuration = NumericalToolConfiguration::parseConfiguration($value);

        return !is_null($configuration);
    }
}
