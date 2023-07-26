<?php

namespace App\Casts;

use CodeIgniter\Entity\Cast\BaseCast;

class ResultSide extends BaseCast
{
    public static function get($value, array $params = [])
    {
        return RESULT_SIDES[$value];
    }

    public static function set($value, array $params = [])
    {
        $index = array_search($value, RESULT_SIDES, true);
        return $index;
    }
}
