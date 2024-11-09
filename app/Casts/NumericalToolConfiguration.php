<?php

namespace App\Casts;

use App\Libraries\NumericalToolConfiguration as ParsedNumericalToolConfiguration;
use CodeIgniter\Entity\Cast\BaseCast;

class NumericalToolConfiguration extends BaseCast
{
    public static function get($value, array $params = [])
    {
        return ParsedNumericalToolConfiguration::parseConfiguration(
            json_decode($value, true)
        );
    }

    public static function set($value, array $params = [])
    {
        return strval($value);
    }
}
