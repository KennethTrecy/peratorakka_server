<?php

namespace App\Libraries\Context;

use App\Libraries\Context;
use App\Libraries\Context\ContextKeys;

abstract class SingletonCache
{
    abstract protected static function contextKey(): ContextKeys;

    public static function make(Context $context): self
    {
        if (!$context->hasVariable(static::contextKey())) {
            $context->setVariable(static::contextKey(), new static($context));
        }

        return $context->getVariable(static::contextKey());
    }

    public readonly Context $context;

    public function __construct(Context $context)
    {
        $this->context = $context;
    }
}
