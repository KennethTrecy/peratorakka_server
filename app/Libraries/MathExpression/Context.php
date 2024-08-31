<?php

namespace App\Libraries\MathExpression;

use App\Libraries\TimeGroupManager;
use Xylemical\Expressions\Context as BaseContext;

class Context extends BaseContext {
    public function __construct(TimeGroupManager $manager) {
        parent::__construct([
            "manager" => $manager
        ]);
    }

    public function timeGroupManager(): TimeGroupManager {
        return $this->getVariable("manager");
    }
}
