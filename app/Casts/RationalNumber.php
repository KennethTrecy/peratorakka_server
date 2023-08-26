<?php

namespace App\Casts;

use Brick\Math\BigRational;

class PreciseNumber extends UnknownableKind
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
