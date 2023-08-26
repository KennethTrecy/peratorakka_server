<?php

namespace App\Casts;

use Brick\Math\BigRational;
use CodeIgniter\Entity\Cast\BaseCast;

class RationalNumber extends BaseCast
{
    public static function get($value, array $params = [])
    {
        return BigRational::of($value);
    }

    public static function set($value, array $params = [])
    {
        return strval($value);
    }
}
