<?php

namespace App\Libraries;

class FlashCache
{
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
