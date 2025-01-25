<?php

namespace App\Libraries;

use App\Libraries\Context\ContextKeys;
use App\Libraries\Context\FlashCache;
use App\Libraries\Context\Memoizer;
use Xylemical\Expressions\Context as BaseContext;

class Context extends BaseContext
{
    public function __construct()
    {
        $this->setVariable(ContextKeys::FLASH_CACHE, new FlashCache());
        $this->setVariable(ContextKeys::MEMOIZER, new Memoizer());
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

    public function newScope(int $max_stack_count): Context
    {
        $clone = new Context();
        $clone->setVariables($this->getVariables());

        $current_stack_count = $clone->getVariable(ContextKeys::CURRENT_STACK_COUNT_STATUS, 0);
        // Statuses do not get passed
        $clone->setVariable(
            ContextKeys::CURRENT_STACK_COUNT_STATUS,
            $current_stack_count + 1
        );
        $clone->setVariable(ContextKeys::MAX_STACK_COUNT_STATUS, $max_stack_count);

        return $clone;
    }
}
