<?php

namespace App\Libraries\Context;

use App\Libraries\Context\ContextKeys;

class Memoizer extends SingletonCache
{
    protected static function contextKey(): ContextKeys
    {
        return ContextKeys::MEMOIZER;
    }

    private array $memo = [];

    public function write(string $key, mixed $value): void
    {
        $this->memo[$key] = $value;
    }

    public function read(string $key, mixed $default = null): mixed
    {
        if (!isset($this->memo[$key])) {
            return $default;
        }

        $value = $this->memo[$key];

        return $value;
    }
}
