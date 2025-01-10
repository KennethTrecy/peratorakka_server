<?php

namespace App\Libraries\MathExpression;

use App\Casts\RationalNumber;
use App\Libraries\Context\FlashCache;
use App\Libraries\Context\Memoizer;
use App\Libraries\MathExpression\ExpressionFactory\RegisterProcedures;
use App\Libraries\MathExpression\ExpressionFactory\RegisterValues;
use App\Libraries\MathExpression\PeratorakkaMath;
use CodeIgniter\Database\BaseBuilder;
use Xylemical\Expressions\ExpressionFactory as BaseExpressionFactory;
use Xylemical\Expressions\Operator;
use Xylemical\Expressions\Procedure;
use Xylemical\Expressions\Token;
use Xylemical\Expressions\Value;

class ExpressionFactory extends BaseExpressionFactory
{
    use RegisterValues;
    use RegisterProcedures;

    private readonly FlashCache $cache;
    private readonly Memoizer $memo;

    public function __construct(FlashCache $cache, Memoizer $memo, PeratorakkaMath $math)
    {
        parent::__construct($math);

        $this->cache = $cache;
        $this->memo = $memo;

        $this->addValues();
        $this->addProcedures();
    }
}
