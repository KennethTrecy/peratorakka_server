<?php

namespace App\Validation;

use App\Exceptions\NumericalToolConfigurationException;
use App\Libraries\NumericalToolConfiguration;

class NumericalToolRules
{
    public function valid_numerical_tool_configuration(
        $value,
        ?string &$error = null
    ): bool {
        try {
            $configuration = NumericalToolConfiguration::parseConfiguration($value);

            return !is_null($configuration);
        } catch (NumericalToolConfigurationException $exception) {
            $error = $exception->getMessage();
            return false;
        }
    }
}
