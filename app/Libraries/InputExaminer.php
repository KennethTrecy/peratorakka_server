<?php

namespace App\Libraries;

use App\Libraries\Context;

class InputExaminer
{
    private static array $instances = [];

    protected readonly array $input;
    protected readonly Context $context;

    public static function make(string $key, array $data): Self
    {
        helper("array");

        if (!isset(self::$instances[$key])) {
            static::$instances[$key] = new static(dot_array_search($key, $data) ?? []);
        }

        return static::$instances[$key];
    }

    public static function clear()
    {
        static::$instances = [];
    }

    private function __construct(array $input)
    {
        $this->context = Context::make();
        $this->input = $input;
    }
}
