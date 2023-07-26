<?php

namespace App\Casts;

use CodeIgniter\Entity\Cast\BaseCast;

class UnknownableKind extends BaseCast
{
    protected static array $KINDS;
    protected static string $UNKNOWN_KIND;

    public static function get($value, array $params = [])
    {
        return isset(static::$KINDS[$value])
            ? static::$KINDS[$value]
            : static::$UNKNOWN_KIND;
    }

    public static function set($value, array $params = [])
    {
        $index = array_search($value, static::$KINDS, true);
        return $index < 0
            ? array_search(static::$UNKNOWN_KIND, static::$KINDS, true)
            : $index;
    }
}
