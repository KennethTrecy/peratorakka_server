<?php

namespace App\Casts\Deprecated;

use App\Libraries\NumericalToolConfiguration\Deprecated\DeprecatedNumericalToolConfiguration;
use CodeIgniter\Entity\Cast\BaseCast;

class NumericalToolConfiguration extends BaseCast
{
    public static function get($value, array $params = [])
    {
        return DeprecatedNumericalToolConfiguration::parseConfiguration(
            json_decode($value, true)
        );
    }

    public static function set($value, array $params = [])
    {
        return strval($value);
    }
}
