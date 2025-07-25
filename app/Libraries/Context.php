<?php

namespace App\Libraries;

use App\Libraries\Context\ContextKeys;
use App\Libraries\Context\FlashCache;
use App\Libraries\Context\Memoizer;
use CodeIgniter\Shield\Entities\User;
use Xylemical\Expressions\Context as BaseContext;

class Context extends BaseContext
{
    /**
     * @type Context[]
     */
    private static ?Context $instance = null;

    public static function make(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public static function clear()
    {
        self::$instance = null;
    }

    public function __construct()
    {
        FlashCache::make($this);
        Memoizer::make($this);
    }

    public function getVariable($name, $default = null)
    {
        if ($name instanceof ContextKeys) {
            $name = $name->value;
        }

        return parent::getVariable($name, $default);
    }

    public function hasVariable($name)
    {
        if ($name instanceof ContextKeys) {
            $name = $name->value;
        }

        return parent::hasVariable($name);
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

    public function user(): User
    {
        $user = $this->getVariable(ContextKeys::USER, null);

        if ($user === null) {
            $this->setVariable(ContextKeys::USER, auth()->user());

            return $this->user();
        } else {
            return $user;
        }
    }
}
