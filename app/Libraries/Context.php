<?php

namespace App\Libraries;

use App\Libraries\Context\ContextKeys;
use App\Libraries\Context\FlashCache;
use Xylemical\Expressions\Context as BaseContext;

class Context extends BaseContext
{
    public function __construct()
    {
        $this->setVariable(ContextKeys::FLASH_CACHE, new FlashCache());
    }

    public function getVariable($name, $default = null)
    {
        if ($name instanceof ContextKeys) {
            $name = $name->value;
        }

        return parent::getVariable($name, $default);
    }

    public function setVariable($name, $value)
    {
        if ($name instanceof ContextKeys) {
            $name = $name->value;
        }

        return parent::setVariable($name, $value);
    }
}
