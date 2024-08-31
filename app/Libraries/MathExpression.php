<?php

namespace App\Libraries;

use App\Libraries\FlashCache;
use App\Libraries\MathExpression\Context;
use App\Libraries\MathExpression\ExpressionFactory;
use App\Libraries\MathExpression\PeratorakkaMath;
use Brick\Math\BigRational;
use Xylemical\Expressions\Evaluator;
use Xylemical\Expressions\Lexer;
use Xylemical\Expressions\Parser;

class MathExpression
{
    private readonly TimeGroupManager $manager;
    private readonly FlashCache $cache;

    public function __construct(TimeGroupManager $manager) {
        $this->manager = $manager;
        $this->cache = new FlashCache();
    }

    public function evaluate(string $formula): array {
        $math = new PeratorakkaMath();
        $expression_factory = new ExpressionFactory($this->cache, $math);
        $lexer = new Lexer($expression_factory);
        $parser = new Parser($lexer);
        $evaluator = new Evaluator();

        $context = new Context($this->manager, $this->cache);

        foreach ($context as $key => $value) {
            $context->setVariable($key, $value);
        }

        $tokens = $parser->parse($formula);
        $rawResult = $evaluator->evaluate($tokens, $context);
        $result = $math->resolve($rawResult);

        return $result;
    }
}
