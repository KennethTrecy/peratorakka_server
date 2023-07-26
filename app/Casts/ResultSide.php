<?php

namespace App\Casts;

use CodeIgniter\Entity\Cast\BaseCast;

class ResultSide extends BaseCast
{
    public static function get($value, array $params = [])
    {
        return RESULT_SIDE[$value];
    }

    public static function set($value, array $params = [])
    {
        $index = array_search($value, RESULT_SIDE, true);
        return $index;
    }
}
