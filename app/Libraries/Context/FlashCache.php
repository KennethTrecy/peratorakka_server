<?php

namespace App\Libraries\Context;
use App\Libraries\Context\ContextKeys;

class FlashCache extends SingletonCache
{
    protected static function contextKey(): ContextKeys
    {
        return ContextKeys::FLASH_CACHE;
    }

    private array $cache = [];

    public function store(mixed $value): string
    {
        $key = '#'.uniqid();

        $this->cache[$key] = $value;

        return $key;
    }

    public function flash(string $key, mixed $default = null): mixed
    {
        if (!isset($this->cache[$key])) {
            return $default;
        }

        $value = $this->cache[$key];

        unset($this->cache[$key]);

        return $value;
    }
}
