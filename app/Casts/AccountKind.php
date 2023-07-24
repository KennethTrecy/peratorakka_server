<?php

namespace App\Casts;

use CodeIgniter\Entity\Cast\BaseCast;

class AccountKind extends BaseCast
{
    public static function get($value, array $params = [])
    {
        return ACCOUNT_KINDS[$value];
    }

    public static function set($value, array $params = [])
    {
        $index = array_search($value, ACCOUNT_KINDS, true);
        return max(0, $index);
    }
}
