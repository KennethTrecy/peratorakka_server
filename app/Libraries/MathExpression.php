<?php

namespace App\Libraries;

use App\Casts\RationalNumber;
use App\Libraries\Context;
use App\Libraries\Context\TimeGroupManager;
use App\Libraries\Context\ContextKeys;
use App\Libraries\MathExpression\ExpressionFactory;
use App\Libraries\MathExpression\PeratorakkaMath;
use Xylemical\Expressions\Evaluator;
use Xylemical\Expressions\Lexer;
use Xylemical\Expressions\Parser;

class MathExpression
{
    private readonly TimeGroupManager $manager;

    public function __construct(TimeGroupManager $manager)
    {
        $this->manager = $manager;
    }

    public function evaluate(string $formula): array
    {
        $manager = $this->manager;
        $cache = $manager->context->getVariable(ContextKeys::FLASH_CACHE);
        $memo = $manager->context->getVariable(ContextKeys::MEMOIZER);
        $math = new PeratorakkaMath();
        $expression_factory = new ExpressionFactory($cache, $memo, $math);
        $lexer = new Lexer($expression_factory);
        $parser = new Parser($lexer);
        $evaluator = new Evaluator();

        $tokens = $parser->parse($formula);
        $rawResult = $evaluator->evaluate($tokens, $manager->context);
        $result = $math->resolve($rawResult);

        return $result;
    }

    public static function summatePeriodicResults(array $periodic_results): array
    {
        return array_map(
            function (array $individual_period_results) {
                return is_array($individual_period_results)
                    ? array_reduce(
                        $individual_period_results,
                        function ($total, $individual_result) {
                            return $total->plus($individual_result);
                        },
                        RationalNumber::zero()
                    )->simplified()
                    : $individual_period_results->simplified();
            },
            $periodic_results
        );
    }
}
